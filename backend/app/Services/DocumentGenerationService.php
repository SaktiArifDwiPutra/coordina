<?php

namespace App\Services;

use App\Contracts\PdfConverterInterface;
use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

class DocumentGenerationService
{
    public function __construct(
        private readonly MasterTemplateGeneratorService $templateGenerator,
        private readonly PdfConverterInterface $pdfConverter
    ) {}

    public function resolveActiveTemplate(string $workflowType): ?DocumentTemplate
    {
        $scopes = match ($workflowType) {
            BorrowRequest::WORKFLOW_INTER_ESKUL => ['workflow_a', 'shared'],
            BorrowRequest::WORKFLOW_MPK_BORROW => ['workflow_b', 'shared'],
            default => ['shared'],
        };

        $templates = DocumentTemplate::query()
            ->where('is_active', true)
            ->whereIn('workflow_scope', $scopes)
            ->orderByDesc('version')
            ->get();

        if ($templates->isEmpty()) {
            return DocumentTemplate::where('is_active', true)->orderByDesc('version')->first();
        }

        $preferred = $templates->first(fn (DocumentTemplate $tpl) => $tpl->workflow_scope !== 'shared');

        return $preferred ?? $templates->first();
    }

    public function mergeDocx(BorrowRequest $borrowRequest, BorrowDocument $document): string
    {
        $borrowRequest->loadMissing(['organization', 'ownerOrganization', 'facility']);

        $template = $document->document_template_id
            ? DocumentTemplate::find($document->document_template_id)
            : $this->resolveActiveTemplate($borrowRequest->workflow_type);

        if (! $template || ! Storage::disk('local')->exists($template->file_path)) {
            $relativeDefault = 'templates/smkn4_auto_master.docx';
            $this->templateGenerator->generateDefaultTemplate($relativeDefault);
            $template = DocumentTemplate::create([
                'name' => 'Template Resmi MPK SMKN 4 Padalarang',
                'workflow_scope' => 'shared',
                'file_path' => $relativeDefault,
                'placeholders_json' => MasterTemplateGeneratorService::REQUIRED_PLACEHOLDERS,
                'version' => ((int) DocumentTemplate::max('version')) + 1,
                'is_active' => true,
            ]);
            $document->update(['document_template_id' => $template->id]);
        }

        $relativeMerged = "documents/merged/req_{$borrowRequest->id}_rev_{$document->current_revision}.docx";
        $stagingDocx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'coordina_merge_' . bin2hex(random_bytes(8)) . '.docx';

        try {
            $processor = new TemplateProcessor(Storage::disk('local')->path($template->file_path));

            $signatures = $document->activeSignatures()->get()->keyBy('signer_role');
            $mpkSig = $signatures->get('mpk');
            $requesterSig = $signatures->get('requester_eskul');
            $ownerSig = $signatures->get('owner_eskul');

            $mpkOrg = Organization::where('is_mpk', true)->first()
                ?? ($mpkSig ? Organization::find($mpkSig->organization_id) : null);
            $requesterOrg = $borrowRequest->organization;

            $this->applyRequiredImageOrEmpty($processor, 'mpk_logo', $mpkOrg?->logo_path, ['width' => 75, 'height' => 75, 'ratio' => true], false);
            $this->applyRequiredImageOrEmpty(
                $processor,
                'requesting_org_logo',
                $requesterOrg?->logo_path,
                ['width' => 75, 'height' => 75, 'ratio' => true],
                false
            );

            $letterDate = Carbon::parse($borrowRequest->letter_date ?? now())->locale('id')->translatedFormat('d F Y');
            $borrowDate = Carbon::parse($borrowRequest->date)->locale('id')->translatedFormat('l, d F Y');
            $timeRange = substr((string) $borrowRequest->start_time, 0, 5) . ' - ' . substr((string) $borrowRequest->end_time, 0, 5) . ' WIB';

            $isWorkflowB = $borrowRequest->workflow_type === BorrowRequest::WORKFLOW_MPK_BORROW;
            $isFreeSlot = $borrowRequest->workflow_type === BorrowRequest::WORKFLOW_FREE_SLOT;
            $isMpkRequester = (bool) ($requesterOrg?->is_mpk);

            $requesterClause = $isMpkRequester ? '' : ' dan ekstrakulikuler ' . ($requesterOrg?->name ?? '-');
            $recipientOrg = $borrowRequest->ownerOrganization?->name ?? 'Pengelola Sarana Prasarana Sekolah';

            $processor->setValue('letter_date', $letterDate);
            $processor->setValue('letter_number', $borrowRequest->letter_number ?? '-/MPK/' . now()->format('Y'));
            $processor->setValue('recipient_org', $recipientOrg);
            $processor->setValue('reason', $borrowRequest->reason);
            $processor->setValue('requester_clause', $requesterClause);
            $processor->setValue('borrow_date', $borrowDate);
            $processor->setValue('facility_name', $borrowRequest->facility?->name ?? '-');
            $processor->setValue('time_range', $timeRange);

            // Left signature block: MPK Representative (present in all workflows)
            $processor->setValue('mpk_chair_name', $mpkSig?->signer_name_snapshot ?? '-');
            $processor->setValue('mpk_chair_id', $mpkSig?->signer_id_number_snapshot ?: '-');
            $this->applyRequiredImageOrEmpty(
                $processor,
                'mpk_signature',
                $mpkSig?->signature_image_path_snapshot,
                ['width' => 110, 'height' => 55, 'ratio' => true],
                $mpkSig !== null
            );

            if ($isWorkflowB) {
                // 2-signature Workflow B: Right = Affected Owner, Bottom = empty
                $this->setRightSignatureBlock(
                    $processor,
                    $ownerSig?->signer_title_snapshot ?? ('Ketua ' . ($borrowRequest->ownerOrganization?->name ?? '')),
                    $ownerSig?->signer_name_snapshot ?? '-',
                    $ownerSig?->signer_id_number_snapshot ?: '-',
                    $ownerSig?->signature_image_path_snapshot,
                    $ownerSig !== null
                );
                $this->clearBottomSignatureBlock($processor);
            } elseif ($isFreeSlot && $isMpkRequester) {
                // 1-signature MPK Free Slot: MPK Left only; Right & Bottom omitted cleanly
                $this->clearRightSignatureBlock($processor);
                $this->clearBottomSignatureBlock($processor);
            } elseif ($isFreeSlot) {
                // 2-signature Eskul Free Slot: Left = MPK, Right = Requesting Eskul, Bottom = empty
                $this->setRightSignatureBlock(
                    $processor,
                    $requesterSig?->signer_title_snapshot ?? ('Ketua ' . ($requesterOrg?->name ?? '')),
                    $requesterSig?->signer_name_snapshot ?? '-',
                    $requesterSig?->signer_id_number_snapshot ?: '-',
                    $requesterSig?->signature_image_path_snapshot,
                    $requesterSig !== null
                );
                $this->clearBottomSignatureBlock($processor);
            } else {
                // 3-signature Workflow A: Right = Requesting Eskul, Bottom = Receiving Owner
                $this->setRightSignatureBlock(
                    $processor,
                    $requesterSig?->signer_title_snapshot ?? ('Ketua ' . ($requesterOrg?->name ?? '')),
                    $requesterSig?->signer_name_snapshot ?? '-',
                    $requesterSig?->signer_id_number_snapshot ?: '-',
                    $requesterSig?->signature_image_path_snapshot,
                    $requesterSig !== null
                );

                $processor->setValue('bottom_chair_title', $ownerSig?->signer_title_snapshot ?? ('Ketua ' . ($borrowRequest->ownerOrganization?->name ?? '')));
                $processor->setValue('bottom_school_line', 'SMK Negeri 4 Padalarang');
                $this->applyRequiredImageOrEmpty(
                    $processor,
                    'bottom_signature',
                    $ownerSig?->signature_image_path_snapshot,
                    ['width' => 110, 'height' => 55, 'ratio' => true],
                    $ownerSig !== null
                );
                $processor->setValue('bottom_chair_name_line', '(' . ($ownerSig?->signer_name_snapshot ?? '-') . ')');
                $processor->setValue('bottom_chair_id_line', 'NIS. ' . ($ownerSig?->signer_id_number_snapshot ?: '-'));
            }

            $processor->saveAs($stagingDocx);

            Storage::disk('local')->makeDirectory(dirname($relativeMerged));
            $targetDocx = Storage::disk('local')->path($relativeMerged);
            if (! @rename($stagingDocx, $targetDocx)) {
                if (! @copy($stagingDocx, $targetDocx)) {
                    throw new RuntimeException('Gagal menyimpan file DOCX tergabung.');
                }
            }

            return $relativeMerged;
        } finally {
            if (file_exists($stagingDocx)) {
                @unlink($stagingDocx);
            }
        }
    }

