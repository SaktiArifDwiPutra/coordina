import { defineStore } from 'pinia'
import {
  INITIAL_FACILITIES,
  INITIAL_FIXED_SCHEDULES,
  INITIAL_ORGANIZATIONS,
  INITIAL_REQUESTS,
} from '~/data/mockData'
import { detectScheduleConflict, isOrgProfileComplete } from '~/lib/workflow'
import type {
  BorrowRequestItem,
  DemoRole,
  Facility,
  FixedSchedule,
  OrganizationProfile,
  WeekdayKey,
} from '~/types/coordina'

const STORAGE_KEY = 'coordina_demo_v2_state_1'

function cloneDeep<T>(obj: T): T {
  return JSON.parse(JSON.stringify(obj)) as T
}

function nowFormatted(): string {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date())
}

export const useDemoStore = defineStore('coordinaDemo', {
  state: () => ({
    role: 'mpk' as DemoRole,
    activeEskulOrgId: 2, // Ekskul Basket by default so Workflow A & B decisions are immediately demoable
    organizations: cloneDeep(INITIAL_ORGANIZATIONS) as OrganizationProfile[],
    facilities: cloneDeep(INITIAL_FACILITIES) as Facility[],
    fixedSchedules: cloneDeep(INITIAL_FIXED_SCHEDULES) as FixedSchedule[],
    requests: cloneDeep(INITIAL_REQUESTS) as BorrowRequestItem[],
    nextLetterSeq: 19,
    hydrated: false,
  }),

  getters: {
    mpkOrganization(state): OrganizationProfile {
      return state.organizations.find((o) => o.isMpk) || state.organizations[0]
    },
    eskulOrganizations(state): OrganizationProfile[] {
      return state.organizations.filter((o) => !o.isMpk)
    },
    activeOrganization(state): OrganizationProfile {
      if (state.role === 'mpk') {
        return state.organizations.find((o) => o.isMpk) || state.organizations[0]
      }
      return state.organizations.find((o) => o.id === state.activeEskulOrgId) || state.organizations[1]
    },
    visibleRequests(state): BorrowRequestItem[] {
      if (state.role === 'mpk') {
        return state.requests
      }
      return state.requests.filter(
        (r) => r.requesterOrgId === state.activeEskulOrgId || r.ownerOrgId === state.activeEskulOrgId
      )
    },
    archivedDocuments(state): BorrowRequestItem[] {
      const approved = state.requests.filter((r) => r.status === 'approved' && r.finalized && r.letterNumber)
      if (state.role === 'mpk') return approved
      return approved.filter(
        (r) => r.requesterOrgId === state.activeEskulOrgId || r.ownerOrgId === state.activeEskulOrgId
      )
    },
    activeActionCount(state): number {
      if (state.role === 'mpk') {
        return state.requests.filter((r) =>
          ['pending_mpk_screening', 'owner_approved', 'owner_rejected'].includes(r.status)
        ).length
      }
      return state.requests.filter(
        (r) => r.ownerOrgId === state.activeEskulOrgId && r.status === 'pending_owner'
      ).length
    },
  },

  actions: {
    initFromStorage() {
      if (this.hydrated || !import.meta.client) return
      this.hydrated = true
      try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (!raw) return
        const parsed = JSON.parse(raw)
        if (parsed && Array.isArray(parsed.requests) && Array.isArray(parsed.organizations)) {
          this.role = parsed.role === 'eskul' ? 'eskul' : 'mpk'
          this.activeEskulOrgId = Number(parsed.activeEskulOrgId) || 2
          this.organizations = parsed.organizations
          this.facilities = parsed.facilities || cloneDeep(INITIAL_FACILITIES)
          this.fixedSchedules = parsed.fixedSchedules || cloneDeep(INITIAL_FIXED_SCHEDULES)
          this.requests = parsed.requests
          this.nextLetterSeq = Number(parsed.nextLetterSeq) || 19
        }
      } catch {
        // Fallback silently to initial seed data
      }
    },

    persist() {
      if (!import.meta.client) return
      try {
        localStorage.setItem(
          STORAGE_KEY,
          JSON.stringify({
            role: this.role,
            activeEskulOrgId: this.activeEskulOrgId,
            organizations: this.organizations,
            facilities: this.facilities,
            fixedSchedules: this.fixedSchedules,
            requests: this.requests,
            nextLetterSeq: this.nextLetterSeq,
          })
        )
      } catch {
        // Ignore storage quota errors in demo
      }
    },

    resetDemoData() {
      this.role = 'mpk'
      this.activeEskulOrgId = 2
      this.organizations = cloneDeep(INITIAL_ORGANIZATIONS)
      this.facilities = cloneDeep(INITIAL_FACILITIES)
      this.fixedSchedules = cloneDeep(INITIAL_FIXED_SCHEDULES)
      this.requests = cloneDeep(INITIAL_REQUESTS)
      this.nextLetterSeq = 19
      this.persist()
    },

    switchRole(role: DemoRole) {
      this.role = role
      this.persist()
    },

    switchEskulOrg(orgId: number) {
      this.activeEskulOrgId = orgId
      this.persist()
    },

    allocateLetterNumber(): string {
      const seq = String(this.nextLetterSeq).padStart(3, '0')
      this.nextLetterSeq += 1
      return `${seq}/MPK-K2/XI/2026`
    },

    submitRequest(payload: {
      requesterOrgId: number
      facilityId: number
      date: string
      startTime: string
      endTime: string
      reason: string
      confirmSignature: boolean
    }): { ok: boolean; message: string; created?: BorrowRequestItem } {
      const requester = this.organizations.find((o) => o.id === payload.requesterOrgId)
      if (!requester) {
        return { ok: false, message: 'Organisasi pemohon tidak ditemukan.' }
      }
      if (!isOrgProfileComplete(requester)) {
        return {
          ok: false,
          message: `Profil & tanda tangan ${requester.name} belum lengkap. Lengkapi terlebih dahulu di tab Profil & Tanda Tangan.`,
        }
      }
      if (!payload.confirmSignature) {
        return { ok: false, message: 'Konfirmasi pembubuhan tanda tangan simulasi wajib dicentang.' }
      }
      if (!payload.date || !payload.startTime || !payload.endTime || payload.endTime <= payload.startTime) {
        return { ok: false, message: 'Jam selesai harus lebih akhir dari jam mulai.' }
      }

      const conflict = detectScheduleConflict({
        facilityId: payload.facilityId,
        requesterOrgId: payload.requesterOrgId,
        date: payload.date,
        startTime: payload.startTime,
        endTime: payload.endTime,
        fixedSchedules: this.fixedSchedules,
        requests: this.requests,
        organizations: this.organizations,
      })

      if (conflict.blockedByBooking) {
        return { ok: false, message: conflict.noticeText }
      }

      const mpkOrg = this.mpkOrganization
      const ownerOrg = conflict.overlappingOwnerOrg
      const workflowType = conflict.predictedWorkflow
      const newId = Math.max(100, ...this.requests.map((r) => r.id)) + 1
      const stamp = nowFormatted()

      if (workflowType === 'free_slot' && requester.isMpk) {
        const letterNumber = this.allocateLetterNumber()
        const created: BorrowRequestItem = {
          id: newId,
          workflowType: 'free_slot',
          facilityId: payload.facilityId,
          requesterOrgId: requester.id,
          ownerOrgId: null,
          date: payload.date,
          startTime: payload.startTime,
          endTime: payload.endTime,
          reason: payload.reason.trim(),
          status: 'approved',
          rejectionReason: null,
          letterNumber,
          letterDate: '2026-11-26',
          finalized: true,
          signatures: [
            {
              stageOrder: 1,
              signerRole: 'mpk',
              roleLabel: 'Perwakilan MPK Komisi 2',
              signerName: requester.chairpersonName,
              signerTitle: requester.chairpersonTitle,
              organizationName: requester.name,
              signed: true,
              signedAt: stamp,
              signatureDataUrl: requester.signatureDataUrl,
            },
          ],
          history: [
            {
              id: 1,
              actor: requester.name,
              roleText: 'Pemohon Resmi (Slot Kosong)',
              summary: `Mengajukan & menerbitkan surat slot kosong No. ${letterNumber} (Simulasi Demo).`,
              note: payload.reason.trim(),
              timestamp: stamp,
            },
          ],
        }
        this.requests.unshift(created)
        this.persist()
        return { ok: true, message: `Slot kosong disetujui & diterbitkan dengan nomor ${letterNumber}.`, created }
      }

      if (workflowType === 'mpk_borrow' && ownerOrg) {
        const created: BorrowRequestItem = {
          id: newId,
          workflowType: 'mpk_borrow',
          facilityId: payload.facilityId,
          requesterOrgId: requester.id,
          ownerOrgId: ownerOrg.id,
          date: payload.date,
          startTime: payload.startTime,
          endTime: payload.endTime,
          reason: payload.reason.trim(),
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
              signerName: requester.chairpersonName,
              signerTitle: requester.chairpersonTitle,
              organizationName: requester.name,
              signed: true,
              signedAt: stamp,
              signatureDataUrl: requester.signatureDataUrl,
            },
            {
              stageOrder: 2,
              signerRole: 'owner_eskul',
              roleLabel: 'Ketua Ekskul Pemilik Jadwal',
              signerName: ownerOrg.chairpersonName || 'Ketua Organisasi',
              signerTitle: ownerOrg.chairpersonTitle || `Ketua ${ownerOrg.name}`,
              organizationName: ownerOrg.name,
              signed: false,
              signedAt: null,
              signatureDataUrl: null,
            },
          ],
          history: [
            {
              id: 1,
              actor: requester.name,
              roleText: 'Pemohon Resmi (Alur B)',
              summary: `Mengajukan peminjaman langsung ke ${ownerOrg.name} dengan TTD MPK (tanpa screening terpisah).`,
              note: payload.reason.trim(),
              timestamp: stamp,
            },
          ],
        }
        this.requests.unshift(created)
        this.persist()
        return {
          ok: true,
          message: `Permohonan MPK (Alur B · 2 TTD) dikirim langsung ke ${ownerOrg.name}.`,
          created,
        }
      }

      if (workflowType === 'free_slot') {
        const created: BorrowRequestItem = {
          id: newId,
          workflowType: 'free_slot',
          facilityId: payload.facilityId,
          requesterOrgId: requester.id,
          ownerOrgId: null,
          date: payload.date,
          startTime: payload.startTime,
          endTime: payload.endTime,
          reason: payload.reason.trim(),
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
              signerName: requester.chairpersonName,
              signerTitle: requester.chairpersonTitle,
              organizationName: requester.name,
              signed: true,
              signedAt: stamp,
              signatureDataUrl: requester.signatureDataUrl,
            },
            {
              stageOrder: 2,
              signerRole: 'mpk',
              roleLabel: 'Perwakilan MPK Komisi 2',
              signerName: mpkOrg.chairpersonName,
              signerTitle: mpkOrg.chairpersonTitle,
              organizationName: mpkOrg.name,
              signed: false,
              signedAt: null,
              signatureDataUrl: null,
            },
          ],
          history: [
            {
              id: 1,
              actor: requester.name,
              roleText: 'Pemohon (Slot Kosong)',
              summary: 'Mengajukan peminjaman slot kosong + TTD Pemohon (menunggu pemeriksaan MPK).',
              note: payload.reason.trim(),
              timestamp: stamp,
            },
          ],
        }
        this.requests.unshift(created)
        this.persist()
        return { ok: true, message: 'Pengajuan slot kosong terkirim ke MPK untuk pemeriksaan.', created }
      }

      // Workflow A: Inter-extracurricular
      const created: BorrowRequestItem = {
        id: newId,
        workflowType: 'inter_eskul',
        facilityId: payload.facilityId,
        requesterOrgId: requester.id,
        ownerOrgId: ownerOrg?.id || null,
        date: payload.date,
        startTime: payload.startTime,
        endTime: payload.endTime,
        reason: payload.reason.trim(),
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
            signerName: requester.chairpersonName,
            signerTitle: requester.chairpersonTitle,
            organizationName: requester.name,
            signed: true,
            signedAt: stamp,
            signatureDataUrl: requester.signatureDataUrl,
          },
          {
            stageOrder: 2,
            signerRole: 'mpk',
            roleLabel: 'Perwakilan MPK Komisi 2',
            signerName: mpkOrg.chairpersonName,
            signerTitle: mpkOrg.chairpersonTitle,
            organizationName: mpkOrg.name,
            signed: false,
            signedAt: null,
            signatureDataUrl: null,
          },
          {
            stageOrder: 3,
            signerRole: 'owner_eskul',
            roleLabel: 'Ketua Ekskul Pemilik Jadwal',
            signerName: ownerOrg?.chairpersonName || 'Ketua Organisasi',
            signerTitle: ownerOrg?.chairpersonTitle || `Ketua ${ownerOrg?.name}`,
            organizationName: ownerOrg?.name || 'Pemilik Jadwal',
            signed: false,
            signedAt: null,
            signatureDataUrl: null,
          },
        ],
        history: [
          {
            id: 1,
            actor: requester.name,
            roleText: 'Pemohon (Alur A)',
            summary: `Mengajukan peminjaman jadwal ${ownerOrg?.name} + TTD Pemohon (Simulasi Demo).`,
            note: payload.reason.trim(),
            timestamp: stamp,
          },
        ],
      }
      this.requests.unshift(created)
      this.persist()
      return {
        ok: true,
        message: `Pengajuan (Alur A · 3 TTD) dikirim untuk pemeriksaan awal MPK.`,
        created,
      }
    },

    screenRequest(requestId: number, decision: 'accept' | 'reject', rejectionReason = ''): { ok: boolean; message: string } {
      const req = this.requests.find((r) => r.id === requestId)
      if (!req || req.status !== 'pending_mpk_screening') {
        return { ok: false, message: 'Permohonan tidak berada pada tahap pemeriksaan MPK.' }
      }
      const mpkOrg = this.mpkOrganization
      if (decision === 'accept' && !isOrgProfileComplete(mpkOrg)) {
        return { ok: false, message: 'Profil & tanda tangan MPK belum lengkap.' }
      }
      const stamp = nowFormatted()

      if (decision === 'reject') {
        const clean = rejectionReason.trim()
        if (!clean) return { ok: false, message: 'Alasan penolakan wajib diisi.' }
        req.status = 'rejected'
        req.rejectionReason = clean
        req.finalized = true
        req.history.push({
          id: req.history.length + 1,
          actor: mpkOrg.name,
          roleText: 'Pemeriksa Awal',
          summary: 'Menolak permohonan saat pemeriksaan awal MPK (surat izin tidak diterbitkan).',
          note: clean,
          timestamp: stamp,
        })
        this.persist()
        return { ok: true, message: 'Permohonan ditolak pada pemeriksaan MPK beserta alasan tertulis.' }
      }

      // Sign MPK Stage 2
      const mpkSig = req.signatures.find((s) => s.stageOrder === 2)
      if (mpkSig) {
        mpkSig.signed = true
        mpkSig.signedAt = stamp
        mpkSig.signerName = mpkOrg.chairpersonName
        mpkSig.signerTitle = mpkOrg.chairpersonTitle
        mpkSig.signatureDataUrl = mpkOrg.signatureDataUrl
      }

      if (req.workflowType === 'free_slot') {
        const letterNumber = this.allocateLetterNumber()
        req.status = 'approved'
        req.letterNumber = letterNumber
        req.letterDate = '2026-11-26'
        req.finalized = true
        req.history.push({
          id: req.history.length + 1,
          actor: mpkOrg.name,
          roleText: 'Pemeriksa & Penerbit (Slot Kosong)',
          summary: `Menyetujui, membubuhkan TTD MPK, dan menerbitkan surat resmi No. ${letterNumber}.`,
          timestamp: stamp,
        })
        this.persist()
        return { ok: true, message: `Slot kosong disetujui & surat resmi ${letterNumber} diterbitkan.` }
      }

      req.status = 'pending_owner'
      const ownerName = this.organizations.find((o) => o.id === req.ownerOrgId)?.name || 'Pemilik Jadwal'
      req.history.push({
        id: req.history.length + 1,
        actor: mpkOrg.name,
        roleText: 'Pemeriksa Awal',
        summary: `Lolos pemeriksaan MPK, TTD Saksi MPK dibubuhkan, dan diteruskan ke ${ownerName}.`,
        timestamp: stamp,
      })
      this.persist()
      return { ok: true, message: `Lolos pemeriksaan MPK & diteruskan ke ${ownerName}.` }
    },

    ownerDecision(requestId: number, decision: 'approve' | 'reject', rejectionReason = ''): { ok: boolean; message: string } {
      const req = this.requests.find((r) => r.id === requestId)
      if (!req || req.status !== 'pending_owner') {
        return { ok: false, message: 'Permohonan tidak sedang menunggu persetujuan pemilik jadwal.' }
      }
      const ownerOrg = this.organizations.find((o) => o.id === req.ownerOrgId)
      if (!ownerOrg) return { ok: false, message: 'Organisasi pemilik tidak ditemukan.' }
      if (decision === 'approve' && !isOrgProfileComplete(ownerOrg)) {
        return {
          ok: false,
          message: `Profil & tanda tangan ${ownerOrg.name} belum lengkap. Lengkapi terlebih dahulu di tab Profil & Tanda Tangan.`,
        }
      }
      const stamp = nowFormatted()

      if (decision === 'reject') {
        const clean = rejectionReason.trim()
        if (!clean) return { ok: false, message: 'Alasan penolakan wajib diisi.' }
        req.status = 'owner_rejected'
        req.rejectionReason = clean
        req.history.push({
          id: req.history.length + 1,
          actor: ownerOrg.name,
          roleText: 'Pemilik Jadwal',
          summary: 'Menolak peminjaman jadwal dengan alasan tertulis.',
          note: clean,
          timestamp: stamp,
        })
        this.persist()
        return { ok: true, message: `Keputusan penolakan oleh ${ownerOrg.name} dikirim ke MPK untuk dicatat.` }
      }

      const ownerSigStage = req.workflowType === 'mpk_borrow' ? 2 : 3
      const ownerSig = req.signatures.find((s) => s.stageOrder === ownerSigStage)
      if (ownerSig) {
        ownerSig.signed = true
        ownerSig.signedAt = stamp
        ownerSig.signerName = ownerOrg.chairpersonName
        ownerSig.signerTitle = ownerOrg.chairpersonTitle
        ownerSig.signatureDataUrl = ownerOrg.signatureDataUrl
      }

      req.status = 'owner_approved'
      req.history.push({
        id: req.history.length + 1,
        actor: ownerOrg.name,
        roleText: 'Pemilik Jadwal',
        summary:
          req.workflowType === 'mpk_borrow'
            ? 'Menyetujui peminjaman MPK & membubuhkan TTD Pemilik (siap dicatat & diarsipkan MPK).'
            : 'Menyetujui peminjaman & membubuhkan TTD Pemilik (siap verifikasi akhir MPK).',
        timestamp: stamp,
      })
      this.persist()
      return { ok: true, message: `Izin disetujui & TTD ${ownerOrg.name} tercatat.` }
    },

    finalizeAndPublish(requestId: number): { ok: boolean; message: string } {
      const req = this.requests.find((r) => r.id === requestId)
      if (!req || !['owner_approved', 'owner_rejected'].includes(req.status)) {
        return { ok: false, message: 'Berkas belum siap dicatat atau diverifikasi MPK.' }
      }
      const mpkOrg = this.mpkOrganization
      const stamp = nowFormatted()

      if (req.status === 'owner_rejected') {
        req.status = 'rejected'
        req.finalized = true
        req.history.push({
          id: req.history.length + 1,
          actor: mpkOrg.name,
          roleText: 'Pencatatan Administratif',
          summary: 'Mencatat penolakan resmi dari pemilik jadwal · tidak ada surat izin diterbitkan.',
          note: req.rejectionReason || undefined,
          timestamp: stamp,
        })
        this.persist()
        return { ok: true, message: 'Penolakan pemilik jadwal telah dicatat secara resmi.' }
      }

      const letterNumber = this.allocateLetterNumber()
      req.status = 'approved'
      req.letterNumber = letterNumber
      req.letterDate = '2026-11-26'
      req.finalized = true
      req.history.push({
        id: req.history.length + 1,
        actor: mpkOrg.name,
        roleText: req.workflowType === 'mpk_borrow' ? 'Pencatatan & Pengarsipan' : 'Verifikator Administratif',
        summary:
          req.workflowType === 'mpk_borrow'
            ? `Mencatat hasil persetujuan (2 TTD) dan mengarsipkan surat resmi No. ${letterNumber}.`
            : `Memverifikasi kelengkapan berkas (3 TTD) dan menerbitkan surat resmi No. ${letterNumber}.`,
        timestamp: stamp,
      })
      this.persist()
      return { ok: true, message: `Surat resmi ${letterNumber} berhasil dipublikasikan ke Arsip Surat Resmi.` }
    },

    updateOrganizationProfile(orgId: number, updates: Partial<OrganizationProfile>) {
      const org = this.organizations.find((o) => o.id === orgId)
      if (!org) return
      Object.assign(org, updates)
      this.persist()
    },

    addFacility(payload: { name: string; type: 'Indoor' | 'Outdoor'; description: string }) {
      const nextId = Math.max(0, ...this.facilities.map((f) => f.id)) + 1
      this.facilities.push({ id: nextId, ...payload })
      this.persist()
    },

    addFixedSchedule(payload: {
      facilityId: number
      organizationId: number
      day: WeekdayKey
      startTime: string
      endTime: string
    }) {
      const nextId = Math.max(0, ...this.fixedSchedules.map((s) => s.id)) + 1
      this.fixedSchedules.push({ id: nextId, ...payload })
      this.persist()
    },

    deleteFixedSchedule(scheduleId: number) {
      this.fixedSchedules = this.fixedSchedules.filter((s) => s.id !== scheduleId)
      this.persist()
    },

    createEskulAccount(payload: { name: string; accountEmail: string; chairpersonName: string }) {
      const nextId = Math.max(0, ...this.organizations.map((o) => o.id)) + 1
      const shortCode = payload.name.replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase() || 'ORG'
      this.organizations.push({
        id: nextId,
        name: payload.name,
        shortCode,
        isMpk: false,
        chairpersonName: payload.chairpersonName,
        chairpersonTitle: `Ketua ${payload.name}`,
        chairpersonIdNumber: `242510${120 + nextId}`,
        advisorName: '',
        logoDataUrl: null,
        signatureDataUrl: null,
        accountEmail: payload.accountEmail,
        accountActive: true,
      })
      this.persist()
    },

    toggleAccountActive(orgId: number) {
      const org = this.organizations.find((o) => o.id === orgId && !o.isMpk)
      if (!org) return
      org.accountActive = !org.accountActive
      this.persist()
    },
  },
})

