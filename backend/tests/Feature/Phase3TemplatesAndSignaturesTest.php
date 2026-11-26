<?php

namespace Tests\Feature;

use App\Models\BorrowDocument;
use App\Models\BorrowRequest;
use App\Models\DocumentSignature;
use App\Models\DocumentTemplate;
use App\Models\Facility;
use App\Models\FixedSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use Tests\TestCase;

class Phase3TemplatesAndSignaturesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $mpkOrg;
    private Organization $basketOrg;
    private Organization $futsalOrg;
    private User $adminMpk;
    private User $basketOwner;
    private User $futsalOwner;
    private Facility $court;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->app->instance(\App\Contracts\PdfConverterInterface::class, new class implements \App\Contracts\PdfConverterInterface {
            public function isAvailable(): bool
            {
                return true;
            }

            public function convertDocxToPdf(string $docxFullPath, string $outputDirectory): string
            {
                if (! is_dir($outputDirectory)) {
                    mkdir($outputDirectory, 0775, true);
                }
                $target = $outputDirectory . DIRECTORY_SEPARATOR . pathinfo($docxFullPath, PATHINFO_FILENAME) . '.pdf';
                file_put_contents($target, "%PDF-1.4\n% Phase3 Stub PDF\n%%EOF\n");

                return $target;
            }
        });

        $this->mpkOrg = Organization::create(['name' => 'MPK / OSIS', 'advisor_name' => 'Bu Siti', 'is_mpk' => true]);
        $this->basketOrg = Organization::create(['name' => 'Eskul Basket', 'advisor_name' => 'Pak Budi']);
        $this->futsalOrg = Organization::create(['name' => 'Eskul Futsal', 'advisor_name' => 'Pak Andi']);

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

        $this->court = Facility::create(['name' => 'Lapangan Basket', 'type' => 'Outdoor']);

        FixedSchedule::create([
            'facility_id' => $this->court->id,
            'organization_id' => $this->basketOrg->id,
            'day' => 'Friday',
            'start_time' => '15:00',
            'end_time' => '17:00',
        ]);
    }

    private function completeOrgSigningProfile(User $actor, string $name, string $title, string $nis): void
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

    public function test_organization_profile_assets_are_private_and_access_controlled(): void
    {
        $this->completeOrgSigningProfile($this->futsalOwner, 'Bima Sakti', 'Ketua Futsal', '23241001');

        $this->actingAs($this->futsalOwner, 'sanctum')
            ->getJson('/api/organization/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.is_complete_for_signing', true);

        // Unrelated organization cannot download Futsal's signature image
        $this->actingAs($this->basketOwner, 'sanctum')
            ->get("/api/organizations/{$this->futsalOrg->id}/signature")
            ->assertStatus(403);

        // Owner and MPK can stream signature with no-store private cache control
        $ownerRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->get("/api/organizations/{$this->futsalOrg->id}/signature")
            ->assertStatus(200);
        $this->assertStringContainsString('no-store', $ownerRes->headers->get('Cache-Control'));

        $this->actingAs($this->adminMpk, 'sanctum')
            ->get("/api/organizations/{$this->futsalOrg->id}/signature")
            ->assertStatus(200);
    }

    public function test_master_docx_template_generation_validation_and_access_control(): void
    {
        // Non-MPK cannot list, generate, upload, or download templates
        $this->actingAs($this->futsalOwner, 'sanctum')->getJson('/api/document-templates')->assertStatus(403);
        $this->actingAs($this->futsalOwner, 'sanctum')->postJson('/api/document-templates/generate-default')->assertStatus(403);

        // Generate built-in SMKN 4 Padalarang default DOCX template
        $res = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/document-templates/generate-default')
            ->assertStatus(201)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.is_active', true);

        $tpl = DocumentTemplate::findOrFail($res->json('data.id'));
        $processor = new TemplateProcessor(Storage::disk('local')->path($tpl->file_path));
        $vars = $processor->getVariables();
        $this->assertContains('mpk_logo', $vars);
        $this->assertContains('requesting_org_logo', $vars);
        $this->assertContains('mpk_signature', $vars);

        // Non-MPK cannot download template; MPK can download
        $this->actingAs($this->futsalOwner, 'sanctum')->get("/api/document-templates/{$tpl->id}/download")->assertStatus(403);
        $this->actingAs($this->adminMpk, 'sanctum')->get("/api/document-templates/{$tpl->id}/download")->assertStatus(200);

        // Upload invalid DOCX missing required placeholders -> 422
        $badDocxPath = sys_get_temp_dir() . '/bad_template_' . uniqid() . '.docx';
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('Incomplete ${reason}');
        IOFactory::createWriter($phpWord, 'Word2007')->save($badDocxPath);

        $badUpload = new UploadedFile($badDocxPath, 'bad.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $this->actingAs($this->futsalOwner, 'sanctum')
            ->post('/api/document-templates', ['name' => 'Forbidden', 'template' => $badUpload], ['Accept' => 'application/json'])
            ->assertStatus(403);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->post('/api/document-templates', ['name' => 'Invalid Template', 'template' => $badUpload], ['Accept' => 'application/json'])
            ->assertStatus(422);

        @unlink($badDocxPath);
    }

    public function test_workflow_a_three_signatures_finalization_and_immutable_snapshots(): void
    {
        $friday = now()->next('Friday')->toDateString();

        // Incomplete profile cannot sign on submit
        $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Latihan gabungan',
                'confirm_signature' => true,
            ])
            ->assertStatus(422);

        $this->completeOrgSigningProfile($this->futsalOwner, 'Ketua Futsal Lama', 'Ketua Futsal', '1001');
        $this->completeOrgSigningProfile($this->adminMpk, 'Ketua Komisi 2', 'Ketua Komisi 2 MPK', '2001');
        $this->completeOrgSigningProfile($this->basketOwner, 'Ketua Basket', 'Ketua Eskul Basket', '3001');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/document-templates/generate-default')
            ->assertStatus(201);

        // Stage 1: Futsal submits + signs
        $submitRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Latihan gabungan',
                'confirm_signature' => true,
            ])
            ->assertStatus(201);

        $reqId = $submitRes->json('data.id');
        $this->assertCount(1, $submitRes->json('data.document.signatures'));

        // Stage 2: MPK screens + signs
        $screenRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.document.document_status', 'waiting_owner_signature');
        $this->assertCount(2, $screenRes->json('data.document.signatures'));

        // Stage 3: Basket owner approves + signs
        $ownerRes = $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/owner-decision", [
                'decision' => 'approve',
                'confirm_signature' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.document.document_status', 'pending_mpk_verification');
        $this->assertCount(3, $ownerRes->json('data.document.signatures'));

        // Valid finalization succeeds with all 3 required signatures
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/finalize")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.document.document_status', 'finalized');

        // Simulate leadership transition in Futsal: update profile chairperson name
        $this->completeOrgSigningProfile($this->futsalOwner, 'Ketua Futsal Baru', 'Ketua Futsal', '9999');

        // Historical signature snapshot on the document MUST remain 'Ketua Futsal Lama'
        $docRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->getJson("/api/borrow-requests/{$reqId}")
            ->assertStatus(200);

        $this->assertSame('Ketua Futsal Lama', $docRes->json('data.document.signatures.0.signer_name_snapshot'));
    }

    public function test_workflow_b_two_signatures_sequence_and_finalization(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $this->completeOrgSigningProfile($this->adminMpk, 'Ketua Komisi 2', 'Ketua Komisi 2 MPK', '2001');
        $this->completeOrgSigningProfile($this->basketOwner, 'Ketua Basket', 'Ketua Eskul Basket', '3001');

        // Stage 1: MPK submits + signs
        $submitRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Acara sekolah',
                'confirm_signature' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.workflow_type', 'mpk_borrow');

        $reqId = $submitRes->json('data.id');
        $this->assertCount(1, $submitRes->json('data.document.signatures'));

        // Stage 2: Basket owner approves + signs
        $ownerRes = $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/owner-decision", [
                'decision' => 'approve',
                'confirm_signature' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.document.document_status', 'pending_mpk_verification');

        $sigs = $ownerRes->json('data.document.signatures');
        $this->assertCount(2, $sigs);
        $this->assertSame('mpk', $sigs[0]['signer_role']);
        $this->assertSame('owner_eskul', $sigs[1]['signer_role']);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/finalize")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_sign_stage_guards_prevent_rejected_signing_desync_and_null_org_bypass(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $this->completeOrgSigningProfile($this->futsalOwner, 'Ketua Futsal', 'Ketua Futsal', '1001');
        $this->completeOrgSigningProfile($this->adminMpk, 'Ketua Komisi 2', 'Ketua Komisi 2 MPK', '2001');
        $this->completeOrgSigningProfile($this->basketOwner, 'Ketua Basket', 'Ketua Eskul Basket', '3001');

        // 1. Signing a rejected request is rejected (422)
        $rejectedId = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Permintaan ditolak',
                'confirm_signature' => true,
            ])->json('data.id');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$rejectedId}/screen", [
                'decision' => 'reject',
                'rejection_reason' => 'Tidak valid',
            ])->assertStatus(200);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$rejectedId}/sign")
            ->assertStatus(422);

        // 2. Signing while still pending_owner cannot desynchronize statuses
        $pendingId = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Permintaan valid',
                'confirm_signature' => true,
            ])->json('data.id');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$pendingId}/screen", [
                'decision' => 'accept',
                'confirm_signature' => true,
            ])->assertStatus(200);

        // Owner tries /sign before /owner-decision -> blocked 422, statuses remain synchronized
        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$pendingId}/sign")
            ->assertStatus(422);

        $freshReq = BorrowRequest::with('document')->findOrFail($pendingId);
        $this->assertSame('pending_owner', $freshReq->status);
        $this->assertSame('waiting_owner_signature', $freshReq->document->document_status);

        // 3. Actor with null organization_id cannot sign as owner on a free-slot request (403)
        $orglessOwner = User::create([
            'name' => 'Orgless',
            'email' => 'orgless@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => null,
        ]);

        $freeSlotId = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'reason' => 'Slot kosong',
                'confirm_signature' => true,
            ])->json('data.id');

        $this->actingAs($orglessOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$freeSlotId}/sign")
            ->assertStatus(403);
    }

    public function test_finalization_rejects_missing_signatures_for_workflow_a_and_workflow_b(): void
    {
        $friday = now()->next('Friday')->toDateString();

        // Workflow A without signatures -> finalize rejected with 422
        $reqA = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Tanpa TTD',
                'confirm_signature' => false,
            ])->json('data.id');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqA}/screen", ['decision' => 'accept', 'confirm_signature' => false])
            ->assertStatus(200);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqA}/owner-decision", ['decision' => 'approve', 'confirm_signature' => false])
            ->assertStatus(200);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqA}/finalize")
            ->assertStatus(422);

        // Workflow B without signatures -> finalize rejected with 422
        $reqB = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '16:00',
                'end_time' => '17:00',
                'reason' => 'MPK Tanpa TTD',
                'confirm_signature' => false,
            ])->json('data.id');

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqB}/owner-decision", ['decision' => 'approve', 'confirm_signature' => false])
            ->assertStatus(200);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqB}/finalize")
            ->assertStatus(422);
    }

    public function test_duplicate_signing_db_uniqueness_and_active_signatures_revision_isolation(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $this->completeOrgSigningProfile($this->futsalOwner, 'Ketua Futsal', 'Ketua Futsal', '1001');
        $this->completeOrgSigningProfile($this->adminMpk, 'Ketua Komisi 2', 'Ketua Komisi 2 MPK', '2001');

        $reqId = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Uji duplikasi TTD',
                'confirm_signature' => true,
            ])->json('data.id');

        // Replayed / duplicate Stage 1 signing is rejected (422)
        $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/sign")
            ->assertStatus(422);

        // Database-level unique constraint blocks duplicate (borrow_document_id, revision_number, stage_order)
        $doc = BorrowDocument::where('borrow_request_id', $reqId)->firstOrFail();

        $this->expectException(QueryException::class);
        DocumentSignature::create([
            'borrow_document_id' => $doc->id,
            'revision_number' => 1,
            'signer_role' => 'requester_eskul',
            'stage_order' => 1,
            'organization_id' => $this->futsalOrg->id,
            'signed_by_user_id' => $this->futsalOwner->id,
            'signer_name_snapshot' => 'Dup',
            'signer_title_snapshot' => 'Dup',
            'signature_image_path_snapshot' => 'dup.png',
            'is_valid' => true,
            'signed_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_active_signatures_only_returns_current_revision_while_preserving_history(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $this->completeOrgSigningProfile($this->futsalOwner, 'Ketua Futsal', 'Ketua Futsal', '1001');
        $this->completeOrgSigningProfile($this->adminMpk, 'Ketua Komisi 2', 'Ketua Komisi 2 MPK', '2001');

        $reqId = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Uji revisi',
                'confirm_signature' => true,
            ])->json('data.id');

        $doc = BorrowDocument::where('borrow_request_id', $reqId)->firstOrFail();
        $sigService = app(SignatureService::class);

        // Simulate revision 2: invalidate old signatures, bump revision, re-sign stage 1
        $sigService->invalidateSignatures($doc);
        $doc->update(['current_revision' => 2]);
        $sigService->sign($doc->fresh(), $this->futsalOwner, 'requester_eskul', 1);

        // Historical revision 1 signature remains in DB
        $this->assertDatabaseCount('document_signatures', 2);
        $this->assertDatabaseHas('document_signatures', [
            'borrow_document_id' => $doc->id,
            'revision_number' => 1,
            'is_valid' => false,
        ]);

        // Even if a prior revision signature were still marked is_valid=true, activeSignatures() filters strictly by current_revision
        DocumentSignature::where('borrow_document_id', $doc->id)
            ->where('revision_number', 1)
            ->update(['is_valid' => true]);

        $loadedLazy = $doc->fresh()->activeSignatures;
        $this->assertCount(1, $loadedLazy);
        $this->assertSame(2, (int) $loadedLazy->first()->revision_number);

        $loadedEager = BorrowDocument::with('activeSignatures')->findOrFail($doc->id)->activeSignatures;
        $this->assertCount(1, $loadedEager);
        $this->assertSame(2, (int) $loadedEager->first()->revision_number);
    }
}
