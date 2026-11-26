import type {
  BorrowRequestItem,
  DemoRole,
  FixedSchedule,
  OrganizationProfile,
  WeekdayKey,
} from '~/types/coordina'

export interface StepItem {
  title: string
  subtitle: string
  state: 'done' | 'current' | 'rejected' | 'pending'
}

export function isOrgProfileComplete(org?: OrganizationProfile | null): boolean {
  if (!org) return false
  return Boolean(
    org.chairpersonName?.trim() &&
      org.chairpersonTitle?.trim() &&
      org.logoDataUrl &&
      org.signatureDataUrl
  )
}

export function getWeekdayFromDate(dateStr: string): WeekdayKey | null {
  if (!dateStr || !dateStr.includes('-')) return null
  const [y, m, d] = dateStr.split('-').map(Number)
  const dt = new Date(y, (m || 1) - 1, d || 1)
  const en = dt.toLocaleDateString('en-US', { weekday: 'long' }) as WeekdayKey
  return en
}

export function formatDateId(dateStr: string | null): string {
  if (!dateStr || !dateStr.includes('-')) return dateStr || '-'
  const [y, m, d] = dateStr.split('-').map(Number)
  const dt = new Date(y, (m || 1) - 1, d || 1)
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(dt)
}

export function formatTimeRange(start: string, end: string): string {
  return `${start.slice(0, 5)} – ${end.slice(0, 5)} WIB`
}

export function workflowTitle(req: BorrowRequestItem): string {
  if (req.workflowType === 'mpk_borrow') return 'Alur B · Peminjaman Resmi MPK (2 Tanda Tangan)'
  if (req.workflowType === 'free_slot') return 'Slot Kosong · Tidak Bentrok Jadwal Rutin'
  return 'Alur A · Peminjaman Antar-Ekskul (3 Tanda Tangan)'
}

export function getHumanStatusBadge(req: BorrowRequestItem): {
  label: string
  tone: 'emerald' | 'blue' | 'amber' | 'rose'
  icon: 'check' | 'clock' | 'x'
} {
  switch (req.status) {
    case 'pending_mpk_screening':
      return { label: 'Menunggu Pemeriksaan MPK', tone: 'amber', icon: 'clock' }
    case 'pending_owner':
      return { label: 'Menunggu Persetujuan Pengurus Ekskul', tone: 'blue', icon: 'clock' }
    case 'owner_approved':
      return {
        label:
          req.workflowType === 'mpk_borrow'
            ? 'Disetujui Pemilik · Siap Dicatat MPK'
            : 'Menunggu Verifikasi Akhir',
        tone: 'emerald',
        icon: 'check',
      }
    case 'owner_rejected':
      return { label: 'Ditolak Pemilik · Siap Dicatat MPK', tone: 'rose', icon: 'x' }
    case 'approved':
      return { label: 'Disetujui', tone: 'emerald', icon: 'check' }
    case 'rejected':
      return { label: 'Ditolak', tone: 'rose', icon: 'x' }
  }
}

