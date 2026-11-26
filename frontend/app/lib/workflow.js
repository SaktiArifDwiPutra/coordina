export function requiredSignatureCount(req) {
  if (!req) return 3
  if (req.workflow_type === 'mpk_borrow') return 2
  if (req.workflow_type === 'free_slot') {
    return req.organization?.is_mpk ? 1 : 2
  }
  return 3
}

export function activeSignatures(req) {
  return req?.document?.active_signatures ?? req?.document?.signatures?.filter((s) => s.is_valid) ?? []
}

export function hasSignatureStage(req, stageOrder) {
  return activeSignatures(req).some((s) => Number(s.stage_order) === Number(stageOrder))
}

export function workflowTypeLabel(req) {
  if (!req) return ''
  if (req.workflow_type === 'mpk_borrow') return 'Peminjaman Resmi MPK'
  if (req.workflow_type === 'free_slot') return 'Peminjaman Slot Kosong'
  return 'Peminjaman Antar-Ekskul'
}

export function formatDateId(dateString) {
  if (!dateString || !String(dateString).includes('-')) return dateString || '-'
  const [y, m, d] = String(dateString).split('-').map(Number)
  const dt = new Date(y, (m || 1) - 1, d || 1)
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(dt)
}

export function formatTimeRange(startTime, endTime) {
  const s = String(startTime || '').substring(0, 5)
  const e = String(endTime || '').substring(0, 5)
  if (!s || !e) return '-'
  return `${s} – ${e} WIB`
}

export function latestRevisionNote(req) {
  const revisions = req?.document?.revisions || []
  return revisions.length > 0 ? revisions[revisions.length - 1].reason : null
}

export function getRequestStatusBadge(req) {
  if (!req) return { label: '-', tone: 'slate', icon: 'clock' }
  const docStatus = req.document?.document_status

  if (req.status === 'rejected') {
    return { label: 'Ditolak', tone: 'rose', icon: 'x' }
  }
  if (docStatus === 'needs_correction') {
    return { label: 'Perlu Revisi Pemohon', tone: 'amber', icon: 'alert' }
  }
  if (docStatus === 'pending_pdf_generation') {
    return { label: 'Disetujui · Memproses PDF Resmi', tone: 'blue', icon: 'clock' }
  }
  if (docStatus === 'pdf_generation_failed') {
    return { label: 'Disetujui · PDF Perlu Dibuat Ulang', tone: 'amber', icon: 'alert' }
  }
  if (req.status === 'approved' && docStatus === 'finalized' && req.document?.final_pdf_path) {
    return { label: 'Disetujui · Surat Resmi Siap', tone: 'emerald', icon: 'check' }
  }
  if (req.status === 'approved') {
    return { label: 'Disetujui · Menyiapkan Surat', tone: 'blue', icon: 'clock' }
  }

  if (req.status === 'pending_mpk_screening') {
    if (!hasSignatureStage(req, 1)) {
      return { label: 'Menunggu TTD Pemohon (Tahap 1)', tone: 'amber', icon: 'alert' }
    }
    return { label: 'Menunggu Screening MPK', tone: 'amber', icon: 'clock' }
  }

  if (req.status === 'pending_owner') {
    if (req.workflow_type === 'inter_eskul' && docStatus === 'waiting_mpk_signature') {
      return { label: 'Lolos Screening · TTD MPK Tertunda', tone: 'amber', icon: 'alert' }
    }
    if (req.workflow_type === 'mpk_borrow' && !hasSignatureStage(req, 1)) {
      return { label: 'Menunggu TTD Pengajuan MPK', tone: 'amber', icon: 'alert' }
    }
    return { label: 'Menunggu Keputusan Pemilik Jadwal', tone: 'blue', icon: 'clock' }
  }

  if (req.status === 'owner_approved') {
    if (docStatus === 'waiting_owner_signature') {
      return { label: 'Disetujui Pemilik · TTD Pemilik Belum Masuk', tone: 'amber', icon: 'alert' }
    }
    return {
      label: req.workflow_type === 'mpk_borrow'
        ? 'Disetujui Pemilik · Siap Dicatat MPK'
        : 'Disetujui Pemilik · Siap Verifikasi MPK',
      tone: 'emerald',
      icon: 'check',
    }
  }

  if (req.status === 'owner_rejected') {
    return { label: 'Ditolak Pemilik · Menunggu Pencatatan MPK', tone: 'rose', icon: 'x' }
  }

  return { label: req.status, tone: 'slate', icon: 'clock' }
}

