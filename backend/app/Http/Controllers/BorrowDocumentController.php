<?php

namespace App\Http\Controllers;

use App\Contracts\PdfConverterInterface;
use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BorrowDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentGenerationService $documentGenerationService,
        private readonly PdfConverterInterface $pdfConverter
    ) {}

    public function archive(Request $request)
    {
        $user = $request->user();

        if (! in_array($user->role, ['admin_mpk', 'owner_eskul'], true)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $query = BorrowRequest::query()
            ->with([
                'organization:id,name,is_mpk',
                'ownerOrganization:id,name',
                'facility:id,name',
                'document.activeSignatures',
                'document.revisions',
                'document.template:id,name,version',
            ])
            ->where('status', BorrowRequest::STATUS_APPROVED)
            ->whereHas('document', function ($q) {
                $q->where('document_status', BorrowDocument::STATUS_FINALIZED)
                    ->whereNotNull('final_pdf_path');
            });

        if ($user->role === 'owner_eskul') {
            if ($user->organization_id === null) {
                return response()->json(['data' => []]);
            }

            $orgId = (int) $user->organization_id;
            $query->where(function ($sub) use ($orgId) {
                $sub->where('organization_id', $orgId)
                    ->orWhere('owner_organization_id', $orgId);
            });
        }

        if ($request->filled('workflow_type')) {
            $query->where('workflow_type', $request->string('workflow_type')->toString());
        }

        if ($request->filled('search')) {
            $term = '%' . $request->string('search')->toString() . '%';
            $query->where(function ($sub) use ($term) {
                $sub->where('letter_number', 'like', $term)
                    ->orWhere('reason', 'like', $term)
                    ->orWhereHas('facility', fn ($fq) => $fq->where('name', 'like', $term))
                    ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', $term));
            });
        }

        $items = $query->orderByDesc('finalized_at')->orderByDesc('id')->get();

        return response()->json(['data' => $items]);
    }

    public function downloadPdf(Request $request, int $id)
    {
        $borrowRequest = BorrowRequest::with('document')->findOrFail($id);
        Gate::authorize('view', $borrowRequest);

        $document = $borrowRequest->document;
        if (
            ! $document ||
            $document->document_status !== BorrowDocument::STATUS_FINALIZED ||
            ! $document->final_pdf_path ||
            ! Storage::disk('local')->exists($document->final_pdf_path)
        ) {
            return response()->json(['message' => 'Dokumen PDF resmi belum tersedia.'], 404);
        }

        $filename = "surat-izin-{$borrowRequest->id}.pdf";
        $headers = [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, private',
        ];

        if ($request->boolean('inline')) {
            return Storage::disk('local')->response($document->final_pdf_path, $filename, $headers);
        }

        return Storage::disk('local')->download($document->final_pdf_path, $filename, $headers);
    }

    public function downloadDocx(Request $request, int $id)
    {
        $borrowRequest = BorrowRequest::with('document')->findOrFail($id);
        Gate::authorize('view', $borrowRequest);

        $document = $borrowRequest->document;
        if (
            ! $document ||
            $document->document_status !== BorrowDocument::STATUS_FINALIZED ||
            ! $document->merged_docx_path ||
            ! Storage::disk('local')->exists($document->merged_docx_path)
        ) {
            return response()->json(['message' => 'Dokumen DOCX resmi belum tersedia.'], 404);
        }

        $filename = "surat-izin-{$borrowRequest->id}.docx";

        return Storage::disk('local')->download(
            $document->merged_docx_path,
            $filename,
            ['Cache-Control' => 'no-store, private']
        );
    }

    public function regeneratePdf(Request $request, int $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Hanya admin MPK yang dapat memproses ulang PDF resmi.'], 403);
        }

        $borrowRequest = BorrowRequest::with(['document', 'organization', 'ownerOrganization', 'facility'])
            ->findOrFail($id);

        $document = $borrowRequest->document;
        if (
            $borrowRequest->status !== BorrowRequest::STATUS_APPROVED ||
            ! $document ||
            in_array($document->document_status, [
                BorrowDocument::STATUS_REJECTED,
                BorrowDocument::STATUS_NEEDS_CORRECTION,
            ], true)
        ) {
            return response()->json(['message' => 'Hanya permohonan yang sudah disetujui yang dapat diproses menjadi PDF resmi.'], 422);
        }

        if (
            $document->document_status === BorrowDocument::STATUS_FINALIZED &&
            $document->final_pdf_path &&
            Storage::disk('local')->exists($document->final_pdf_path)
        ) {
            return response()->json(['message' => 'PDF resmi sudah tersedia dan bersifat permanen.'], 422);
        }

        if (! $this->pdfConverter->isAvailable()) {
            $this->documentGenerationService->markPdfGenerationFailed(
                $document,
                'Konverter PDF (LibreOffice) tidak tersedia pada server saat ini.'
            );

            return response()->json([
                'message' => 'Konverter PDF (LibreOffice) tidak tersedia pada server saat ini.',
            ], 503);
        }

        $generated = $this->documentGenerationService->generateFinalPdf($borrowRequest, $document);
        $freshDoc = $document->fresh();

        if ($generated === null || $freshDoc->document_status !== BorrowDocument::STATUS_FINALIZED) {
            return response()->json([
                'message' => $freshDoc->pdf_generation_error ?: 'Gagal membuat dokumen PDF resmi.',
            ], 422);
        }

        return response()->json([
            'message' => 'PDF resmi berhasil dibuat.',
            'data' => $borrowRequest->fresh([
                'organization:id,name,is_mpk',
                'ownerOrganization:id,name',
                'facility:id,name',
                'document.activeSignatures',
                'document.revisions',
                'document.template:id,name,version',
            ]),
        ]);
    }
}