export function buildWorkflowSteps(
  req: BorrowRequestItem,
  orgs: OrganizationProfile[]
): { summary: string; steps: StepItem[] } {
  const requesterName = orgs.find((o) => o.id === req.requesterOrgId)?.name || 'Pemohon'
  const ownerName = req.ownerOrgId ? orgs.find((o) => o.id === req.ownerOrgId)?.name || 'Pemilik Jadwal' : null
  const sig1 = req.signatures.find((s) => s.stageOrder === 1)?.signed ?? false
  const sig2 = req.signatures.find((s) => s.stageOrder === 2)?.signed ?? false
  const sig3 = req.signatures.find((s) => s.stageOrder === 3)?.signed ?? false

  // Workflow B: MPK borrows extracurricular schedule (3 clear stages, 2 signatures only, NO second approval!)
  if (req.workflowType === 'mpk_borrow') {
    const ownerRejected = req.status === 'owner_rejected' || req.status === 'rejected'
    const ownerApprovedOrDone = req.status === 'owner_approved' || req.status === 'approved'
    const steps: StepItem[] = [
      {
        title: `1. Pengajuan & TTD Perwakilan MPK (${requesterName})`,
        subtitle: sig1
          ? 'Permintaan dikirim langsung ke pemilik jadwal (tanpa pemeriksaan MPK terpisah).'
          : 'Menunggu tanda tangan perwakilan MPK.',
        state: sig1 ? 'done' : 'current',
      },
      {
        title: `2. Persetujuan & TTD Pemilik Jadwal (${ownerName})`,
        subtitle: ownerRejected
          ? `Ditolak oleh ${ownerName} dengan alasan tertulis.`
          : ownerApprovedOrDone && sig2
            ? `Disetujui & ditandatangani oleh Ketua ${ownerName}.`
            : `Menunggu keputusan dan tanda tangan dari ${ownerName}.`,
        state: ownerRejected ? 'rejected' : ownerApprovedOrDone ? 'done' : 'current',
      },
      {
        title: '3. Pencatatan Hasil & Pengarsipan oleh MPK',
        subtitle:
          req.status === 'approved'
            ? `Dicatat & diarsipkan resmi (${req.letterNumber || 'Surat Resmi'}).`
            : req.status === 'rejected'
              ? 'Penolakan dicatat resmi · surat izin tidak diterbitkan.'
              : req.status === 'owner_approved'
                ? 'Siap dicatat & diarsipkan MPK (tidak memerlukan persetujuan ulang).'
                : req.status === 'owner_rejected'
                  ? 'Penolakan pemilik siap dicatat resmi oleh MPK.'
                  : 'Menunggu keputusan pemilik jadwal.',
        state:
          req.status === 'approved'
            ? 'done'
            : req.status === 'rejected'
              ? 'rejected'
              : ['owner_approved', 'owner_rejected'].includes(req.status)
                ? 'current'
                : 'pending',
      },
    ]

    const stepNum =
      req.status === 'pending_owner' ? 2 : ['owner_approved', 'owner_rejected', 'approved', 'rejected'].includes(req.status) ? 3 : 1
    return {
      summary: `Tahap ${stepNum} dari 3 · Alur B (2 Blok TTD, Tanpa Screening)`,
      steps,
    }
  }

  // Free slot
  if (req.workflowType === 'free_slot') {
    const isMpkReq = orgs.find((o) => o.id === req.requesterOrgId)?.isMpk
    if (isMpkReq) {
      return {
        summary: `Tahap ${req.status === 'approved' ? 2 : 1} dari 2 · Slot Kosong MPK (1 Blok TTD)`,
        steps: [
          {
            title: `1. Pengajuan Resmi & TTD MPK (${requesterName})`,
            subtitle: 'Slot kosong dicatat langsung atas nama MPK.',
            state: 'done',
          },
          {
            title: '2. Penerbitan & Pengarsipan Surat Resmi',
            subtitle:
              req.status === 'approved'
                ? `Terbit resmi (${req.letterNumber || 'Siap Dicetak'}).`
                : 'Menunggu pencatatan surat.',
            state: req.status === 'approved' ? 'done' : 'current',
          },
        ],
      }
    }

    const rejected = req.status === 'rejected'
    return {
      summary: `Tahap ${req.status === 'approved' ? 3 : 2} dari 3 · Slot Kosong Ekskul (2 Blok TTD)`,
      steps: [
        {
          title: `1. Pengajuan & TTD Pemohon (${requesterName})`,
          subtitle: 'Pemohon mengonfirmasi permohonan pada slot kosong.',
          state: sig1 ? 'done' : 'current',
        },
        {
          title: '2. Pemeriksaan & TTD MPK Komisi 2',
          subtitle: rejected
            ? 'Ditolak pada pemeriksaan MPK.'
            : req.status === 'approved'
              ? 'Disetujui & ditandatangani MPK.'
              : 'Menunggu pemeriksaan MPK (tanpa pemilik jadwal tetap).',
          state: rejected ? 'rejected' : req.status === 'approved' ? 'done' : 'current',
        },
        {
          title: '3. Penerbitan Surat Resmi',
          subtitle: rejected
            ? 'Tidak ada surat izin diterbitkan.'
            : req.status === 'approved'
              ? `Terbit resmi (${req.letterNumber || 'Arsip Siap'}).`
              : 'Menunggu persetujuan MPK.',
          state: rejected ? 'rejected' : req.status === 'approved' ? 'done' : 'pending',
        },
      ],
    }
  }

  // Workflow A: Inter-extracurricular borrowing (4 distinct stages, 3 signatures)
  const rejectedAtScreening = req.status === 'rejected' && !sig2
  const rejectedByOwner = req.status === 'owner_rejected' || (req.status === 'rejected' && sig2)
  const passedScreening = ['pending_owner', 'owner_approved', 'owner_rejected', 'approved'].includes(req.status) || (req.status === 'rejected' && sig2)

  const steps: StepItem[] = [
    {
      title: `1. Pengajuan & TTD Pemohon (${requesterName})`,
      subtitle: sig1 ? `Ditandatangani Ketua ${requesterName}.` : 'Menunggu TTD pemohon.',
      state: sig1 ? 'done' : 'current',
    },
    {
      title: '2. Pemeriksaan Awal & TTD Perwakilan MPK',
      subtitle: rejectedAtScreening
        ? 'Ditolak saat pemeriksaan awal MPK.'
        : passedScreening && sig2
          ? 'Lolos pemeriksaan & ditandatangani MPK Komisi 2.'
          : 'MPK meninjau kelayakan dan meneruskan ke pemilik jadwal.',
      state: rejectedAtScreening ? 'rejected' : passedScreening && sig2 ? 'done' : 'current',
    },
    {
      title: `3. Keputusan & TTD Pemilik Jadwal (${ownerName})`,
      subtitle: rejectedByOwner
        ? `Ditolak oleh ${ownerName}.`
        : ['owner_approved', 'approved'].includes(req.status) && sig3
          ? `Disetujui & ditandatangani Ketua ${ownerName}.`
          : req.status === 'pending_owner'
            ? `Menunggu keputusan dari ${ownerName}.`
            : 'Menunggu pemeriksaan MPK selesai.',
      state: rejectedByOwner
        ? 'rejected'
        : ['owner_approved', 'approved'].includes(req.status) && sig3
          ? 'done'
          : req.status === 'pending_owner'
            ? 'current'
            : 'pending',
    },
    {
      title: '4. Verifikasi Administratif & Publikasi oleh MPK',
      subtitle:
        req.status === 'approved'
          ? `Terverifikasi & terbit resmi (${req.letterNumber || 'PDF Siap'}).`
          : req.status === 'rejected'
            ? 'Pengajuan ditutup dengan alasan penolakan tertulis.'
            : ['owner_approved', 'owner_rejected'].includes(req.status)
              ? 'Semua pihak telah memberi keputusan · siap diverifikasi & dipublikasikan MPK.'
              : 'Menunggu keputusan pemilik jadwal.',
      state:
        req.status === 'approved'
          ? 'done'
          : req.status === 'rejected'
            ? 'rejected'
            : ['owner_approved', 'owner_rejected'].includes(req.status)
              ? 'current'
              : 'pending',
    },
  ]

  let currentStage = 1
  if (rejectedAtScreening || req.status === 'pending_mpk_screening') currentStage = 2
  else if (req.status === 'pending_owner') currentStage = 3
  else if (['owner_approved', 'owner_rejected', 'approved', 'rejected'].includes(req.status)) currentStage = 4

  return {
    summary: `Tahap ${currentStage} dari 4 · Alur A (3 Blok TTD)`,
    steps,
  }
}