/**
 * Builds the exact ordered workflow stages without collapsing or oversimplifying
 * differences between Workflow A (inter_eskul, 4 stages / 3 sigs),
 * Workflow B (mpk_borrow, 3 stages / 2 sigs), and Free Slot (2 or 3 stages).
 */
export function buildWorkflowSteps(req) {
  if (!req) return { summary: '', steps: [] }

  const requesterName = req.organization?.name || 'Pemohon'
  const ownerName = req.owner_organization?.name || 'Pemilik Jadwal'
  const docStatus = req.document?.document_status
  const status = req.status
  const sig1 = hasSignatureStage(req, 1)
  const sig2 = hasSignatureStage(req, 2)
  const sig3 = hasSignatureStage(req, 3)

  const finalStepState = () => {
    if (status === 'rejected') return 'rejected'
    if (docStatus === 'needs_correction') return 'correction'
    if (docStatus === 'pdf_generation_failed') return 'failed'
    if (status === 'approved' && docStatus === 'finalized') return 'done'
    if (status === 'approved' && docStatus === 'pending_pdf_generation') return 'current'
    if (['owner_approved', 'owner_rejected'].includes(status)) return 'current'
    return 'pending'
  }

  // Case 1: Free Slot submitted by MPK (1 signature, 2 stages)
  if (req.workflow_type === 'free_slot' && req.organization?.is_mpk) {
    const steps = [
      {
        title: 'Pengajuan & TTD Resmi MPK',
        subtitle: sig1 ? `Ditandatangani oleh ${requesterName}` : 'Menunggu TTD MPK Komisi 2',
        state: sig1 ? 'done' : 'current',
      },
      {
        title: 'Nomor Surat & Penerbitan PDF',
        subtitle: status === 'approved' && docStatus === 'finalized'
          ? `Terbit resmi (${req.letter_number || 'PDF Siap'})`
          : docStatus === 'pdf_generation_failed'
            ? 'Konversi PDF gagal — perlu dibuat ulang oleh MPK'
            : 'Memproses penomoran & PDF surat izin',
        state: finalStepState(),
      },
    ]
    const currentNum = status === 'approved' && docStatus === 'finalized' ? 2 : (sig1 ? 2 : 1)
    return { summary: `Tahap ${currentNum} dari 2 · Slot Kosong MPK (1 TTD)`, steps }
  }

  // Case 2: Free Slot submitted by Eskul (2 signatures, 3 stages: Pemohon -> MPK -> Terbit PDF)
  if (req.workflow_type === 'free_slot') {
    const mpkApproved = status === 'approved'
    const mpkRejected = status === 'rejected'
    const steps = [
      {
        title: `Pengajuan & TTD Pemohon (${requesterName})`,
        subtitle: sig1 ? 'Tanda tangan tahap 1 tercatat' : 'Tanda tangan tahap 1 belum dibubuhkan',
        state: sig1 ? 'done' : (mpkRejected ? 'done' : 'current'),
      },
      {
        title: 'Pemeriksaan & TTD MPK (Slot Kosong)',
        subtitle: mpkRejected
          ? 'Ditolak saat pemeriksaan MPK'
          : mpkApproved
            ? (sig2 ? 'Disetujui & ditandatangani MPK' : 'Disetujui MPK')
            : (status === 'pending_mpk_screening' ? 'Menunggu pemeriksaan & TTD MPK Komisi 2' : 'Menunggu'),
        state: mpkRejected ? 'rejected' : mpkApproved ? 'done' : (status === 'pending_mpk_screening' ? 'current' : 'pending'),
      },
      {
        title: 'Nomor Surat & Penerbitan PDF',
        subtitle: mpkRejected
          ? 'Tidak ada surat izin terbit karena pengajuan ditolak'
          : status === 'approved' && docStatus === 'finalized'
            ? `Resmi terbit (${req.letter_number || 'PDF Siap'})`
            : docStatus === 'pdf_generation_failed'
              ? 'Pembuatan PDF tertunda — silakan generate ulang'
              : 'Menunggu persetujuan akhir',
        state: finalStepState(),
      },
    ]
    const currentNum = mpkRejected ? 2 : status === 'approved' ? 3 : (sig1 ? 2 : 1)
    return { summary: `Tahap ${currentNum} dari 3 · Slot Kosong (2 TTD)`, steps }
  }

  // Case 3: Workflow B — MPK Borrowing Owner Schedule (2 signatures, 3 stages, NO MPK screening!)
  if (req.workflow_type === 'mpk_borrow') {
    const steps = [
      {
        title: 'Pengajuan Resmi & TTD MPK (Tahap 1)',
        subtitle: sig1 ? 'Ditandatangani MPK Komisi 2 · langsung diteruskan tanpa screening' : 'Menunggu TTD pengajuan MPK',
        state: sig1 ? 'done' : 'current',
      },
      {
        title: `Keputusan & TTD Pemilik Jadwal (${ownerName})`,
        subtitle: status === 'owner_approved' || status === 'approved'
          ? (sig2 ? `Disetujui & ditandatangani ${ownerName}` : `Disetujui ${ownerName} — menunggu TTD pemilik`)
          : status === 'owner_rejected' || (status === 'rejected' && req.rejection_reason)
            ? `Ditolak oleh ${ownerName}`
            : `Menunggu persetujuan ${ownerName}`,
        state: status === 'owner_rejected' || (status === 'rejected' && req.rejection_reason)
          ? 'rejected'
          : (status === 'approved' || (status === 'owner_approved' && docStatus !== 'waiting_owner_signature'))
            ? 'done'
            : (status === 'pending_owner' || docStatus === 'waiting_owner_signature')
              ? 'current'
              : 'pending',
      },
      {
        title: 'Pencatatan Administratif MPK & PDF Resmi',
        subtitle: status === 'rejected'
          ? 'Penolakan dicatat resmi · tidak ada PDF izin terbit'
          : docStatus === 'needs_correction'
            ? `Menunggu revisi pemohon (v${req.document?.current_revision || 1})`
            : status === 'approved' && docStatus === 'finalized'
              ? `Terbit resmi (${req.letter_number || 'PDF Siap'})`
              : docStatus === 'pdf_generation_failed'
                ? 'Konversi PDF gagal — dapat dibuat ulang oleh MPK'
                : status === 'owner_rejected'
                  ? 'Siap dicatat & disampaikan penolakannya oleh MPK'
                  : status === 'owner_approved'
                    ? 'Siap dicatat & diterbitkan PDF oleh MPK (tanpa persetujuan ulang)'
                    : 'Menunggu keputusan pemilik jadwal',
        state: finalStepState(),
      },
    ]

    let currentNum = 1
    if (status === 'approved' || status === 'rejected' || ['owner_approved', 'owner_rejected'].includes(status)) currentNum = 3
    else if (status === 'pending_owner') currentNum = sig1 ? 2 : 1

    return { summary: `Tahap ${currentNum} dari 3 · Alur Resmi MPK (2 TTD, Tanpa Screening)`, steps }
  }

  // Case 4: Workflow A — Inter-Eskul Borrowing (3 signatures, 4 stages)
  const screenedOut = status === 'rejected' && !req.owner_decided_at
  const ownerRejected = status === 'owner_rejected' || (status === 'rejected' && Boolean(req.owner_decided_at))
  const mpkPassed = ['pending_owner', 'owner_approved', 'owner_rejected', 'approved'].includes(status) || ownerRejected

  const steps = [
    {
      title: `Pengajuan & TTD Pemohon (${requesterName})`,
      subtitle: sig1 ? 'Tanda tangan tahap 1 (pemohon) tercatat' : 'Tanda tangan pemohon tahap 1 belum dibubuhkan',
      state: sig1 ? 'done' : 'current',
    },
    {
      title: 'Screening & TTD Saksi MPK Komisi 2',
      subtitle: screenedOut
        ? 'Ditolak saat screening awal MPK'
        : mpkPassed
          ? (sig2 ? 'Lolos screening & ditandatangani MPK' : 'Lolos screening · menunggu TTD MPK tahap 2')
          : 'Menunggu pemeriksaan kelayakan oleh MPK',
      state: screenedOut
        ? 'rejected'
        : (mpkPassed && docStatus !== 'waiting_mpk_signature')
          ? 'done'
          : (status === 'pending_mpk_screening' || docStatus === 'waiting_mpk_signature')
            ? 'current'
            : 'pending',
    },
    {
      title: `Keputusan & TTD Pemilik Jadwal (${ownerName})`,
      subtitle: ownerRejected
        ? `Ditolak oleh ${ownerName}`
        : status === 'owner_approved' || status === 'approved'
          ? (sig3 ? `Disetujui & ditandatangani ${ownerName}` : `Disetujui ${ownerName} — menunggu TTD pemilik`)
          : status === 'pending_owner'
            ? `Menunggu keputusan ${ownerName}`
            : 'Menunggu tahap screening selesai',
      state: ownerRejected
        ? 'rejected'
        : (status === 'approved' || (status === 'owner_approved' && docStatus !== 'waiting_owner_signature'))
          ? 'done'
          : (status === 'pending_owner' || docStatus === 'waiting_owner_signature')
            ? 'current'
            : 'pending',
    },
    {
      title: 'Verifikasi Administratif MPK & PDF Resmi',
      subtitle: status === 'rejected'
        ? 'Berhenti karena ditolak · riwayat tetap tersimpan'
        : docStatus === 'needs_correction'
          ? `Perlu revisi pemohon (v${req.document?.current_revision || 1})`
          : status === 'approved' && docStatus === 'finalized'
            ? `Terverifikasi & terbit (${req.letter_number || 'PDF Siap'})`
            : docStatus === 'pdf_generation_failed'
              ? 'PDF gagal dibuat — klik Generate Ulang PDF'
              : status === 'owner_rejected'
                ? 'Siap dicatat penolakannya oleh MPK'
                : status === 'owner_approved'
                  ? 'Kelengkapan berkas & 3 TTD siap diverifikasi MPK'
                  : 'Menunggu keputusan pemilik jadwal',
      state: finalStepState(),
    },
  ]

  let currentNum = 1
  if (screenedOut) currentNum = 2
  else if (status === 'pending_mpk_screening') currentNum = sig1 ? 2 : 1
  else if (status === 'pending_owner') currentNum = docStatus === 'waiting_mpk_signature' ? 2 : 3
  else if (['owner_approved', 'owner_rejected', 'approved', 'rejected'].includes(status)) currentNum = 4

  return { summary: `Tahap ${currentNum} dari 4 · Antar-Ekskul (3 TTD)`, steps }
}