    public function generateFinalPdf(BorrowRequest $borrowRequest, BorrowDocument $document): ?string
    {
        if (
            $document->document_status === BorrowDocument::STATUS_FINALIZED &&
            $document->final_pdf_path &&
            Storage::disk('local')->exists($document->final_pdf_path)
        ) {
            return $document->final_pdf_path;
        }

        if (
            $borrowRequest->status !== BorrowRequest::STATUS_APPROVED ||
            in_array($document->document_status, [BorrowDocument::STATUS_REJECTED, BorrowDocument::STATUS_NEEDS_CORRECTION], true)
        ) {
            return null;
        }

        if (! $this->pdfConverter->isAvailable()) {
            $this->markPdfGenerationFailed($document, 'Konverter PDF (LibreOffice) tidak tersedia pada server.', null, null);

            return null;
        }

        $relativeMerged = null;
        $relativePdfDir = 'documents/final';
        $expectedRelativePdf = "{$relativePdfDir}/req_{$borrowRequest->id}_rev_{$document->current_revision}.pdf";
        $relativePdf = null;

        try {
            $relativeMerged = $this->mergeDocx($borrowRequest, $document);
            $docxFullPath = Storage::disk('local')->path($relativeMerged);

            Storage::disk('local')->makeDirectory($relativePdfDir);
            $outputDir = Storage::disk('local')->path($relativePdfDir);

            $generatedPdfFullPath = $this->pdfConverter->convertDocxToPdf($docxFullPath, $outputDir);
            $relativePdf = $relativePdfDir . '/' . basename($generatedPdfFullPath);

            if (! file_exists($generatedPdfFullPath) || (filesize($generatedPdfFullPath) ?: 0) <= 0) {
                throw new RuntimeException('File PDF akhir kosong atau tidak ditemukan.');
            }

            $header = @file_get_contents($generatedPdfFullPath, false, null, 0, 5);
            if ($header !== '%PDF-') {
                throw new RuntimeException('File PDF akhir tidak memiliki header %PDF- yang valid.');
            }

            $sha256 = hash_file('sha256', $generatedPdfFullPath);
            if ($sha256 === false) {
                throw new RuntimeException('Gagal menghitung checksum SHA-256 PDF akhir.');
            }

            $document->update([
                'merged_docx_path' => $relativeMerged,
                'final_pdf_path' => $relativePdf,
                'final_pdf_sha256' => $sha256,
                'document_status' => BorrowDocument::STATUS_FINALIZED,
                'published_at' => now(),
                'pdf_generation_error' => null,
            ]);

            return $relativePdf;
        } catch (\Throwable $e) {
            $this->markPdfGenerationFailed($document, $e->getMessage(), $relativeMerged, $relativePdf ?? $expectedRelativePdf);

            return null;
        }
    }

