<?php

namespace App\Jobs;

use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Services\DocumentGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateFinalPermissionPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $borrowRequestId
    ) {}

    public function handle(DocumentGenerationService $generationService): void
    {
        $borrowRequest = BorrowRequest::with(['document', 'organization', 'ownerOrganization', 'facility'])
            ->findOrFail($this->borrowRequestId);

        $document = $borrowRequest->document;
        if (! $document) {
            return;
        }

        if ($document->document_status === BorrowDocument::STATUS_FINALIZED && $document->final_pdf_path !== null) {
            return;
        }

        $generationService->generateFinalPdf($borrowRequest, $document);
    }

    public function failed(Throwable $exception): void
    {
        $borrowRequest = BorrowRequest::with('document')->find($this->borrowRequestId);
        if (! $borrowRequest || ! $borrowRequest->document) {
            return;
        }

        app(DocumentGenerationService::class)->markPdfGenerationFailed(
            $borrowRequest->document,
            $exception->getMessage()
        );
    }
}