export function getActionGuidance(
  req: BorrowRequestItem,
  role: DemoRole,
  activeEskulOrgId: number,
  orgs: OrganizationProfile[]
): { tone: 'blue' | 'emerald' | 'amber' | 'rose'; title: string; description: string } {
  const requesterName = orgs.find((o) => o.id === req.requesterOrgId)?.name || 'Pemohon'
  const ownerName = req.ownerOrgId ? orgs.find((o) => o.id === req.ownerOrgId)?.name || 'Pemilik Jadwal' : null

  if (req.status === 'approved') {
    return {
      tone: 'emerald',
      title: `Surat Resmi Sudah Diterbitkan (${req.letterNumber})`,
      description:
        'Seluruh tanda tangan dan verifikasi telah lengkap. Surat izin tersimpan di Arsip Surat Resmi dan dapat dipratinjau atau dicetak.',
    }
  }

  if (req.status === 'rejected') {
    return {
      tone: 'rose',
      title: 'Permohonan Ditutup (Ditolak)',
      description: req.rejectionReason
        ? `Alasan penolakan tercatat: “${req.rejectionReason}”. Tidak ada surat izin resmi yang diterbitkan.`
        : 'Permohonan ini ditolak dan riwayatnya tersimpan tanpa menerbitkan surat resmi.',
    }
  }

  if (req.status === 'pending_mpk_screening') {
    if (role === 'mpk') {
      return {
        tone: 'blue',
        title: ownerName
          ? `Periksa Kelayakan & Teruskan ke ${ownerName}`
          : 'Periksa & Setujui Penggunaan Slot Kosong',
        description: ownerName
          ? `Tinjau alasan peminjaman dari ${requesterName}. Jika wajar dan lengkap, klik Setujui Pemeriksaan & TTD MPK agar surat diteruskan ke ${ownerName}.`
          : `Jadwal ini kosong tanpa pemilik tetap. Klik Setujui & Terbitkan Surat untuk menandatangani dan langsung menerbitkan surat resmi.`,
      }
    }
    return {
      tone: 'amber',
      title: 'Menunggu Pemeriksaan MPK',
      description:
        'Permohonan sudah masuk dengan tanda tangan pemohon. Saat ini MPK sedang meninjau kelayakan sebelum meneruskannya.',
    }
  }

  if (req.status === 'pending_owner') {
    const isAffectedOwner = role === 'eskul' && req.ownerOrgId === activeEskulOrgId
    if (isAffectedOwner) {
      return {
        tone: 'blue',
        title: `Keputusan Ada di Tangan ${ownerName}`,
        description:
          req.workflowType === 'mpk_borrow'
            ? `${requesterName} mengajukan peminjaman resmi atas jadwal rutin Anda (Alur B · 2 TTD). Klik Setujui & TTD Pemilik jika jadwal bisa digunakan, atau Tolak dengan alasan tertulis.`
            : `${requesterName} telah lolos pemeriksaan MPK dan meminta izin memakai jadwal rutin Anda. Klik Setujui & TTD Pemilik untuk mengizinkan, atau Tolak dengan alasan tertulis.`,
      }
    }
    return {
      tone: 'blue',
      title: `Menunggu Persetujuan ${ownerName}`,
      description: `Berkas telah dikirim kepada ${ownerName}. Pemilik jadwal berhak penuh menyetujui atau menolak permintaan ini.`,
    }
  }

  if (req.status === 'owner_approved') {
    if (role === 'mpk') {
      return {
        tone: 'emerald',
        title:
          req.workflowType === 'mpk_borrow'
            ? 'Pemilik Menyetujui · Siap Dicatat & Diarsipkan'
            : 'Seluruh 3 Tanda Tangan Lengkap · Siap Verifikasi Akhir',
        description:
          req.workflowType === 'mpk_borrow'
            ? 'Pemilik jadwal sudah menyetujui dan membubuhkan tanda tangan ke-2. Klik Catat & Terbitkan Surat Resmi untuk mengarsipkan (tanpa persetujuan ulang).'
            : 'Pemohon, MPK, dan Pemilik Jadwal sudah menandatangani. Lakukan verifikasi administratif dan klik Verifikasi & Publikasikan Surat.',
      }
    }
    return {
      tone: 'emerald',
      title: 'Menunggu Verifikasi & Publikasi Akhir MPK',
      description: 'Persetujuan pemilik sudah masuk. MPK segera menerbitkan nomor surat resmi dan dokumen arsip.',
    }
  }

  if (req.status === 'owner_rejected') {
    if (role === 'mpk') {
      return {
        tone: 'rose',
        title: 'Pemilik Menolak · Catat Hasil Penolakan',
        description: `Pemilik jadwal (${ownerName}) menolak dengan alasan: “${req.rejectionReason}”. Catat hasil penolakan agar pemohon mendapat kepastian resmi.`,
      }
    }
    return {
      tone: 'rose',
      title: `Ditolak oleh ${ownerName}`,
      description: `Pemilik jadwal menolak dengan alasan: “${req.rejectionReason}”. Menunggu pencatatan akhir dari MPK.`,
    }
  }

  return {
    tone: 'blue',
    title: 'Informasi Permohonan',
    description: 'Pantau status dan tanda tangan pada rincian di bawah ini.',
  }
}