export function getActionGuidance(req, isMpk) {
  if (!req) return null
  const docStatus = req.document?.document_status
  const status = req.status
  const sig1 = hasSignatureStage(req, 1)

  if (status === 'rejected') {
    return {
      tone: 'rose',
      title: 'Permohonan Ditutup (Ditolak)',
      description: req.rejection_reason
        ? `Alasan penolakan: "${req.rejection_reason}". Tidak ada surat izin resmi yang diterbitkan.`
        : 'Permohonan ini telah ditolak dan dicatat di riwayat.',
    }
  }

  if (docStatus === 'needs_correction') {
    const note = latestRevisionNote(req) || 'Periksa kembali jadwal dan alasan peminjaman.'
    return {
      tone: 'amber',
      title: isMpk ? 'Menunggu Revisi dari Pemohon' : 'Berkas Perlu Diperbaiki & Diajukan Ulang',
      description: isMpk
        ? `MPK telah meminta revisi dengan catatan: "${note}". Menunggu pemohon mengirim perbaikan.`
        : `Catatan dari MPK: "${note}". Klik tombol Ajukan Revisi untuk memperbaiki data dan memperbarui tanda tangan.`,
    }
  }

  if (status === 'approved' && docStatus === 'finalized' && req.document?.final_pdf_path) {
    return {
      tone: 'emerald',
      title: `Surat Resmi Siap Dicetak (${req.letter_number || 'PDF Resmi'})`,
      description: 'Seluruh persetujuan dan verifikasi selesai. Anda dapat membuka pratinjau cetak atau mengunduh salinan resmi PDF/DOCX.',
    }
  }

  if (status === 'approved' && docStatus === 'pdf_generation_failed') {
    return {
      tone: 'amber',
      title: 'Pembuatan File PDF Gagal',
      description: isMpk
        ? `Konversi PDF mengalami kendala (${req.document?.pdf_generation_error || 'konverter belum siap'}). Gunakan tombol Generate Ulang PDF.`
        : `Persetujuan sudah final, namun file PDF tertunda (${req.document?.pdf_generation_error || 'konverter belum siap'}). Hubungi MPK untuk memproses ulang PDF.`,
    }
  }

  if (status === 'pending_mpk_screening') {
    if (isMpk) {
      return {
        tone: 'blue',
        title: req.owner_organization_id ? 'Periksa Kelayakan Peminjaman (Screening Awal)' : 'Periksa & Terbitkan Izin Slot Kosong',
        description: req.owner_organization_id
          ? `Periksa alasan peminjaman dari ${req.organization?.name}. Jika wajar, klik Lolos Screening & TTD agar diteruskan ke ${req.owner_organization?.name}.`
          : 'Slot ini tidak bentrok dengan jadwal tetap ekskul lain. Anda dapat langsung menyetujui & menandatangani surat izin slot kosong ini.',
      }
    }
    return {
      tone: 'amber',
      title: !sig1 ? 'Lengkapi Tanda Tangan Tahap 1' : 'Menunggu Pemeriksaan MPK Komisi 2',
      description: !sig1
        ? 'Pengajuan sudah tercatat, namun tanda tangan ketua organisasi Anda belum dibubuhkan. Klik Bubuhkan TTD Tahap 1.'
        : 'Permohonan Anda sedang menunggu pemeriksaan awal oleh MPK Komisi 2.',
    }
  }

  if (status === 'pending_owner') {
    if (isMpk && req.workflow_type === 'inter_eskul' && docStatus === 'waiting_mpk_signature') {
      return {
        tone: 'amber',
        title: 'Bubuhkan Tanda Tangan Saksi MPK',
        description: 'Screening sudah diterima namun tanda tangan MPK belum terpasang. Klik Bubuhkan TTD MPK agar pemilik jadwal dapat melanjutkan.',
      }
    }
    if (!isMpk) {
      return {
        tone: 'blue',
        title: 'Permohonan Izin Masuk untuk Jadwal Tetap Anda',
        description: `${req.organization?.name} meminta izin memakai jadwal Anda di ${req.facility?.name}. Anda berhak penuh mengizinkan beserta TTD, atau menolak dengan alasan tertulis.`,
      }
    }
    return {
      tone: 'blue',
      title: `Menunggu Jawaban ${req.owner_organization?.name || 'Pemilik Jadwal'}`,
      description: 'Surat resmi telah dikirim kepada pemilik jadwal. Keputusan kini berada di tangan pemilik jadwal.',
    }
  }

  if (status === 'owner_approved') {
    if (docStatus === 'waiting_owner_signature' && !isMpk) {
      return {
        tone: 'amber',
        title: 'Lengkapi Tanda Tangan Pemilik Jadwal',
        description: 'Anda telah menyetujui peminjaman ini. Klik Bubuhkan TTD Pemilik agar MPK dapat menerbitkan PDF resmi.',
      }
    }
    if (isMpk) {
      return {
        tone: 'emerald',
        title: req.workflow_type === 'mpk_borrow' ? 'Siap Dicatat & Diterbitkan PDF' : 'Siap Verifikasi Administratif & Terbitkan PDF',
        description: req.workflow_type === 'mpk_borrow'
          ? 'Pemilik jadwal telah menyetujui dan menandatangani. Catat persetujuan ini untuk menerbitkan nomor surat dan PDF resmi.'
          : 'Pemilik jadwal telah menyetujui. Periksa kelengkapan berkas & 3 TTD, lalu terbitkan PDF resmi atau minta revisi jika ada kekurangan dokumen.',
      }
    }
  }

  if (status === 'owner_rejected') {
    return {
      tone: 'rose',
      title: isMpk ? 'Catat & Sampaikan Penolakan Pemilik' : 'Ditolak oleh Pemilik Jadwal',
      description: `Pemilik jadwal menolak dengan alasan: "${req.rejection_reason || '-'}".`,
    }
  }

  return null
}

