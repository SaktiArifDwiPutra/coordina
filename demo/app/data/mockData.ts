import type {
  BorrowRequestItem,
  Facility,
  FixedSchedule,
  OrganizationProfile,
} from '~/types/coordina'

export function createSvgBadgeDataUrl(code: string, bg: string): string {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">
    <rect width="96" height="96" rx="18" fill="${bg}"/>
    <circle cx="48" cy="48" r="36" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="2"/>
    <text x="48" y="55" text-anchor="middle" font-family="sans-serif" font-size="24" font-weight="700" fill="#FFFFFF">${code}</text>
  </svg>`
  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`
}

export function createSvgSignatureDataUrl(stroke: string, variant = 1): string {
  const path =
    variant === 1
      ? 'M14 48 C 28 16, 40 52, 58 26 C 72 10, 86 48, 108 24 C 122 14, 134 40, 158 32'
      : variant === 2
        ? 'M16 42 C 30 22, 46 46, 62 20 C 78 36, 94 14, 114 38 C 128 24, 144 36, 162 26'
        : 'M12 40 C 32 14, 48 44, 68 24 C 84 12, 100 44, 122 22 C 138 30, 148 18, 164 34'
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="180" height="64" viewBox="0 0 180 64">
    <path d="${path}" fill="none" stroke="${stroke}" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M24 52 L148 50" fill="none" stroke="${stroke}" stroke-width="1.2" stroke-opacity="0.45" stroke-linecap="round"/>
  </svg>`
  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`
}

export const INITIAL_ORGANIZATIONS: OrganizationProfile[] = [
  {
    id: 1,
    name: 'MPK Komisi 2 SMKN 4',
    shortCode: 'MPK',
    isMpk: true,
    chairpersonName: 'Dimas Arya Pratama',
    chairpersonTitle: 'Ketua Komisi 2 MPK',
    chairpersonIdNumber: '242510012',
    advisorName: 'Dra. Sri Rahayu, M.Pd.',
    logoDataUrl: createSvgBadgeDataUrl('MPK', '#1E3A5F'),
    signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
    accountEmail: 'mpk@smkn4padalarang.demo',
    accountActive: true,
  },
  {
    id: 2,
    name: 'Ekskul Basket',
    shortCode: 'BSK',
    isMpk: false,
    chairpersonName: 'Naufal Ramadhan',
    chairpersonTitle: 'Ketua Ekskul Basket',
    chairpersonIdNumber: '242510104',
    advisorName: 'Hendra Wijaya, S.Pd.',
    logoDataUrl: createSvgBadgeDataUrl('BSK', '#B45309'),
    signatureDataUrl: createSvgSignatureDataUrl('#0F172A', 2),
    accountEmail: 'basket@smkn4padalarang.demo',
    accountActive: true,
  },
  {
    id: 3,
    name: 'Ekskul Futsal',
    shortCode: 'FTS',
    isMpk: false,
    chairpersonName: 'Raka Pratama',
    chairpersonTitle: 'Ketua Ekskul Futsal',
    chairpersonIdNumber: '242510088',
    advisorName: 'Hendra Wijaya, S.Pd.',
    logoDataUrl: createSvgBadgeDataUrl('FTS', '#15803D'),
    signatureDataUrl: createSvgSignatureDataUrl('#152943', 3),
    accountEmail: 'futsal@smkn4padalarang.demo',
    accountActive: true,
  },
  {
    id: 4,
    name: 'Paskibra SMKN 4',
    shortCode: 'PSK',
    isMpk: false,
    chairpersonName: 'Nadia Putri Lestari',
    chairpersonTitle: 'Komandan Paskibra',
    chairpersonIdNumber: '242510045',
    advisorName: 'Bambang Sutrisno, S.Pd.',
    logoDataUrl: createSvgBadgeDataUrl('PSK', '#BE123C'),
    signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 2),
    accountEmail: 'paskibra@smkn4padalarang.demo',
    accountActive: true,
  },
  {
    id: 5,
    name: 'Pramuka',
    shortCode: 'PRM',
    isMpk: false,
    chairpersonName: 'Bagas Wijaya',
    chairpersonTitle: 'Pradana Putra Pramuka',
    chairpersonIdNumber: '242510063',
    advisorName: 'Ratna Sari, M.Pd.',
    logoDataUrl: createSvgBadgeDataUrl('PRM', '#78350F'),
    signatureDataUrl: createSvgSignatureDataUrl('#0F172A', 1),
    accountEmail: 'pramuka@smkn4padalarang.demo',
    accountActive: true,
  },
  {
    id: 6,
    name: 'Ekskul Karate',
    shortCode: 'KRT',
    isMpk: false,
    chairpersonName: 'Fajar Maulana',
    chairpersonTitle: '',
    chairpersonIdNumber: '242510119',
    advisorName: 'Drs. Agus Salim',
    logoDataUrl: null,
    signatureDataUrl: null,
    accountEmail: 'karate@smkn4padalarang.demo',
    accountActive: true,
  },
]

