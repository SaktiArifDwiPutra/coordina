<?php

namespace Tests\Feature;

use App\Contracts\PdfConverterInterface;
use App\Jobs\GenerateFinalPermissionPdfJob;
use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Models\DocumentRevision;
use App\Models\DocumentSignature;
use App\Models\Facility;
use App\Models\FixedSchedule;
use App\Models\LetterCounter;
use App\Models\Organization;
use App\Models\User;
use App\Services\LibreOfficePdfConverter;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class Phase4PdfVerificationAndArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Organization $mpkOrg;
    private Organization $basketOrg;
    private Organization $futsalOrg;
    private Organization $paskibraOrg;
    private User $adminMpk;
    private User $basketOwner;
    private User $futsalOwner;
    private User $paskibraOwner;
    private Facility $court;
    private string $nextFriday;
    private string $nextTuesday;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->mpkOrg = Organization::create(['name' => 'MPK / OSIS', 'advisor_name' => 'Bu Siti', 'is_mpk' => true]);
        $this->basketOrg = Organization::create(['name' => 'Eskul Basket', 'advisor_name' => 'Pak Budi']);
        $this->futsalOrg = Organization::create(['name' => 'Eskul Futsal', 'advisor_name' => 'Pak Andi']);
        $this->paskibraOrg = Organization::create(['name' => 'Paskibra', 'advisor_name' => 'Pak Dedi']);

        $this->adminMpk = User::create([
            'name' => 'Admin MPK',
            'email' => 'admin@mpk.com',
            'password' => Hash::make('password'),
            'role' => 'admin_mpk',
            'organization_id' => $this->mpkOrg->id,
        ]);

        $this->basketOwner = User::create([
            'name' => 'Ketua Basket',
            'email' => 'ketua@basket.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => $this->basketOrg->id,
        ]);

        $this->futsalOwner = User::create([
            'name' => 'Ketua Futsal',
            'email' => 'ketua@futsal.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => $this->futsalOrg->id,
        ]);

        $this->paskibraOwner = User::create([
            'name' => 'Ketua Paskibra',
            'email' => 'ketua@paskibra.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => $this->paskibraOrg->id,
        ]);

        $this->court = Facility::create(['name' => 'Lapangan Basket', 'type' => 'Outdoor']);

        FixedSchedule::create([
            'facility_id' => $this->court->id,
            'organization_id' => $this->basketOrg->id,
            'day' => 'Friday',
            'start_time' => '15:00',
            'end_time' => '17:00',
        ]);

        $this->nextFriday = Carbon::now()->next(Carbon::FRIDAY)->toDateString();
        $this->nextTuesday = Carbon::now()->next(Carbon::TUESDAY)->toDateString();

        $this->completeOrgProfile($this->adminMpk, 'Naufal MPK', 'Ketua Komisi 2', '232410001');
        $this->completeOrgProfile($this->basketOwner, 'Raka Basket', 'Ketua Eskul Basket', '232410002');
        $this->completeOrgProfile($this->futsalOwner, 'Dimas Futsal', 'Ketua Eskul Futsal', '232410003');
        $this->completeOrgProfile($this->paskibraOwner, 'Bima Paskibra', 'Ketua Paskibra', '232410004');
    }

    private function completeOrgProfile(User $actor, string $name, string $title, string $nis): void
    {
        $this->actingAs($actor, 'sanctum')
            ->putJson('/api/organization/profile', [
                'chairperson_name' => $name,
                'chairperson_title' => $title,
                'chairperson_id_number' => $nis,
            ])
            ->assertStatus(200);

        $this->actingAs($actor, 'sanctum')
            ->post('/api/organization/assets', [
                'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
                'signature_image' => UploadedFile::fake()->image('ttd.png', 200, 100),
            ])
            ->assertStatus(200);
    }

    private function bindFakePdfConverter(bool $available = true, ?string $mode = 'valid'): object
    {
        $converter = new class($available, $mode) implements PdfConverterInterface {
            public function __construct(
                public bool $available,
                public ?string $mode = 'valid'
            ) {}

            public function isAvailable(): bool
            {
                return $this->available;
            }

            public function convertDocxToPdf(string $docxFullPath, string $outputDirectory): string
            {
                if (! is_dir($outputDirectory)) {
                    mkdir($outputDirectory, 0775, true);
                }

                $target = $outputDirectory . DIRECTORY_SEPARATOR . pathinfo($docxFullPath, PATHINFO_FILENAME) . '.pdf';

                if ($this->mode === 'throw') {
                    file_put_contents($target, 'partial');
                    throw new RuntimeException('Simulated LibreOffice crash');
                }

                if ($this->mode === 'empty') {
                    file_put_contents($target, '');

                    return $target;
                }

                if ($this->mode === 'invalid_header') {
                    file_put_contents($target, 'NOT_A_PDF_FILE');

                    return $target;
                }

                file_put_contents($target, "%PDF-1.4\n% Coordina Test PDF for " . basename($docxFullPath) . "\n%%EOF\n");

                return $target;
            }
        };

        $this->app->instance(PdfConverterInterface::class, $converter);

        return $converter;
    }

    private function readDocxXml(string $relativePath): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($relativePath)) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    private function advanceWorkflowAToOwnerApproved(string $reason = 'Latihan sparing persiapan O2SN'): int
    {
        $createRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $this->nextFriday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => $reason,
                'confirm_signature' => true,
            ])
            ->assertStatus(201);

        $requestId = (int) $createRes->json('data.id');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => true,
            ])
            ->assertStatus(200);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/owner-decision", [
                'decision' => 'approve',
                'confirm_signature' => true,
            ])
            ->assertStatus(200);

        return $requestId;
    }

    public function test_workflow_a_finalization_merges_three_signatures_assigns_letter_number_and_streams_pdf(): void
    {
        $this->bindFakePdfConverter(true);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/document-templates/generate-default')
            ->assertStatus(201);

        $requestId = $this->advanceWorkflowAToOwnerApproved();

        $finalizeRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(200);

        $year = now()->format('Y');
        $expectedNumber = "001/MPK/{$year}";

        $finalizeRes->assertJsonPath('data.status', BorrowRequest::STATUS_APPROVED);
        $finalizeRes->assertJsonPath('data.letter_number', $expectedNumber);
        $finalizeRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_FINALIZED);

        $mergedDocxPath = $finalizeRes->json('data.document.merged_docx_path');
        $finalPdfPath = $finalizeRes->json('data.document.final_pdf_path');
        $finalSha256 = $finalizeRes->json('data.document.final_pdf_sha256');

        $this->assertNotNull($mergedDocxPath);
        $this->assertNotNull($finalPdfPath);
        $this->assertTrue(Storage::disk('local')->exists($mergedDocxPath));
        $this->assertTrue(Storage::disk('local')->exists($finalPdfPath));
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($finalPdfPath)), $finalSha256);

        $xml = $this->readDocxXml($mergedDocxPath);
        $this->assertStringContainsString($expectedNumber, $xml);
        $this->assertStringContainsString('Eskul Basket', $xml);
        $this->assertStringContainsString('Eskul Futsal', $xml);
        $this->assertStringContainsString('Naufal MPK', $xml);
        $this->assertStringContainsString('Dimas Futsal', $xml);
        $this->assertStringContainsString('Raka Basket', $xml);
        $this->assertStringContainsString('SMK Negeri 4 Padalarang', $xml);
        $this->assertDoesNotMatchRegularExpression('/\$\{[a-z0-9_]+\}/i', $xml);

        $downloadRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->get("/api/borrow-requests/{$requestId}/document/pdf")
            ->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $downloadRes->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $downloadRes->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', (string) $downloadRes->headers->get('Content-Disposition'));

        $inlineRes = $this->actingAs($this->basketOwner, 'sanctum')
            ->get("/api/borrow-requests/{$requestId}/document/pdf?inline=1")
            ->assertStatus(200);
        $this->assertStringContainsString('inline', (string) $inlineRes->headers->get('Content-Disposition'));

        $this->actingAs($this->adminMpk, 'sanctum')
            ->get("/api/borrow-requests/{$requestId}/document/docx")
            ->assertStatus(200);
    }

    public function test_workflow_b_finalization_merges_two_signatures_without_third_block_and_increments_letter_number(): void
    {
        $this->bindFakePdfConverter(true);

        $reqA = $this->advanceWorkflowAToOwnerApproved('Agenda Futsal');
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqA}/finalize")
            ->assertStatus(200);

        $secondFriday = Carbon::parse($this->nextFriday)->addWeek()->toDateString();
        $createB = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $secondFriday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Upacara Pembukaan P5 Sekolah',
                'confirm_signature' => true,
            ])
            ->assertStatus(201);

        $reqB = (int) $createB->json('data.id');

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqB}/owner-decision", [
                'decision' => 'approve',
                'confirm_signature' => true,
            ])
            ->assertStatus(200);

        $finalizeB = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqB}/finalize")
            ->assertStatus(200);

        $year = now()->format('Y');
        $finalizeB->assertJsonPath('data.letter_number', "002/MPK/{$year}");

        $xml = $this->readDocxXml($finalizeB->json('data.document.merged_docx_path'));
        $this->assertStringContainsString("002/MPK/{$year}", $xml);
        $this->assertStringContainsString('Naufal MPK', $xml);
        $this->assertStringContainsString('Raka Basket', $xml);
        $this->assertStringNotContainsString('Dimas Futsal', $xml);
        $this->assertStringNotContainsString('NIS. -', $xml);
        $this->assertDoesNotMatchRegularExpression('/\$\{[a-z0-9_]+\}/i', $xml);
    }

    public function test_free_slot_workflows_for_mpk_and_eskul_assign_numbers_use_clean_layouts_and_generate_pdf(): void
    {
        $this->bindFakePdfConverter(true);
        $year = now()->format('Y');

        // 1. MPK free-slot without confirm_signature is rejected (422)
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $this->nextTuesday,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'reason' => 'Apel pagi MPK',
                'confirm_signature' => false,
            ])
            ->assertStatus(422);

        // 2. MPK free-slot with confirm_signature succeeds -> 1 signature, 001/MPK/YEAR, PDF generated
        $mpkFreeRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $this->nextTuesday,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'reason' => 'Apel pagi MPK resmi',
                'confirm_signature' => true,
            ])
            ->assertStatus(201);

        $mpkFreeRes->assertJsonPath('data.workflow_type', BorrowRequest::WORKFLOW_FREE_SLOT);
        $mpkFreeRes->assertJsonPath('data.status', BorrowRequest::STATUS_APPROVED);
        $mpkFreeRes->assertJsonPath('data.letter_number', "001/MPK/{$year}");
        $mpkFreeRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_FINALIZED);
        $this->assertNotNull($mpkFreeRes->json('data.document.final_pdf_path'));

        // Generated DOCX for 1-sig MPK free slot has ONLY left MPK block and NO fake '()' / 'NIS.' right/bottom blocks
        $mpkXml = $this->readDocxXml($mpkFreeRes->json('data.document.merged_docx_path'));
        $this->assertStringContainsString('Naufal MPK', $mpkXml);
        $this->assertStringNotContainsString('()', $mpkXml);
        $this->assertStringNotContainsString('NIS. -', $mpkXml);
        $this->assertDoesNotMatchRegularExpression('/\$\{[a-z0-9_]+\}/i', $mpkXml);

        // 3. Eskul free-slot submitted without signature, signed later via /sign, then accepted by MPK -> 2 signatures, 002/MPK/YEAR
        $eskulFreeRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $this->nextTuesday,
                'start_time' => '13:00',
                'end_time' => '14:30',
                'reason' => 'Latihan tambahan slot kosong',
                'confirm_signature' => false,
            ])
            ->assertStatus(201);

        $eskulFreeId = (int) $eskulFreeRes->json('data.id');

        // Screen accept blocked until requester stage 1 signed
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$eskulFreeId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => true,
            ])
            ->assertStatus(422);

        // Requester signs Stage 1 on free_slot via /sign
        $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$eskulFreeId}/sign")
            ->assertStatus(200);

        // Screen accept without MPK confirm_signature blocked (422)
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$eskulFreeId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => false,
            ])
            ->assertStatus(422);

        // Screen accept with MPK signature approves, numbers 002/MPK/YEAR, and produces 2-sig PDF
        $eskulApproved = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$eskulFreeId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => true,
            ])
            ->assertStatus(200);

        $eskulApproved->assertJsonPath('data.status', BorrowRequest::STATUS_APPROVED);
        $eskulApproved->assertJsonPath('data.letter_number', "002/MPK/{$year}");
        $eskulApproved->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_FINALIZED);

        $eskulXml = $this->readDocxXml($eskulApproved->json('data.document.merged_docx_path'));
        $this->assertStringContainsString('Naufal MPK', $eskulXml);
        $this->assertStringContainsString('Dimas Futsal', $eskulXml);
        $this->assertStringNotContainsString('NIS. -', $eskulXml);
        $this->assertDoesNotMatchRegularExpression('/\$\{[a-z0-9_]+\}/i', $eskulXml);
    }

    public function test_rejected_request_never_produces_pdf_or_letter_number(): void
    {
        $this->bindFakePdfConverter(true);

        $createRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $this->nextFriday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Latihan tambahan',
                'confirm_signature' => true,
            ])
            ->assertStatus(201);

        $requestId = (int) $createRes->json('data.id');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => true,
            ])
            ->assertStatus(200);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/owner-decision", [
                'decision' => 'reject',
                'rejection_reason' => 'Jadwal seleksi tim kota.',
            ])
            ->assertStatus(200);

        $finalizeRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(200);

        $finalizeRes->assertJsonPath('data.status', BorrowRequest::STATUS_REJECTED);
        $finalizeRes->assertJsonPath('data.letter_number', null);
        $finalizeRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_REJECTED);
        $finalizeRes->assertJsonPath('data.document.final_pdf_path', null);
        $finalizeRes->assertJsonPath('data.document.merged_docx_path', null);

        $this->actingAs($this->adminMpk, 'sanctum')->getJson("/api/borrow-requests/{$requestId}/document/pdf")->assertStatus(404);
        $this->actingAs($this->adminMpk, 'sanctum')->getJson("/api/borrow-requests/{$requestId}/document/docx")->assertStatus(404);
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/regenerate-pdf")->assertStatus(422);
    }

    public function test_finalized_pdf_and_sha256_remain_immutable_when_template_or_org_profile_changes(): void
    {
        $this->bindFakePdfConverter(true);

        $requestId = $this->advanceWorkflowAToOwnerApproved();
        $finalizeRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(200);

        $originalPdfPath = $finalizeRes->json('data.document.final_pdf_path');
        $originalSha256 = $finalizeRes->json('data.document.final_pdf_sha256');
        $originalContent = Storage::disk('local')->get($originalPdfPath);

        $this->actingAs($this->adminMpk, 'sanctum')->postJson('/api/document-templates/generate-default')->assertStatus(201);
        $this->completeOrgProfile($this->adminMpk, 'Ketua Baru MPK', 'Ketua Komisi 2 Baru', '999999');
        $this->completeOrgProfile($this->basketOwner, 'Ketua Baru Basket', 'Ketua Eskul Baru', '888888');

        $doc = BorrowDocument::where('borrow_request_id', $requestId)->firstOrFail();
        $this->assertSame($originalPdfPath, $doc->final_pdf_path);
        $this->assertSame($originalSha256, $doc->final_pdf_sha256);
        $this->assertSame($originalContent, Storage::disk('local')->get($originalPdfPath));

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/regenerate-pdf")
            ->assertStatus(422);
    }

    public function test_mpk_request_correction_invalidates_signatures_clears_revision_artifacts_and_restarts_workflow(): void
    {
        $this->bindFakePdfConverter(true);

        $requestId = $this->advanceWorkflowAToOwnerApproved('Alasan awal terlalu singkat');

        $corrRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/request-correction", [
                'reason' => 'Harap perjelas agenda kegiatan dan sesuaikan jam selesai.',
            ])
            ->assertStatus(200);

        $corrRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_NEEDS_CORRECTION);
        $corrRes->assertJsonPath('data.document.current_revision', 2);

        $doc = BorrowDocument::where('borrow_request_id', $requestId)->firstOrFail();
        $this->assertSame(0, $doc->activeSignatures()->count());
        $this->assertSame(3, DocumentSignature::where('borrow_document_id', $doc->id)->where('is_valid', false)->count());
        $this->assertDatabaseHas('document_revisions', [
            'borrow_document_id' => $doc->id,
            'revision_number' => 1,
            'reason' => 'Harap perjelas agenda kegiatan dan sesuaikan jam selesai.',
        ]);

        // Cannot finalize while needs_correction
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/finalize")->assertStatus(422);

        // Non-requester cannot revise Workflow A
        $this->actingAs($this->paskibraOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/revise", [
                'facility_id' => $this->court->id,
                'date' => $this->nextFriday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Ilegal revise',
            ])
            ->assertStatus(403);

        // Conflicting approved booking blocks revision (409)
        BorrowRequest::create([
            'user_id' => $this->paskibraOwner->id,
            'organization_id' => $this->paskibraOrg->id,
            'facility_id' => $this->court->id,
            'workflow_type' => BorrowRequest::WORKFLOW_FREE_SLOT,
            'date' => $this->nextFriday,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'reason' => 'Approved slot',
            'priority' => 'low',
            'status' => BorrowRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/revise", [
                'facility_id' => $this->court->id,
                'date' => $this->nextFriday,
                'start_time' => '10:30',
                'end_time' => '11:30',
                'reason' => 'Jam bentrok',
                'confirm_signature' => false,
            ])
            ->assertStatus(409);

        // Valid revision WITHOUT confirm_signature, followed by standalone Stage 1 /sign
        $reviseRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/revise", [
                'facility_id' => $this->court->id,
                'date' => $this->nextFriday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Latihan sparing resmi persiapan turnamen tingkat kabupaten.',
                'confirm_signature' => false,
            ])
            ->assertStatus(200);

        $reviseRes->assertJsonPath('data.status', BorrowRequest::STATUS_PENDING_MPK_SCREENING);
        $reviseRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_WAITING_MPK_SCREENING);
        $reviseRes->assertJsonPath('data.document.current_revision', 2);
        $reviseRes->assertJsonPath('data.document.merged_docx_path', null);
        $reviseRes->assertJsonPath('data.document.final_pdf_path', null);

        $this->actingAs($this->futsalOwner, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/sign")->assertStatus(200);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/screen", ['decision' => 'accept', 'confirm_signature' => true])
            ->assertStatus(200);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/owner-decision", ['decision' => 'approve', 'confirm_signature' => true])
            ->assertStatus(200);

        $finalRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(200);

        $finalRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_FINALIZED);
        $this->assertNotNull($finalRes->json('data.document.final_pdf_path'));
        $xml = $this->readDocxXml($finalRes->json('data.document.merged_docx_path'));
        $this->assertStringContainsString('Latihan sparing resmi persiapan turnamen tingkat kabupaten.', $xml);
    }

    public function test_failed_pdf_conversion_does_not_mark_finalized_cleans_artifacts_and_supports_async_queue(): void
    {
        // 1. Unavailable converter -> pdf_generation_failed, NEVER finalized, zero orphan files, not in public archive
        $converter = $this->bindFakePdfConverter(false);
        $requestId = $this->advanceWorkflowAToOwnerApproved('Uji gagal konversi LibreOffice');

        $finalizeRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(200);

        $finalizeRes->assertJsonPath('data.status', BorrowRequest::STATUS_APPROVED);
        $finalizeRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_PDF_GENERATION_FAILED);
        $finalizeRes->assertJsonPath('data.document.merged_docx_path', null);
        $finalizeRes->assertJsonPath('data.document.final_pdf_path', null);
        $finalizeRes->assertJsonPath('data.document.published_at', null);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));

        // Not in official archive & PDF/DOCX downloads return 404 while failed
        $this->actingAs($this->adminMpk, 'sanctum')->getJson('/api/documents/archive')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->actingAs($this->futsalOwner, 'sanctum')->getJson('/api/documents/archive')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->actingAs($this->futsalOwner, 'sanctum')->getJson("/api/borrow-requests/{$requestId}/document/pdf")->assertStatus(404);
        $this->actingAs($this->paskibraOwner, 'sanctum')->getJson("/api/borrow-requests/{$requestId}/document/pdf")->assertStatus(403);
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/regenerate-pdf")->assertStatus(503);

        // 2. Invalid/corrupt PDF output or converter crash cleans up partial files & stays failed
        $converter->available = true;
        $converter->mode = 'invalid_header';
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/regenerate-pdf")->assertStatus(422);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));
        $this->assertSame(BorrowDocument::STATUS_PDF_GENERATION_FAILED, BorrowDocument::where('borrow_request_id', $requestId)->value('document_status'));

        $converter->mode = 'throw';
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/regenerate-pdf")->assertStatus(422);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));

        // 3. Valid converter recovery succeeds and publishes to archive with org isolation
        $converter->mode = 'valid';
        $regenRes = $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/regenerate-pdf")->assertStatus(200);
        $regenRes->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_FINALIZED);

        $this->actingAs($this->adminMpk, 'sanctum')->getJson('/api/documents/archive')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->actingAs($this->futsalOwner, 'sanctum')->getJson('/api/documents/archive')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->actingAs($this->basketOwner, 'sanctum')->getJson('/api/documents/archive')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->actingAs($this->paskibraOwner, 'sanctum')->getJson('/api/documents/archive')->assertStatus(200)->assertJsonCount(0, 'data');

        // 4. Async queue mode sets pending_pdf_generation until GenerateFinalPermissionPdfJob runs
        config(['coordina.async_pdf_generation' => true]);
        Queue::fake();

        $secondFriday = Carbon::parse($this->nextFriday)->addWeek()->toDateString();
        $asyncCreate = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $secondFriday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Agenda async PDF',
                'confirm_signature' => true,
            ])->assertStatus(201);

        $asyncId = (int) $asyncCreate->json('data.id');
        $this->actingAs($this->basketOwner, 'sanctum')->postJson("/api/borrow-requests/{$asyncId}/owner-decision", ['decision' => 'approve', 'confirm_signature' => true])->assertStatus(200);
        $asyncFin = $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$asyncId}/finalize")->assertStatus(200);

        $asyncFin->assertJsonPath('data.status', BorrowRequest::STATUS_APPROVED);
        $asyncFin->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_PENDING_PDF_GENERATION);
        $asyncFin->assertJsonPath('data.document.published_at', null);
        Queue::assertPushed(GenerateFinalPermissionPdfJob::class, fn ($job) => $job->borrowRequestId === $asyncId);

        // Execute queued job manually -> transitions to finalized
        config(['coordina.async_pdf_generation' => false]);
        (new GenerateFinalPermissionPdfJob($asyncId))->handle(app(\App\Services\DocumentGenerationService::class));

        $afterJob = BorrowDocument::where('borrow_request_id', $asyncId)->firstOrFail();
        $this->assertSame(BorrowDocument::STATUS_FINALIZED, $afterJob->document_status);
        $this->assertNotNull($afterJob->final_pdf_path);
        $this->assertNotNull($afterJob->published_at);
    }

    public function test_corrupted_image_and_missing_libreoffice_binary_fail_safely_and_letter_number_is_unique(): void
    {
        // Corrupted signature image stored on disk fails PDF generation safely with zero orphan artifacts
        $this->bindFakePdfConverter(true);
        $this->actingAs($this->futsalOwner, 'sanctum')
            ->post('/api/organization/assets', [
                'logo' => UploadedFile::fake()->createWithContent('corrupt.png', 'not-a-valid-png'),
            ]);
        // Force corrupt file directly in fake storage to simulate damaged asset on disk
        Storage::disk('local')->put($this->futsalOrg->fresh()->signature_image_path, 'corrupted-bytes');

        $requestId = $this->advanceWorkflowAToOwnerApproved('Uji gambar rusak');
        $res = $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$requestId}/finalize")->assertStatus(200);
        $res->assertJsonPath('data.document.document_status', BorrowDocument::STATUS_PDF_GENERATION_FAILED);
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));

        // LibreOfficePdfConverter throws clear exception when binary is missing
        config(['coordina.libreoffice_binary' => '/nonexistent/binary/soffice_missing']);
        $realConverter = new LibreOfficePdfConverter();
        $this->assertFalse($realConverter->isAvailable());
        $this->expectException(RuntimeException::class);
        $realConverter->convertDocxToPdf('/tmp/missing.docx', sys_get_temp_dir());
    }

    public function test_letter_counters_and_unique_letter_number_constraint_prevent_duplicates(): void
    {
        $year = (int) now()->format('Y');
        $counter = LetterCounter::create(['year' => $year, 'last_sequence' => 5]);
        $this->assertSame(5, $counter->last_sequence);

        BorrowRequest::create([
            'user_id' => $this->futsalOwner->id,
            'organization_id' => $this->futsalOrg->id,
            'facility_id' => $this->court->id,
            'workflow_type' => BorrowRequest::WORKFLOW_FREE_SLOT,
            'letter_number' => "006/MPK/{$year}",
            'letter_date' => now()->toDateString(),
            'date' => $this->nextTuesday,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'First unique number',
            'status' => BorrowRequest::STATUS_APPROVED,
        ]);

        $this->expectException(QueryException::class);

        BorrowRequest::create([
            'user_id' => $this->paskibraOwner->id,
            'organization_id' => $this->paskibraOrg->id,
            'facility_id' => $this->court->id,
            'workflow_type' => BorrowRequest::WORKFLOW_FREE_SLOT,
            'letter_number' => "006/MPK/{$year}",
            'letter_date' => now()->toDateString(),
            'date' => $this->nextTuesday,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'reason' => 'Duplicate letter number',
            'status' => BorrowRequest::STATUS_APPROVED,
        ]);
    }
}
