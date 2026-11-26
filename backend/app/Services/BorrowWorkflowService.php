<?php

namespace App\Services;

use App\Models\ApprovalHistory;
use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Notifications\BorrowRequestWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BorrowWorkflowService
{
    public function __construct(
        private readonly ScheduleConflictService $conflictService,
        private readonly SignatureService $signatureService,
        private readonly DocumentGenerationService $documentGenerationService
    ) {}

    public function submit(User $actor, array $data): BorrowRequest
    {
        $actor->ensureMpkOrganizationLinked();

        if ($actor->organization_id === null) {
            $this->conflictService->abortJson('Akun Anda belum terhubung ke organisasi mana pun.', 422);
        }

        $dispatchRequestId = null;

        $borrowRequest = DB::transaction(function () use ($actor, $data, &$dispatchRequestId) {
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

            $confirmSignature = ! empty($data['confirm_signature']);

            if ($actor->role === 'admin_mpk') {
                if ($ownerOrgId === null && ! $confirmSignature) {
                    $this->conflictService->abortJson(
                        'Tanda tangan MPK wajib dibubuhkan untuk menyetujui dan menerbitkan surat izin slot kosong.',
                        422
                    );
                }
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

            if ($initialStatus === BorrowRequest::STATUS_APPROVED) {
                $this->assignLetterNumberIfMissing($borrowRequest);
            }

            $activeTemplate = $this->documentGenerationService->resolveActiveTemplate($workflowType);
            $initialDocStatus = match ($initialStatus) {
                BorrowRequest::STATUS_PENDING_OWNER => BorrowDocument::STATUS_WAITING_OWNER_SIGNATURE,
                BorrowRequest::STATUS_APPROVED => BorrowDocument::STATUS_PENDING_PDF_GENERATION,
                default => BorrowDocument::STATUS_WAITING_MPK_SCREENING,
            };

            $document = BorrowDocument::create([
                'borrow_request_id' => $borrowRequest->id,
                'document_template_id' => $activeTemplate?->id,
                'current_revision' => 1,
                'document_status' => $initialDocStatus,
                'verified_by_user_id' => $initialStatus === BorrowRequest::STATUS_APPROVED ? $actor->id : null,
                'verified_at' => $initialStatus === BorrowRequest::STATUS_APPROVED ? now() : null,
                'published_at' => null,
            ]);

            $this->recordHistory($borrowRequest, $actor, 'submitted', null, $initialStatus, $data['reason']);

            if ($confirmSignature) {
                $signerRole = $actor->role === 'admin_mpk' ? 'mpk' : 'requester_eskul';
                $this->signatureService->sign($document, $actor, $signerRole, 1);
                $this->recordHistory($borrowRequest, $actor, "signed_{$signerRole}", $initialStatus, $initialStatus);
            }

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
            } elseif ($initialStatus === BorrowRequest::STATUS_APPROVED) {
                $this->recordHistory($borrowRequest, $actor, 'finalized_approved', $initialStatus, BorrowRequest::STATUS_APPROVED);
                $dispatchRequestId = (int) $borrowRequest->id;
            }

            return $borrowRequest;
        });

        if ($dispatchRequestId !== null) {
            $this->dispatchPdfGeneration($dispatchRequestId);
        }

        return $this->loadRelations($borrowRequest->fresh());
    }

    public function screen(
        BorrowRequest $borrowRequest,
        User $mpkUser,
        string $decision,
        ?string $rejectionReason = null,
        bool $confirmSignature = false
    ): BorrowRequest {
        $dispatchRequestId = null;

        $updated = DB::transaction(function () use ($borrowRequest, $mpkUser, $decision, $rejectionReason, $confirmSignature, &$dispatchRequestId) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BorrowRequest::STATUS_PENDING_MPK_SCREENING) {
                $this->conflictService->abortJson('Permohonan tidak berada pada tahap screening MPK.', 422);
            }

            $fromStatus = $locked->status;
            $document = $this->ensureLockedDocument($locked);

            if ($decision === 'reject') {
                $locked->update([
                    'status' => BorrowRequest::STATUS_REJECTED,
                    'rejection_reason' => $rejectionReason,
                    'mpk_screened_at' => now(),
                    'finalized_at' => now(),
                ]);
                $document->update(['document_status' => BorrowDocument::STATUS_REJECTED]);

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

                    if ($confirmSignature) {
                        $this->signatureService->sign($document, $mpkUser, 'mpk', 2);
                        $this->recordHistory($locked, $mpkUser, 'signed_mpk', $fromStatus, $toStatus);
                        $document->update(['document_status' => BorrowDocument::STATUS_WAITING_OWNER_SIGNATURE]);
                    } else {
                        $document->update(['document_status' => BorrowDocument::STATUS_WAITING_MPK_SIGNATURE]);
                    }

                    $this->recordHistory($locked, $mpkUser, 'screened_accepted', $fromStatus, $toStatus);
                    $this->notifyOrganizationOwners(
                        $locked,
                        (int) $locked->owner_organization_id,
                        'screened_accepted',
                        'Permohonan Izin Jadwal Masuk',
                        'MPK telah meneruskan pengajuan peminjaman untuk jadwal tetap organisasi Anda.'
                    );
                } else {
                    if ($confirmSignature) {
                        $this->signatureService->sign($document, $mpkUser, 'mpk', 2);
                        $this->recordHistory($locked, $mpkUser, 'signed_mpk', $fromStatus, BorrowRequest::STATUS_APPROVED);
                    }

                    if (! $this->signatureService->hasAllRequiredSignatures($locked, $document)) {
                        $this->conflictService->abortJson(
                            'Slot kosong tidak dapat disetujui karena tanda tangan wajib pemohon dan MPK belum lengkap.',
                            422
                        );
                    }

                    $this->conflictService->ensureNoApprovedBookingConflict(
                        (int) $locked->facility_id,
                        $locked->date,
                        $locked->start_time,
                        $locked->end_time,
                        (int) $locked->id,
                        true
                    );

                    $this->assignLetterNumberIfMissing($locked);

                    $toStatus = BorrowRequest::STATUS_APPROVED;
                    $locked->update([
                        'status' => $toStatus,
                        'mpk_screened_at' => now(),
                        'finalized_at' => now(),
                    ]);

                    $document->update([
                        'document_status' => BorrowDocument::STATUS_PENDING_PDF_GENERATION,
                        'verified_by_user_id' => $mpkUser->id,
                        'verified_at' => now(),
                        'published_at' => null,
                        'pdf_generation_error' => null,
                    ]);

                    $dispatchRequestId = (int) $locked->id;

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

            return $locked;
        });

        if ($dispatchRequestId !== null) {
            $this->dispatchPdfGeneration($dispatchRequestId);
        }

        return $this->loadRelations($updated->fresh());
    }

    public function ownerDecision(
        BorrowRequest $borrowRequest,
        User $ownerUser,
        string $decision,
        ?string $rejectionReason = null,
        bool $confirmSignature = false
    ): BorrowRequest {
        return DB::transaction(function () use ($borrowRequest, $ownerUser, $decision, $rejectionReason, $confirmSignature) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BorrowRequest::STATUS_PENDING_OWNER) {
                $this->conflictService->abortJson('Permohonan belum atau tidak lagi menunggu keputusan pemilik jadwal.', 422);
            }

            $fromStatus = $locked->status;
            $document = $this->ensureLockedDocument($locked);

            if ($decision === 'reject') {
                $locked->update([
                    'status' => BorrowRequest::STATUS_OWNER_REJECTED,
                    'rejection_reason' => $rejectionReason,
                    'owner_decided_at' => now(),
                ]);
                $document->update(['document_status' => BorrowDocument::STATUS_REJECTED]);

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

                if ($confirmSignature) {
                    $stageOrder = $locked->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW ? 2 : 3;
                    $this->signatureService->sign($document, $ownerUser, 'owner_eskul', $stageOrder);
                    $this->recordHistory($locked, $ownerUser, 'signed_owner_eskul', $fromStatus, BorrowRequest::STATUS_OWNER_APPROVED);
                    $document->update(['document_status' => BorrowDocument::STATUS_PENDING_MPK_VERIFICATION]);
                } else {
                    $document->update(['document_status' => BorrowDocument::STATUS_WAITING_OWNER_SIGNATURE]);
                }

                $this->recordHistory($locked, $ownerUser, 'owner_approved', $fromStatus, BorrowRequest::STATUS_OWNER_APPROVED);
                $this->notifyMpk(
                    $locked,
                    'owner_approved',
                    'Pemilik Jadwal Menyetujui Permohonan',
                    'Pemilik jadwal telah menyetujui peminjaman dan menunggu pencatatan akhir MPK.'
                );
            }

            return $this->loadRelations($locked->fresh());
        });
    }

    public function signStage(BorrowRequest $borrowRequest, User $actor): BorrowRequest
    {
        return DB::transaction(function () use ($borrowRequest, $actor) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                BorrowRequest::STATUS_REJECTED,
                BorrowRequest::STATUS_OWNER_REJECTED,
                BorrowRequest::STATUS_APPROVED,
            ], true)) {
                $this->conflictService->abortJson('Dokumen tidak dapat ditandatangani karena permohonan sudah ditolak atau disetujui final.', 422);
            }

            $document = $this->ensureLockedDocument($locked);

            if (in_array($document->document_status, [
                BorrowDocument::STATUS_REJECTED,
                BorrowDocument::STATUS_FINALIZED,
                BorrowDocument::STATUS_NEEDS_CORRECTION,
                BorrowDocument::STATUS_PENDING_PDF_GENERATION,
                BorrowDocument::STATUS_PDF_GENERATION_FAILED,
            ], true)) {
                $this->conflictService->abortJson('Status dokumen saat ini tidak mengizinkan penandatanganan.', 422);
            }

            if ($actor->role === 'admin_mpk') {
                if ($locked->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW) {
                    if ($locked->status !== BorrowRequest::STATUS_PENDING_OWNER) {
                        $this->conflictService->abortJson('Tahap tanda tangan MPK untuk pengajuan ini tidak valid.', 422);
                    }

                    $this->signatureService->sign($document, $actor, 'mpk', 1);
                    $this->recordHistory($locked, $actor, 'signed_mpk', $locked->status, $locked->status);
                } elseif ($locked->workflow_type === BorrowRequest::WORKFLOW_INTER_ESKUL) {
                    if (
                        $locked->status !== BorrowRequest::STATUS_PENDING_OWNER ||
                        $document->document_status !== BorrowDocument::STATUS_WAITING_MPK_SIGNATURE
                    ) {
                        $this->conflictService->abortJson(
                            'Tanda tangan MPK tahap 2 hanya dapat dilakukan setelah screening diterima dan dokumen menunggu tanda tangan MPK.',
                            422
                        );
                    }

                    $this->signatureService->sign($document, $actor, 'mpk', 2);
                    $document->update(['document_status' => BorrowDocument::STATUS_WAITING_OWNER_SIGNATURE]);
                    $this->recordHistory($locked, $actor, 'signed_mpk', $locked->status, $locked->status);
                } else {
                    $this->conflictService->abortJson('Tahap tanda tangan tidak valid untuk jenis permohonan ini.', 422);
                }
            } elseif ($actor->role === 'owner_eskul') {
                if ($actor->organization_id === null) {
                    $this->conflictService->abortJson('Akun tidak terhubung ke organisasi mana pun.', 403);
                }

                $actorOrgId = (int) $actor->organization_id;

                if (
                    $actorOrgId === (int) $locked->organization_id &&
                    in_array($locked->workflow_type, [BorrowRequest::WORKFLOW_INTER_ESKUL, BorrowRequest::WORKFLOW_FREE_SLOT], true)
                ) {
                    if (
                        $locked->status !== BorrowRequest::STATUS_PENDING_MPK_SCREENING ||
                        $document->document_status !== BorrowDocument::STATUS_WAITING_MPK_SCREENING
                    ) {
                        $this->conflictService->abortJson('Tanda tangan peminjam hanya dapat dilakukan sebelum screening MPK.', 422);
                    }

                    $this->signatureService->sign($document, $actor, 'requester_eskul', 1);
                    $this->recordHistory($locked, $actor, 'signed_requester_eskul', $locked->status, $locked->status);
                } elseif ($locked->owner_organization_id !== null && $actorOrgId === (int) $locked->owner_organization_id) {
                    if (
                        $locked->status !== BorrowRequest::STATUS_OWNER_APPROVED ||
                        $document->document_status !== BorrowDocument::STATUS_WAITING_OWNER_SIGNATURE
                    ) {
                        $this->conflictService->abortJson(
                            'Tanda tangan pemilik jadwal hanya dapat dilakukan setelah pemilik menyetujui dan dokumen menunggu tanda tangan pemilik.',
                            422
                        );
                    }

                    $stage = $locked->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW ? 2 : 3;
                    $this->signatureService->sign($document, $actor, 'owner_eskul', $stage);
                    $document->update(['document_status' => BorrowDocument::STATUS_PENDING_MPK_VERIFICATION]);
                    $this->recordHistory($locked, $actor, 'signed_owner_eskul', $locked->status, $locked->status);
                } else {
                    $this->conflictService->abortJson('Anda tidak memiliki hak tanda tangan untuk permohonan ini.', 403);
                }
            } else {
                $this->conflictService->abortJson('Anda tidak memiliki hak tanda tangan untuk permohonan ini.', 403);
            }

            return $this->loadRelations($locked->fresh());
        });
    }

    public function finalize(BorrowRequest $borrowRequest, User $mpkUser): BorrowRequest
    {
        $dispatchRequestId = null;

        $finalized = DB::transaction(function () use ($borrowRequest, $mpkUser, &$dispatchRequestId) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->status;
            $document = $this->ensureLockedDocument($locked);

            if ($fromStatus === BorrowRequest::STATUS_OWNER_APPROVED) {
                if ($document->document_status !== BorrowDocument::STATUS_PENDING_MPK_VERIFICATION) {
                    $this->conflictService->abortJson(
                        'Dokumen belum siap difinalisasi karena status dokumen bukan menunggu verifikasi akhir MPK.',
                        422
                    );
                }

                if (! $this->signatureService->hasAllRequiredSignatures($locked, $document)) {
                    $this->conflictService->abortJson(
                        'Permohonan tidak dapat difinalisasi karena seluruh tanda tangan wajib pada revisi aktif belum lengkap.',
                        422
                    );
                }

                $this->conflictService->ensureNoApprovedBookingConflict(
                    (int) $locked->facility_id,
                    $locked->date,
                    $locked->start_time,
                    $locked->end_time,
                    (int) $locked->id,
                    true
                );

                $this->assignLetterNumberIfMissing($locked);

                $locked->update([
                    'status' => BorrowRequest::STATUS_APPROVED,
                    'finalized_at' => now(),
                ]);
                $document->update([
                    'document_status' => BorrowDocument::STATUS_PENDING_PDF_GENERATION,
                    'verified_by_user_id' => $mpkUser->id,
                    'verified_at' => now(),
                    'published_at' => null,
                    'pdf_generation_error' => null,
                ]);

                $dispatchRequestId = (int) $locked->id;

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
                $document->update(['document_status' => BorrowDocument::STATUS_REJECTED]);

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

            return $locked;
        });

        if ($dispatchRequestId !== null) {
            $this->dispatchPdfGeneration($dispatchRequestId);
        }

        return $this->loadRelations($finalized->fresh());
    }

    private function ensureLockedDocument(BorrowRequest $borrowRequest): BorrowDocument
    {
        $doc = BorrowDocument::where('borrow_request_id', $borrowRequest->id)->lockForUpdate()->first();

        if ($doc) {
            return $doc;
        }

        return BorrowDocument::create([
            'borrow_request_id' => $borrowRequest->id,
            'document_template_id' => DocumentTemplate::where('is_active', true)->orderByDesc('version')->value('id'),
            'current_revision' => 1,
            'document_status' => BorrowDocument::STATUS_DRAFT,
        ]);
    }

    public function requestCorrection(BorrowRequest $borrowRequest, User $mpkUser, string $reason): BorrowRequest
    {
        return DB::transaction(function () use ($borrowRequest, $mpkUser, $reason) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();
            $document = $this->ensureLockedDocument($locked);

            if (
                $locked->status !== BorrowRequest::STATUS_OWNER_APPROVED ||
                $document->document_status !== BorrowDocument::STATUS_PENDING_MPK_VERIFICATION
            ) {
                $this->conflictService->abortJson(
                    'Permintaan revisi hanya dapat diajukan saat dokumen menunggu verifikasi akhir MPK.',
                    422
                );
            }

            if ($document->final_pdf_path !== null) {
                $this->conflictService->abortJson(
                    'Dokumen yang sudah difinalisasi menjadi PDF permanen tidak dapat direvisi.',
                    422
                );
            }

            $activeSnapshots = $document->activeSignatures()->get()->map(fn ($sig) => [
                'signer_role' => $sig->signer_role,
                'stage_order' => $sig->stage_order,
                'signer_name_snapshot' => $sig->signer_name_snapshot,
                'signed_at' => optional($sig->signed_at)->toIso8601String(),
            ])->values()->all();

            \App\Models\DocumentRevision::create([
                'borrow_document_id' => $document->id,
                'revision_number' => (int) $document->current_revision,
                'triggered_by_user_id' => $mpkUser->id,
                'reason' => $reason,
                'snapshot_json' => [
                    'request' => [
                        'facility_id' => $locked->facility_id,
                        'date' => $locked->date,
                        'start_time' => $locked->start_time,
                        'end_time' => $locked->end_time,
                        'reason' => $locked->reason,
                        'workflow_type' => $locked->workflow_type,
                        'owner_organization_id' => $locked->owner_organization_id,
                    ],
                    'signatures' => $activeSnapshots,
                ],
                'created_at' => now(),
            ]);

            $this->signatureService->invalidateSignatures($document);

            $document->update([
                'current_revision' => ((int) $document->current_revision) + 1,
                'document_status' => BorrowDocument::STATUS_NEEDS_CORRECTION,
            ]);

            $this->recordHistory($locked, $mpkUser, 'flagged_correction', $locked->status, $locked->status, $reason);

            if ($locked->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW) {
                $this->notifyMpk(
                    $locked,
                    'flagged_correction',
                    'Dokumen Perlu Revisi',
                    "Permohonan #{$locked->id} ditandai perlu revisi: {$reason}"
                );
            } else {
                $this->notifyOrganizationOwners(
                    $locked,
                    (int) $locked->organization_id,
                    'flagged_correction',
                    'Permohonan Perlu Revisi',
                    "MPK meminta revisi untuk permohonan #{$locked->id}: {$reason}"
                );
            }

            return $this->loadRelations($locked->fresh());
        });
    }

    public function revise(BorrowRequest $borrowRequest, User $actor, array $data): BorrowRequest
    {
        $dispatchRequestId = null;

        $revised = DB::transaction(function () use ($borrowRequest, $actor, $data, &$dispatchRequestId) {
            $locked = BorrowRequest::whereKey($borrowRequest->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->status;
            $document = $this->ensureLockedDocument($locked);

            if ($document->document_status !== BorrowDocument::STATUS_NEEDS_CORRECTION) {
                $this->conflictService->abortJson(
                    'Permohonan tidak sedang berada dalam status perlu revisi.',
                    422
                );
            }

            if ($locked->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW) {
                if ($actor->role !== 'admin_mpk') {
                    $this->conflictService->abortJson('Hanya admin MPK yang dapat merevisi pengajuan MPK.', 403);
                }
            } else {
                if (
                    $actor->role !== 'owner_eskul' ||
                    $actor->organization_id === null ||
                    (int) $actor->organization_id !== (int) $locked->organization_id
                ) {
                    $this->conflictService->abortJson('Hanya organisasi peminjam yang dapat merevisi permohonan ini.', 403);
                }
            }

            $facilityId = (int) $data['facility_id'];
            $date = $data['date'];
            $startTime = $data['start_time'];
            $endTime = $data['end_time'];

            $this->conflictService->ensureNoApprovedBookingConflict(
                $facilityId,
                $date,
                $startTime,
                $endTime,
                (int) $locked->id,
                true
            );

            $ownerOrgId = $this->conflictService->resolveScheduleOwner(
                $facilityId,
                $date,
                $startTime,
                $endTime,
                (int) $locked->organization_id
            );

            $confirmSignature = (bool) ($data['confirm_signature'] ?? false);

            if ($actor->role === 'admin_mpk') {
                if ($ownerOrgId === null && ! $confirmSignature) {
                    $this->conflictService->abortJson(
                        'Tanda tangan MPK wajib dibubuhkan untuk menerbitkan revisi slot kosong.',
                        422
                    );
                }

                $workflowType = $ownerOrgId !== null
                    ? BorrowRequest::WORKFLOW_MPK_BORROW
                    : BorrowRequest::WORKFLOW_FREE_SLOT;
                $nextStatus = $ownerOrgId !== null
                    ? BorrowRequest::STATUS_PENDING_OWNER
                    : BorrowRequest::STATUS_APPROVED;
                $nextDocStatus = $ownerOrgId !== null
                    ? BorrowDocument::STATUS_WAITING_OWNER_SIGNATURE
                    : BorrowDocument::STATUS_PENDING_PDF_GENERATION;
            } else {
                $workflowType = $ownerOrgId !== null
                    ? BorrowRequest::WORKFLOW_INTER_ESKUL
                    : BorrowRequest::WORKFLOW_FREE_SLOT;
                $nextStatus = BorrowRequest::STATUS_PENDING_MPK_SCREENING;
                $nextDocStatus = BorrowDocument::STATUS_WAITING_MPK_SCREENING;
            }

            $locked->update([
                'facility_id' => $facilityId,
                'owner_organization_id' => $ownerOrgId,
                'workflow_type' => $workflowType,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => $data['reason'],
                'status' => $nextStatus,
                'finalized_at' => $nextStatus === BorrowRequest::STATUS_APPROVED ? now() : null,
            ]);

            if ($workflowType === BorrowRequest::WORKFLOW_FREE_SLOT && $nextStatus === BorrowRequest::STATUS_APPROVED) {
                $this->assignLetterNumberIfMissing($locked);
            }

            $latestTemplate = $this->documentGenerationService->resolveActiveTemplate($workflowType);

            $document->update([
                'document_template_id' => $latestTemplate?->id ?? $document->document_template_id,
                'document_status' => $nextDocStatus,
                'merged_docx_path' => null,
                'final_pdf_path' => null,
                'final_pdf_sha256' => null,
                'pdf_generation_error' => null,
                'verified_by_user_id' => $nextStatus === BorrowRequest::STATUS_APPROVED ? $actor->id : null,
                'verified_at' => $nextStatus === BorrowRequest::STATUS_APPROVED ? now() : null,
                'published_at' => null,
            ]);

            if (
                $confirmSignature &&
                in_array($workflowType, [BorrowRequest::WORKFLOW_INTER_ESKUL, BorrowRequest::WORKFLOW_FREE_SLOT], true) &&
                $actor->role === 'owner_eskul'
            ) {
                $this->signatureService->sign($document, $actor, 'requester_eskul', 1);
            } elseif (
                $confirmSignature &&
                in_array($workflowType, [BorrowRequest::WORKFLOW_MPK_BORROW, BorrowRequest::WORKFLOW_FREE_SLOT], true) &&
                $actor->role === 'admin_mpk'
            ) {
                $this->signatureService->sign($document, $actor, 'mpk', 1);
            }

            $this->recordHistory(
                $locked,
                $actor,
                'revised_resubmitted',
                $fromStatus,
                $nextStatus,
                "Revisi v{$document->current_revision} diajukan ulang."
            );

            if ($nextStatus === BorrowRequest::STATUS_PENDING_OWNER && $ownerOrgId !== null) {
                $this->notifyOrganizationOwners(
                    $locked,
                    $ownerOrgId,
                    'revised_resubmitted',
                    'Revisi Pengajuan Peminjaman MPK',
                    "MPK mengirim revisi pengajuan #{$locked->id} pada slot jadwal organisasi Anda."
                );
            } elseif ($nextStatus === BorrowRequest::STATUS_PENDING_MPK_SCREENING) {
                $this->notifyMpk(
                    $locked,
                    'revised_resubmitted',
                    'Revisi Permohonan Baru',
                    "Permohonan #{$locked->id} telah direvisi dan menunggu screening MPK."
                );
            } elseif ($nextStatus === BorrowRequest::STATUS_APPROVED) {
                $dispatchRequestId = (int) $locked->id;
            }

            return $locked;
        });

        if ($dispatchRequestId !== null) {
            $this->dispatchPdfGeneration($dispatchRequestId);
        }

        return $this->loadRelations($revised->fresh());
    }

    private function assignLetterNumberIfMissing(BorrowRequest $locked): void
    {
        if ($locked->letter_number !== null && $locked->letter_date !== null) {
            return;
        }

        $today = now();
        $yearInt = (int) $today->year;
        $yearStr = (string) $yearInt;

        $counter = \App\Models\LetterCounter::where('year', $yearInt)->lockForUpdate()->first();
        if (! $counter) {
            try {
                \App\Models\LetterCounter::create([
                    'year' => $yearInt,
                    'last_sequence' => 0,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Concurrent first-letter-of-year creation raced cleanly; re-select locked row below
            }

            $counter = \App\Models\LetterCounter::where('year', $yearInt)->lockForUpdate()->firstOrFail();
        }

        $existingNumbers = BorrowRequest::query()
            ->whereBetween('letter_date', ["{$yearStr}-01-01", "{$yearStr}-12-31"])
            ->whereNotNull('letter_number')
            ->lockForUpdate()
            ->pluck('letter_number');

        $maxSeq = (int) $counter->last_sequence;
        foreach ($existingNumbers as $num) {
            if (preg_match('/^(\d+)\//', (string) $num, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;
        $counter->update(['last_sequence' => $nextSeq]);

        $locked->update([
            'letter_number' => sprintf('%03d/MPK/%s', $nextSeq, $yearStr),
            'letter_date' => $locked->letter_date ?? $today->toDateString(),
        ]);
    }

    private function dispatchPdfGeneration(int $borrowRequestId): void
    {
        if (config('coordina.async_pdf_generation')) {
            \App\Jobs\GenerateFinalPermissionPdfJob::dispatch($borrowRequestId);
            return;
        }

        \App\Jobs\GenerateFinalPermissionPdfJob::dispatchSync($borrowRequestId);
    }

    private function loadRelations(BorrowRequest $borrowRequest): BorrowRequest
    {
        return $borrowRequest->load([
            'organization:id,name,is_mpk',
            'facility:id,name',
            'ownerOrganization:id,name',
            'histories.actor:id,name,role',
            'document.activeSignatures',
            'document.signatures',
            'document.revisions',
            'document.template:id,name,version',
        ]);
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