export const INITIAL_FACILITIES: Facility[] = [
  {
    id: 1,
    name: 'Lapangan Utama',
    type: 'Outdoor',
    description: 'Lapangan basket dan futsal utama di area tengah sekolah.',
  },
  {
    id: 2,
    name: 'Aula Serbaguna',
    type: 'Indoor',
    description: 'Ruang pertemuan berkapasitas 250 siswa di gedung utama lantai 2.',
  },
  {
    id: 3,
    name: 'Lapangan Upacara',
    type: 'Outdoor',
    description: 'Halaman depan untuk baris-berbaris, kepramukaan, dan apel besar.',
  },
  {
    id: 4,
    name: 'Studio Musik',
    type: 'Indoor',
    description: 'Ruang akustik latihan band dan paduan suara.',
  },
]

export const INITIAL_FIXED_SCHEDULES: FixedSchedule[] = [
  { id: 1, facilityId: 1, organizationId: 3, day: 'Monday', startTime: '15:30', endTime: '17:30' },
  { id: 2, facilityId: 2, organizationId: 6, day: 'Tuesday', startTime: '15:30', endTime: '17:00' },
  { id: 3, facilityId: 2, organizationId: 5, day: 'Wednesday', startTime: '15:00', endTime: '17:30' },
  { id: 4, facilityId: 1, organizationId: 2, day: 'Thursday', startTime: '15:30', endTime: '17:30' },
  { id: 5, facilityId: 1, organizationId: 2, day: 'Friday', startTime: '14:00', endTime: '17:30' },
  { id: 6, facilityId: 3, organizationId: 5, day: 'Saturday', startTime: '08:00', endTime: '11:30' },
  { id: 7, facilityId: 3, organizationId: 4, day: 'Saturday', startTime: '13:00', endTime: '16:00' },
]