    public function markPdfGenerationFailed(
        BorrowDocument $document,
        string $errorMessage,
        ?string $relativeMerged = null,
        ?string $relativePdf = null
    ): void {
        if ($document->document_status === BorrowDocument::STATUS_FINALIZED && $document->final_pdf_path) {
            return;
        }

        $pathsToDelete = array_filter([
            $relativeMerged,
            $relativePdf,
            $document->merged_docx_path,
            $document->final_pdf_path,
        ]);

        foreach (array_unique($pathsToDelete) as $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $document->update([
            'merged_docx_path' => null,
            'final_pdf_path' => null,
            'final_pdf_sha256' => null,
            'document_status' => BorrowDocument::STATUS_PDF_GENERATION_FAILED,
            'pdf_generation_error' => mb_substr($errorMessage, 0, 1000),
            'published_at' => null,
        ]);
    }

    private function setRightSignatureBlock(
        TemplateProcessor $processor,
        string $title,
        string $name,
        string $idNumber,
        ?string $signaturePath,
        bool $signatureRequired
    ): void {
        $processor->setValue('right_chair_title', $title);
        $processor->setValue('right_school_line', 'SMK Negeri 4 Padalarang');
        $processor->setValue('right_chair_name_line', "({$name})");
        $processor->setValue('right_chair_id_line', "NIS. {$idNumber}");
        // Backward compatibility with legacy custom templates
        $processor->setValue('right_chair_name', $name);
        $processor->setValue('right_chair_id', $idNumber);

        $this->applyRequiredImageOrEmpty(
            $processor,
            'right_signature',
            $signaturePath,
            ['width' => 110, 'height' => 55, 'ratio' => true],
            $signatureRequired
        );
    }

    private function clearRightSignatureBlock(TemplateProcessor $processor): void
    {
        $processor->setValue('right_chair_title', '');
        $processor->setValue('right_school_line', '');
        $processor->setValue('right_signature', '');
        $processor->setValue('right_chair_name_line', '');
        $processor->setValue('right_chair_id_line', '');
        $processor->setValue('right_chair_name', '');
        $processor->setValue('right_chair_id', '');
    }

    private function clearBottomSignatureBlock(TemplateProcessor $processor): void
    {
        $processor->setValue('bottom_chair_title', '');
        $processor->setValue('bottom_school_line', '');
        $processor->setValue('bottom_signature', '');
        $processor->setValue('bottom_chair_name_line', '');
        $processor->setValue('bottom_chair_id_line', '');
    }

    private function applyRequiredImageOrEmpty(
        TemplateProcessor $processor,
        string $placeholder,
        ?string $relativePath,
        array $size,
        bool $required
    ): void {
        if ($relativePath && Storage::disk('local')->exists($relativePath)) {
            $fullPath = Storage::disk('local')->path($relativePath);
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo === false) {
                throw new RuntimeException("File gambar aset ({$placeholder}) rusak atau tidak valid.");
            }

            $processor->setImageValue($placeholder, array_merge(['path' => $fullPath], $size));

            return;
        }

        if ($required) {
            throw new RuntimeException("File tanda tangan wajib ({$placeholder}) tidak ditemukan.");
        }

        $processor->setValue($placeholder, '');
    }
}
