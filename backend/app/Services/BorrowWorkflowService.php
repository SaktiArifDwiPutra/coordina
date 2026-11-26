<?php

namespace App\Services;

use App\Models\ApprovalHistory;
use App\Models\BorrowRequest;
use App\Models\User;
use App\Notifications\BorrowRequestWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BorrowWorkflowService
{
    public function __construct(
        private readonly ScheduleConflictService $conflictService
    ) {}

    public function submit(User $actor, array $data): BorrowRequest
    {
        if ($actor->organization_id === null) {
            $this->conflictService->abortJson('Akun Anda belum terhubung ke organisasi mana pun.', 422);
        }

        return DB::transaction(function () use ($actor, $data) {
            $facilityId = (int) $data['facility_id'];
            $date = $data['date'];
            $startTime = $data['start_time'];
            $endTime = $data['end_time'];

            $this->conflictService->ensureNoApprovedBookingConflict(
                $facilityId,
                $date,
                $startTime,
                $endTime,
                null,
                true
            );

            $ownerOrgId = $this->conflictService->resolveScheduleOwner(
                $facilityId,
                $date,
                $startTime,
                $endTime,
                (int) $actor->organization_id
            );

            if ($actor->role === 'admin_mpk') {
                $workflowType = $ownerOrgId !== null
                    ? BorrowRequest::WORKFLOW_MPK_BORROW
                    : BorrowRequest::WORKFLOW_FREE_SLOT;
                $initialStatus = $ownerOrgId !== null
                    ? BorrowRequest::STATUS_PENDING_OWNER
                    : BorrowRequest::STATUS_APPROVED;
            } else {
                $workflowType = $ownerOrgId !== null
                    ? BorrowRequest::WORKFLOW_INTER_ESKUL
                    : BorrowRequest::WORKFLOW_FREE_SLOT;
                $initialStatus = BorrowRequest::STATUS_PENDING_MPK_SCREENING;
            }

            $borrowRequest = BorrowRequest::create([
                'user_id' => $actor->id,
                'organization_id' => $actor->organization_id,
                'facility_id' => $facilityId,
                'owner_organization_id' => $ownerOrgId,
                'workflow_type' => $workflowType,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => $data['reason'],
                'priority' => $data['priority'] ?? 'low',
                'status' => $initialStatus,
                'finalized_at' => $initialStatus === BorrowRequest::STATUS_APPROVED ? now() : null,
            ]);

            $this->recordHistory(
                $borrowRequest,
                $actor,
                'submitted',
                null,
                $initialStatus,
                $data['reason']
            );

            if ($initialStatus === BorrowRequest::STATUS_PENDING_MPK_SCREENING) {
                $this->notifyMpk(
                    $borrowRequest,
                    'submitted',
                    'Pengajuan Baru Menunggu Screening',
                    'Ada pengajuan peminjaman baru yang menunggu screening awal MPK.'
                );
            } elseif ($initialStatus === BorrowRequest::STATUS_PENDING_OWNER) {
                $this->notifyOrganizationOwners(
                    $borrowRequest,
                    $ownerOrgId,
                    'submitted',
                    'Permohonan Izin Jadwal dari MPK',
                    'MPK mengajukan peminjaman pada jadwal tetap organisasi Anda.'
                );
            }

            return $borrowRequest->load([
                'organization:id,name',
                'facility:id,name',
                'ownerOrganization:id,name',
                'histories.actor:id,name,role',
            ]);
        });
    }

    public function screen(BorrowRequest $borrowRequest, User $mpkUser, string $decision, ?string $rejectionReason = null): BorrowRequest
    {
        return DB::transaction(function () use ($borrowRequest, $mpkUser, $decision, $rejectionReason) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BorrowRequest::STATUS_PENDING_MPK_SCREENING) {
                $this->conflictService->abortJson('Permohonan tidak berada pada tahap screening MPK.', 422);
            }

            $fromStatus = $locked->status;

            if ($decision === 'reject') {
                $locked->update([
                    'status' => BorrowRequest::STATUS_REJECTED,
                    'rejection_reason' => $rejectionReason,
                    'mpk_screened_at' => now(),
                    'finalized_at' => now(),
                ]);

                $this->recordHistory($locked, $mpkUser, 'screened_rejected', $fromStatus, BorrowRequest::STATUS_REJECTED, $rejectionReason);
                $this->notifyOrganizationOwners(
                    $locked,
                    (int) $locked->organization_id,
                    'screened_rejected',
                    'Pengajuan Ditolak saat Screening MPK',
                    "Pengajuan ditolak MPK: {$rejectionReason}"
                );
            } else {
                if ($locked->owner_organization_id !== null) {
                    $toStatus = BorrowRequest::STATUS_PENDING_OWNER;
                    $locked->update([
                        'status' => $toStatus,
                        'mpk_screened_at' => now(),
                    ]);

                    $this->recordHistory($locked, $mpkUser, 'screened_accepted', $fromStatus, $toStatus);
                    $this->notifyOrganizationOwners(
                        $locked,
                        (int) $locked->owner_organization_id,
                        'screened_accepted',
                        'Permohonan Izin Jadwal Masuk',
                        'MPK telah meneruskan pengajuan peminjaman untuk jadwal tetap organisasi Anda.'
                    );
                } else {
                    $this->conflictService->ensureNoApprovedBookingConflict(
                        (int) $locked->facility_id,
                        $locked->date,
                        $locked->start_time,
                        $locked->end_time,
                        (int) $locked->id,
                        true
                    );

                    $toStatus = BorrowRequest::STATUS_APPROVED;
                    $locked->update([
                        'status' => $toStatus,
                        'mpk_screened_at' => now(),
                        'finalized_at' => now(),
                    ]);

                    $this->recordHistory($locked, $mpkUser, 'finalized_approved', $fromStatus, $toStatus);
                    $this->notifyOrganizationOwners(
                        $locked,
                        (int) $locked->organization_id,
                        'finalized_approved',
                        'Pengajuan Slot Kosong Disetujui',
                        'Pengajuan peminjaman pada slot kosong telah disetujui MPK.'
                    );
                }
            }

            return $locked->fresh([
                'organization:id,name',
                'facility:id,name',
                'ownerOrganization:id,name',
                'histories.actor:id,name,role',
            ]);
        });
    }

    public function ownerDecision(BorrowRequest $borrowRequest, User $ownerUser, string $decision, ?string $rejectionReason = null): BorrowRequest
    {
        return DB::transaction(function () use ($borrowRequest, $ownerUser, $decision, $rejectionReason) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BorrowRequest::STATUS_PENDING_OWNER) {
                $this->conflictService->abortJson('Permohonan belum atau tidak lagi menunggu keputusan pemilik jadwal.', 422);
            }

            $fromStatus = $locked->status;

            if ($decision === 'reject') {
                $locked->update([
                    'status' => BorrowRequest::STATUS_OWNER_REJECTED,
                    'rejection_reason' => $rejectionReason,
                    'owner_decided_at' => now(),
                ]);

                $this->recordHistory($locked, $ownerUser, 'owner_rejected', $fromStatus, BorrowRequest::STATUS_OWNER_REJECTED, $rejectionReason);
                $this->notifyMpk(
                    $locked,
                    'owner_rejected',
                    'Pemilik Jadwal Menolak Permohonan',
                    'Pemilik jadwal telah menolak permohonan dan menunggu pencatatan akhir MPK.'
                );
            } else {
                $this->conflictService->ensureNoApprovedBookingConflict(
                    (int) $locked->facility_id,
                    $locked->date,
                    $locked->start_time,
                    $locked->end_time,
                    (int) $locked->id,
                    true
                );

                $locked->update([
                    'status' => BorrowRequest::STATUS_OWNER_APPROVED,
                    'owner_decided_at' => now(),
                ]);

                $this->recordHistory($locked, $ownerUser, 'owner_approved', $fromStatus, BorrowRequest::STATUS_OWNER_APPROVED);
                $this->notifyMpk(
                    $locked,
                    'owner_approved',
                    'Pemilik Jadwal Menyetujui Permohonan',
                    'Pemilik jadwal telah menyetujui peminjaman dan menunggu pencatatan akhir MPK.'
                );
            }

            return $locked->fresh([
                'organization:id,name',
                'facility:id,name',
                'ownerOrganization:id,name',
                'histories.actor:id,name,role',
            ]);
        });
    }

    public function finalize(BorrowRequest $borrowRequest, User $mpkUser): BorrowRequest
    {
        return DB::transaction(function () use ($borrowRequest, $mpkUser) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->status;

            if ($fromStatus === BorrowRequest::STATUS_OWNER_APPROVED) {
                $this->conflictService->ensureNoApprovedBookingConflict(
                    (int) $locked->facility_id,
                    $locked->date,
                    $locked->start_time,
                    $locked->end_time,
                    (int) $locked->id,
                    true
                );

                $locked->update([
                    'status' => BorrowRequest::STATUS_APPROVED,
                    'finalized_at' => now(),
                ]);

                $this->recordHistory($locked, $mpkUser, 'finalized_approved', $fromStatus, BorrowRequest::STATUS_APPROVED);
                $this->notifyOrganizationOwners(
                    $locked,
                    (int) $locked->organization_id,
                    'finalized_approved',
                    'Peminjaman Resmi Disetujui',
                    'Keputusan persetujuan pemilik jadwal telah dicatat MPK dan jadwal resmi diperbarui.'
                );
            } elseif ($fromStatus === BorrowRequest::STATUS_OWNER_REJECTED) {
                $locked->update([
                    'status' => BorrowRequest::STATUS_REJECTED,
                    'finalized_at' => now(),
                ]);

                $this->recordHistory(
                    $locked,
                    $mpkUser,
                    'finalized_rejected',
                    $fromStatus,
                    BorrowRequest::STATUS_REJECTED,
                    $locked->rejection_reason
                );
                $this->notifyOrganizationOwners(
                    $locked,
                    (int) $locked->organization_id,
                    'finalized_rejected',
                    'Peminjaman Ditolak Pemilik Jadwal',
                    "Permohonan ditolak oleh pemilik jadwal: {$locked->rejection_reason}"
                );
            } else {
                $this->conflictService->abortJson('Permohonan belum siap dicatat akhir oleh MPK.', 422);
            }

            return $locked->fresh([
                'organization:id,name',
                'facility:id,name',
                'ownerOrganization:id,name',
                'histories.actor:id,name,role',
            ]);
        });
    }

    private function recordHistory(
        BorrowRequest $borrowRequest,
        User $actor,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?string $note = null
    ): void {
        ApprovalHistory::create([
            'borrow_request_id' => $borrowRequest->id,
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function notifyMpk(BorrowRequest $borrowRequest, string $action, string $title, string $message): void
    {
        $mpkUsers = User::where('role', 'admin_mpk')->get();
        if ($mpkUsers->isNotEmpty()) {
            Notification::send($mpkUsers, new BorrowRequestWorkflowNotification($borrowRequest, $action, $title, $message));
        }
    }

    private function notifyOrganizationOwners(BorrowRequest $borrowRequest, int $organizationId, string $action, string $title, string $message): void
    {
        $owners = User::where('role', 'owner_eskul')
            ->where('organization_id', $organizationId)
            ->get();

        if ($owners->isNotEmpty()) {
            Notification::send($owners, new BorrowRequestWorkflowNotification($borrowRequest, $action, $title, $message));
        }
    }
}

