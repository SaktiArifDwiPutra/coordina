export type DemoRole = 'mpk' | 'eskul'

export type WorkflowType = 'inter_eskul' | 'mpk_borrow' | 'free_slot'

export type RequestStatus =
  | 'pending_mpk_screening'
  | 'pending_owner'
  | 'owner_approved'
  | 'owner_rejected'
  | 'approved'
  | 'rejected'

export interface OrganizationProfile {
  id: number
  name: string
  shortCode: string
  isMpk: boolean
  chairpersonName: string
  chairpersonTitle: string
  chairpersonIdNumber: string
  advisorName: string
  logoDataUrl: string | null
  signatureDataUrl: string | null
  accountEmail: string
  accountActive: boolean
}

export interface Facility {
  id: number
  name: string
  type: 'Indoor' | 'Outdoor'
  description: string
}

export type WeekdayKey =
  | 'Monday'
  | 'Tuesday'
  | 'Wednesday'
  | 'Thursday'
  | 'Friday'
  | 'Saturday'
  | 'Sunday'

export interface FixedSchedule {
  id: number
  facilityId: number
  organizationId: number
  day: WeekdayKey
  startTime: string
  endTime: string
}

export interface SignatureEntry {
  stageOrder: 1 | 2 | 3
  signerRole: 'requester_eskul' | 'mpk' | 'owner_eskul'
  roleLabel: string
  signerName: string
  signerTitle: string
  organizationName: string
  signed: boolean
  signedAt: string | null
  signatureDataUrl: string | null
}

export interface ActivityEvent {
  id: number
  actor: string
  roleText: string
  summary: string
  note?: string
  timestamp: string
}

export interface BorrowRequestItem {
  id: number
  workflowType: WorkflowType
  facilityId: number
  requesterOrgId: number
  ownerOrgId: number | null
  date: string
  startTime: string
  endTime: string
  reason: string
  status: RequestStatus
  rejectionReason: string | null
  letterNumber: string | null
  letterDate: string | null
  finalized: boolean
  signatures: SignatureEntry[]
  history: ActivityEvent[]
}