export function detectScheduleConflict(params: {
  facilityId: number
  requesterOrgId: number
  date: string
  startTime: string
  endTime: string
  fixedSchedules: FixedSchedule[]
  requests: BorrowRequestItem[]
  organizations: OrganizationProfile[]
}): {
  blockedByBooking: BorrowRequestItem | null
  overlappingOwnerOrg: OrganizationProfile | null
  predictedWorkflow: 'inter_eskul' | 'mpk_borrow' | 'free_slot'
  noticeTone: 'rose' | 'blue' | 'amber' | 'emerald'
  noticeTitle: string
  noticeText: string
} {
  const {
    facilityId,
    requesterOrgId,
    date,
    startTime,
    endTime,
    fixedSchedules,
    requests,
    organizations,
  } = params

  const blocked =
    requests.find(
      (r) =>
        r.facilityId === facilityId &&
        r.date === date &&
        r.status === 'approved' &&
        r.startTime < endTime &&
        r.endTime > startTime
    ) || null

  if (blocked) {
    const holder = organizations.find((o) => o.id === blocked.requesterOrgId)?.name || 'Organisasi lain'
    return {
      blockedByBooking: blocked,
      overlappingOwnerOrg: null,
      predictedWorkflow: 'inter_eskul',
      noticeTone: 'rose',
      noticeTitle: `Bentrok dengan Peminjaman Resmi (${blocked.letterNumber || 'Disetujui'})`,
      noticeText: `Fasilitas sudah resmi digunakan oleh ${holder} pada jam ${formatTimeRange(blocked.startTime, blocked.endTime)}. Pilih tanggal atau jam lain agar tidak terjadi bentrok ganda.`,
    }
  }

  const weekday = getWeekdayFromDate(date)
  const requester = organizations.find((o) => o.id === requesterOrgId)
  const hitFixed = fixedSchedules.find(
    (fs) =>
      fs.facilityId === facilityId &&
      fs.day === weekday &&
      fs.organizationId !== requesterOrgId &&
      fs.startTime < endTime &&
      fs.endTime > startTime
  )

  if (hitFixed) {
    const ownerOrg = organizations.find((o) => o.id === hitFixed.organizationId) || null
    if (requester?.isMpk) {
      return {
        blockedByBooking: null,
        overlappingOwnerOrg: ownerOrg,
        predictedWorkflow: 'mpk_borrow',
        noticeTone: 'blue',
        noticeTitle: `Alur B · Mengambil Jadwal Tetap ${ownerOrg?.name || 'Ekskul'}`,
        noticeText: `Sebagai MPK, permohonan dikirim langsung ke ${ownerOrg?.name} tanpa tahap screening terpisah (total 2 blok tanda tangan: MPK + ${ownerOrg?.name}).`,
      }
    }
    return {
      blockedByBooking: null,
      overlappingOwnerOrg: ownerOrg,
      predictedWorkflow: 'inter_eskul',
      noticeTone: 'amber',
      noticeTitle: `Alur A · Bertepatan dengan Jadwal Rutin ${ownerOrg?.name || 'Ekskul'}`,
      noticeText: `Memerlukan 3 tanda tangan: Anda (${requester?.name}) → Pemeriksaan & TTD MPK → Persetujuan & TTD ${ownerOrg?.name} → Verifikasi Akhir MPK.`,
    }
  }

  return {
    blockedByBooking: null,
    overlappingOwnerOrg: null,
    predictedWorkflow: 'free_slot',
    noticeTone: 'emerald',
    noticeTitle: 'Slot Kosong (Tidak Ada Jadwal Rutin Lain)',
    noticeText: requester?.isMpk
      ? 'Slot kosong dapat langsung dicatat atas nama MPK (1 tanda tangan MPK) dan surat resmi terbit otomatis.'
      : 'Tidak bentrok dengan jadwal rutin ekskul lain. Pengajuan cukup melalui Pemeriksaan & TTD MPK (2 tanda tangan).',
  }
}