function isoOffsetDate(daysAhead: number): string {
  const base = new Date(2026, 10, 26)
  base.setDate(base.getDate() + daysAhead)
  const y = base.getFullYear()
  const m = String(base.getMonth() + 1).padStart(2, '0')
  const d = String(base.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export const INITIAL_REQUESTS: BorrowRequestItem[] = [
  {
    id: 101,
    workflowType: 'inter_eskul',
    facilityId: 1,
    requesterOrgId: 3, // Futsal
    ownerOrgId: 2, // Basket (Friday fixed schedule)
    date: '2026-11-27', // Friday
    startTime: '15:30',
    endTime: '17:30',
    reason: 'Latihan tanding persahabatan sebelum turnamen futsal antar-SMK se-Bandung Barat.',
    status: 'pending_mpk_screening',
    rejectionReason: null,
    letterNumber: null,
    letterDate: null,
    finalized: false,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'requester_eskul',
        roleLabel: 'Ketua Ekskul Pemohon',
        signerName: 'Raka Pratama',
        signerTitle: 'Ketua Ekskul Futsal',
        organizationName: 'Ekskul Futsal',
        signed: true,
        signedAt: '2026-11-25 14:20',
        signatureDataUrl: createSvgSignatureDataUrl('#152943', 3),
      },
      {
        stageOrder: 2,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK Komisi 2',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: false,
        signedAt: null,
        signatureDataUrl: null,
      },
      {
        stageOrder: 3,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Naufal Ramadhan',
        signerTitle: 'Ketua Ekskul Basket',
        organizationName: 'Ekskul Basket',
        signed: false,
        signedAt: null,
        signatureDataUrl: null,
      },
    ],
    history: [
      {
        id: 1,
        actor: 'Ekskul Futsal',
        roleText: 'Pemohon',
        summary: 'Mengajukan peminjaman jadwal Ekskul Basket & membubuhkan TTD Pemohon (Simulasi Demo).',
        note: 'Latihan tanding persahabatan sebelum turnamen futsal antar-SMK se-Bandung Barat.',
        timestamp: '25 Nov 2026, 14:20',
      },
    ],
  },
  {
    id: 102,
    workflowType: 'inter_eskul',
    facilityId: 2,
    requesterOrgId: 4, // Paskibra
    ownerOrgId: 5, // Pramuka (Wednesday fixed schedule)
    date: '2026-12-02', // Wednesday
    startTime: '15:00',
    endTime: '17:00',
    reason: 'Simulasi teknis upacara dan pengarahan calon pengurus angkatan XIV.',
    status: 'pending_owner',
    rejectionReason: null,
    letterNumber: null,
    letterDate: null,
    finalized: false,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'requester_eskul',
        roleLabel: 'Ketua Ekskul Pemohon',
        signerName: 'Nadia Putri Lestari',
        signerTitle: 'Komandan Paskibra',
        organizationName: 'Paskibra SMKN 4',
        signed: true,
        signedAt: '2026-11-25 10:15',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 2),
      },
      {
        stageOrder: 2,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK Komisi 2',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: true,
        signedAt: '2026-11-25 13:40',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
      },
      {
        stageOrder: 3,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Bagas Wijaya',
        signerTitle: 'Pradana Putra Pramuka',
        organizationName: 'Pramuka',
        signed: false,
        signedAt: null,
        signatureDataUrl: null,
      },
    ],
    history: [
      {
        id: 1,
        actor: 'Paskibra SMKN 4',
        roleText: 'Pemohon',
        summary: 'Mengirim permohonan peminjaman jadwal Pramuka + TTD Pemohon.',
        timestamp: '25 Nov 2026, 10:15',
      },
      {
        id: 2,
        actor: 'MPK Komisi 2',
        roleText: 'Pemeriksa Awal',
        summary: 'Lolos pemeriksaan MPK, membubuhkan TTD Saksi MPK, dan meneruskan ke Pramuka.',
        timestamp: '25 Nov 2026, 13:40',
      },
    ],
  },
  {
    id: 103,
    workflowType: 'inter_eskul',
    facilityId: 3,
    requesterOrgId: 3, // Futsal
    ownerOrgId: 5, // Pramuka (Saturday fixed schedule)
    date: '2026-11-28', // Saturday
    startTime: '08:30',
    endTime: '11:00',
    reason: 'Pembukaan liga futsal antarkelas sekaligus pengecekan barisan peserta.',
    status: 'owner_approved',
    rejectionReason: null,
    letterNumber: null,
    letterDate: null,
    finalized: false,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'requester_eskul',
        roleLabel: 'Ketua Ekskul Pemohon',
        signerName: 'Raka Pratama',
        signerTitle: 'Ketua Ekskul Futsal',
        organizationName: 'Ekskul Futsal',
        signed: true,
        signedAt: '2026-11-24 09:10',
        signatureDataUrl: createSvgSignatureDataUrl('#152943', 3),
      },
      {
        stageOrder: 2,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK Komisi 2',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: true,
        signedAt: '2026-11-24 12:05',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
      },
      {
        stageOrder: 3,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Bagas Wijaya',
        signerTitle: 'Pradana Putra Pramuka',
        organizationName: 'Pramuka',
        signed: true,
        signedAt: '2026-11-25 16:00',
        signatureDataUrl: createSvgSignatureDataUrl('#0F172A', 1),
      },
    ],
    history: [
      {
        id: 1,
        actor: 'Ekskul Futsal',
        roleText: 'Pemohon',
        summary: 'Mengirim pengajuan + TTD Pemohon.',
        timestamp: '24 Nov 2026, 09:10',
      },
      {
        id: 2,
        actor: 'MPK Komisi 2',
        roleText: 'Pemeriksa Awal',
        summary: 'Lolos pemeriksaan MPK & TTD Saksi MPK dibubuhkan.',
        timestamp: '24 Nov 2026, 12:05',
      },
      {
        id: 3,
        actor: 'Pramuka',
        roleText: 'Pemilik Jadwal',
        summary: 'Menyetujui peminjaman jadwal & membubuhkan TTD Pemilik (siap verifikasi akhir MPK).',
        timestamp: '25 Nov 2026, 16:00',
      },
    ],
  },
  {
    id: 104,
    workflowType: 'mpk_borrow',
    facilityId: 1,
    requesterOrgId: 1, // MPK (Workflow B - direct to Basket, 2 signatures only!)
    ownerOrgId: 2, // Basket (Friday fixed schedule)
    date: '2026-12-04', // Friday
    startTime: '14:00',
    endTime: '16:30',
    reason: 'Deklarasi Pemilihan Ketua OSIS & MPK Periode 2026/2027 (Agenda Resmi Sekolah).',
    status: 'pending_owner',
    rejectionReason: null,
    letterNumber: null,
    letterDate: null,
    finalized: false,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK (Pemohon)',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: true,
        signedAt: '2026-11-26 08:30',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
      },
      {
        stageOrder: 2,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Naufal Ramadhan',
        signerTitle: 'Ketua Ekskul Basket',
        organizationName: 'Ekskul Basket',
        signed: false,
        signedAt: null,
        signatureDataUrl: null,
      },
    ],
    history: [
      {
        id: 1,
        actor: 'MPK Komisi 2',
        roleText: 'Pemohon Resmi (Alur B)',
        summary: 'Mengajukan peminjaman langsung ke Ekskul Basket dengan TTD MPK (tanpa tahap screening terpisah).',
        note: 'Deklarasi Pemilihan Ketua OSIS & MPK Periode 2026/2027 (Agenda Resmi Sekolah).',
        timestamp: '26 Nov 2026, 08:30',
      },
    ],
  },
  {
    id: 105,
    workflowType: 'inter_eskul',
    facilityId: 1,
    requesterOrgId: 4, // Paskibra
    ownerOrgId: 2, // Basket
    date: '2026-11-19', // Archived approved Workflow A (3 sigs)
    startTime: '15:30',
    endTime: '17:30',
    reason: 'Latihan gabungan formasi pengibaran bendera Hari Guru Nasional.',
    status: 'approved',
    rejectionReason: null,
    letterNumber: '018/MPK-K2/XI/2026',
    letterDate: '2026-11-18',
    finalized: true,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'requester_eskul',
        roleLabel: 'Ketua Ekskul Pemohon',
        signerName: 'Nadia Putri Lestari',
        signerTitle: 'Komandan Paskibra',
        organizationName: 'Paskibra SMKN 4',
        signed: true,
        signedAt: '2026-11-17 09:00',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 2),
      },
      {
        stageOrder: 2,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK Komisi 2',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: true,
        signedAt: '2026-11-17 13:15',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
      },
      {
        stageOrder: 3,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Naufal Ramadhan',
        signerTitle: 'Ketua Ekskul Basket',
        organizationName: 'Ekskul Basket',
        signed: true,
        signedAt: '2026-11-18 08:40',
        signatureDataUrl: createSvgSignatureDataUrl('#0F172A', 2),
      },
    ],
    history: [
      {
        id: 1,
        actor: 'Paskibra SMKN 4',
        roleText: 'Pemohon',
        summary: 'Mengirim pengajuan + TTD Pemohon.',
        timestamp: '17 Nov 2026, 09:00',
      },
      {
        id: 2,
        actor: 'MPK Komisi 2',
        roleText: 'Pemeriksa Awal',
        summary: 'Lolos pemeriksaan MPK & TTD Saksi dibubuhkan.',
        timestamp: '17 Nov 2026, 13:15',
      },
      {
        id: 3,
        actor: 'Ekskul Basket',
        roleText: 'Pemilik Jadwal',
        summary: 'Menyetujui peminjaman & membubuhkan TTD Pemilik.',
        timestamp: '18 Nov 2026, 08:40',
      },
      {
        id: 4,
        actor: 'MPK Komisi 2',
        roleText: 'Verifikator Administratif',
        summary: 'Memverifikasi kelengkapan berkas dan menerbitkan surat resmi No. 018/MPK-K2/XI/2026.',
        timestamp: '18 Nov 2026, 11:00',
      },
    ],
  },
  {
    id: 106,
    workflowType: 'mpk_borrow',
    facilityId: 2,
    requesterOrgId: 1, // MPK (Workflow B - archived, 2 sigs only)
    ownerOrgId: 5, // Pramuka
    date: '2026-11-11',
    startTime: '15:00',
    endTime: '17:00',
    reason: 'Rapat koordinasi tata tertib seluruh ketua ekstrakurikuler semester ganjil.',
    status: 'approved',
    rejectionReason: null,
    letterNumber: '017/MPK-K2/XI/2026',
    letterDate: '2026-11-10',
    finalized: true,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK (Pemohon)',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: true,
        signedAt: '2026-11-09 10:00',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
      },
      {
        stageOrder: 2,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Bagas Wijaya',
        signerTitle: 'Pradana Putra Pramuka',
        organizationName: 'Pramuka',
        signed: true,
        signedAt: '2026-11-10 09:20',
        signatureDataUrl: createSvgSignatureDataUrl('#0F172A', 1),
      },
    ],
    history: [
      {
        id: 1,
        actor: 'MPK Komisi 2',
        roleText: 'Pemohon Resmi (Alur B)',
        summary: 'Mengajukan peminjaman langsung ke Pramuka dengan TTD MPK.',
        timestamp: '09 Nov 2026, 10:00',
      },
      {
        id: 2,
        actor: 'Pramuka',
        roleText: 'Pemilik Jadwal',
        summary: 'Menyetujui peminjaman & membubuhkan TTD Pemilik.',
        timestamp: '10 Nov 2026, 09:20',
      },
      {
        id: 3,
        actor: 'MPK Komisi 2',
        roleText: 'Pencatatan Administratif',
        summary: 'Mencatat hasil persetujuan pemilik & mengarsipkan surat resmi No. 017/MPK-K2/XI/2026.',
        timestamp: '10 Nov 2026, 10:15',
      },
    ],
  },
  {
    id: 107,
    workflowType: 'inter_eskul',
    facilityId: 1,
    requesterOrgId: 5, // Pramuka
    ownerOrgId: 3, // Futsal
    date: isoOffsetDate(12),
    startTime: '15:30',
    endTime: '17:30',
    reason: 'Pengambilan foto dokumentasi kegiatan di luar ruangan.',
    status: 'rejected',
    rejectionReason: 'Ekskul Futsal sedang menjalani uji coba tanding persiapan final pada jadwal tersebut sehingga lapangan tidak dapat dipinjamkan.',
    letterNumber: null,
    letterDate: null,
    finalized: true,
    signatures: [
      {
        stageOrder: 1,
        signerRole: 'requester_eskul',
        roleLabel: 'Ketua Ekskul Pemohon',
        signerName: 'Bagas Wijaya',
        signerTitle: 'Pradana Putra Pramuka',
        organizationName: 'Pramuka',
        signed: true,
        signedAt: '2026-11-23 11:00',
        signatureDataUrl: createSvgSignatureDataUrl('#0F172A', 1),
      },
      {
        stageOrder: 2,
        signerRole: 'mpk',
        roleLabel: 'Perwakilan MPK Komisi 2',
        signerName: 'Dimas Arya Pratama',
        signerTitle: 'Ketua Komisi 2 MPK',
        organizationName: 'MPK Komisi 2 SMKN 4',
        signed: true,
        signedAt: '2026-11-23 14:20',
        signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 1),
      },
      {
        stageOrder: 3,
        signerRole: 'owner_eskul',
        roleLabel: 'Ketua Ekskul Pemilik Jadwal',
        signerName: 'Raka Pratama',
        signerTitle: 'Ketua Ekskul Futsal',
        organizationName: 'Ekskul Futsal',
        signed: false,
        signedAt: null,
        signatureDataUrl: null,
      },
    ],
    history: [
      {
        id: 1,
        actor: 'Pramuka',
        roleText: 'Pemohon',
        summary: 'Mengirim pengajuan peminjaman jadwal Futsal.',
        timestamp: '23 Nov 2026, 11:00',
      },
      {
        id: 2,
        actor: 'MPK Komisi 2',
        roleText: 'Pemeriksa Awal',
        summary: 'Lolos pemeriksaan MPK & diteruskan ke Ekskul Futsal.',
        timestamp: '23 Nov 2026, 14:20',
      },
      {
        id: 3,
        actor: 'Ekskul Futsal',
        roleText: 'Pemilik Jadwal',
        summary: 'Menolak peminjaman dengan alasan tertulis (surat izin tidak diterbitkan).',
        note: 'Ekskul Futsal sedang menjalani uji coba tanding persiapan final pada jadwal tersebut sehingga lapangan tidak dapat dipinjamkan.',
        timestamp: '24 Nov 2026, 09:00',
      },
    ],
  },
]

