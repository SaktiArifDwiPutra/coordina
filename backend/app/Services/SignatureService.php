<?php

namespace App\Services;

use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Models\DocumentSignature;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SignatureService
{
    public function ensureOrganizationCanSign(Organization $organization): void
    {
        if (! $organization->isProfileCompleteForSigning()) {
            $this->abortJson(
                "Profil tanda tangan organisasi ({$organization->name}) belum lengkap. Lengkapi nama ketua, jabatan, dan gambar tanda tangan terlebih dahulu.",
                422
            );
        }

        if (! Storage::disk('local')->exists($organization->signature_image_path)) {
            $this->abortJson(
                "File tanda tangan untuk {$organization->name} tidak ditemukan di penyimpanan.",
                422
            );
        }
    }

    public function sign(BorrowDocument $document, User $actor, string $signerRole, int $stageOrder): DocumentSignature
    {
        return DB::transaction(function () use ($document, $actor, $signerRole, $stageOrder) {
            // Serialize concurrent signing requests on the parent BorrowDocument row
            $lockedDoc = BorrowDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $borrowRequest = $lockedDoc->borrowRequest;
            $organization = $this->resolveSignerOrganization($borrowRequest, $actor, $signerRole);

            $this->ensureOrganizationCanSign($organization);

            $alreadySigned = DocumentSignature::query()
                ->where('borrow_document_id', $lockedDoc->id)
                ->where('revision_number', $lockedDoc->current_revision)
                ->where('stage_order', $stageOrder)
                ->exists();

            if ($alreadySigned) {
                $this->abortJson('Tahap tanda tangan ini sudah dilakukan pada revisi aktif.', 422);
            }

            if ($stageOrder > 1) {
                $previousSigned = DocumentSignature::query()
                    ->where('borrow_document_id', $lockedDoc->id)
                    ->where('revision_number', $lockedDoc->current_revision)
                    ->where('stage_order', $stageOrder - 1)
                    ->where('is_valid', true)
                    ->exists();

                if (! $previousSigned) {
                    $this->abortJson('Tanda tangan tahap sebelumnya belum selesai.', 422);
                }
            }

            try {
                return DocumentSignature::create([
                    'borrow_document_id' => $lockedDoc->id,
                    'revision_number' => $lockedDoc->current_revision,
                    'signer_role' => $signerRole,
                    'stage_order' => $stageOrder,
                    'organization_id' => $organization->id,
                    'signed_by_user_id' => $actor->id,
                    'signer_name_snapshot' => $organization->chairperson_name,
                    'signer_title_snapshot' => $organization->chairperson_title,
                    'signer_id_number_snapshot' => $organization->chairperson_id_number,
                    'signature_image_path_snapshot' => $organization->signature_image_path,
                    'is_valid' => true,
                    'signed_at' => now(),
                    'created_at' => now(),
                ]);
            } catch (QueryException $e) {
                $this->abortJson('Tahap tanda tangan ini sudah dilakukan pada revisi aktif.', 422);
            }
        });
    }

    public function requiredStagesFor(BorrowRequest $borrowRequest): array
    {
        if ($borrowRequest->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW) {
            return [1, 2];
        }

        if ($borrowRequest->workflow_type === BorrowRequest::WORKFLOW_FREE_SLOT) {
            $borrowRequest->loadMissing('organization:id,is_mpk');
            if ($borrowRequest->organization?->is_mpk) {
                return [1];
            }

            return [1, 2];
        }

        return [1, 2, 3];
    }

    public function hasAllRequiredSignatures(BorrowRequest $borrowRequest, BorrowDocument $document): bool
    {
        $requiredStages = $this->requiredStagesFor($borrowRequest);

        $signatures = DocumentSignature::query()
            ->where('borrow_document_id', $document->id)
            ->where('revision_number', $document->current_revision)
            ->where('is_valid', true)
            ->orderBy('stage_order')
            ->get();

        if ($signatures->count() !== count($requiredStages)) {
            return false;
        }

        return $signatures->pluck('stage_order')->values()->all() === $requiredStages;
    }

    public function invalidateSignatures(BorrowDocument $document): void
    {
        DocumentSignature::query()
            ->where('borrow_document_id', $document->id)
            ->where('is_valid', true)
            ->update(['is_valid' => false]);
    }

    private function resolveSignerOrganization(BorrowRequest $borrowRequest, User $actor, string $signerRole): Organization
    {
        if ($signerRole === 'requester_eskul') {
            return Organization::findOrFail($borrowRequest->organization_id);
        }

        if ($signerRole === 'owner_eskul') {
            if ($borrowRequest->owner_organization_id === null) {
                $this->abortJson('Permohonan ini tidak memiliki organisasi pemilik jadwal.', 422);
            }

            return Organization::findOrFail($borrowRequest->owner_organization_id);
        }

        if ($actor->organization_id !== null) {
            return Organization::findOrFail($actor->organization_id);
        }

        $mpkOrg = Organization::where('is_mpk', true)->first();
        if (! $mpkOrg) {
            $this->abortJson('Organisasi MPK belum dikonfigurasi untuk penandatanganan.', 422);
        }

        return $mpkOrg;
    }

    private function abortJson(string $message, int $status): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
