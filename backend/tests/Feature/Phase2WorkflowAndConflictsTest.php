<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\Facility;
use App\Models\FixedSchedule;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase2WorkflowAndConflictsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $mpkOrg;
    private Organization $basketOrg;
    private Organization $futsalOrg;
    private Organization $choirOrg;
    private User $adminMpk;
    private User $basketOwner;
    private User $futsalOwner;
    private User $choirOwner;
    private Facility $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mpkOrg = Organization::create(['name' => 'MPK / OSIS', 'advisor_name' => 'Bu Siti']);
        $this->basketOrg = Organization::create(['name' => 'Eskul Basket', 'advisor_name' => 'Pak Budi']);
        $this->futsalOrg = Organization::create(['name' => 'Eskul Futsal', 'advisor_name' => 'Pak Andi']);
        $this->choirOrg = Organization::create(['name' => 'Paduan Suara', 'advisor_name' => 'Bu Rina']);

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

        $this->choirOwner = User::create([
            'name' => 'Ketua Padus',
            'email' => 'ketua@padus.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => $this->choirOrg->id,
        ]);

        $this->court = Facility::create([
            'name' => 'Lapangan Basket',
            'type' => 'Outdoor',
        ]);

        FixedSchedule::create([
            'facility_id' => $this->court->id,
            'organization_id' => $this->basketOrg->id,
            'day' => 'Friday',
            'start_time' => '15:00',
            'end_time' => '17:00',
        ]);
    }

    public function test_workflow_a_full_approval_with_audit_history_and_notifications(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $submitRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:30',
                'end_time' => '16:30',
                'reason' => 'Latihan persiapan turnamen',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.workflow_type', 'inter_eskul')
            ->assertJsonPath('data.status', 'pending_mpk_screening');

        $reqId = $submitRes->json('data.id');

        // MPK received notification
        $notifRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->getJson('/api/notifications')
            ->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $notifRes->json('unread_count'));

        // MPK screens & accepts
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/screen", ['decision' => 'accept'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_owner');

        // Unrelated Choir cannot view or decide
        $this->actingAs($this->choirOwner, 'sanctum')
            ->getJson("/api/borrow-requests/{$reqId}")
            ->assertStatus(403);

        // MPK cannot make owner decision
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/owner-decision", ['decision' => 'approve'])
            ->assertStatus(403);

        // Basket owner approves
        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/owner-decision", ['decision' => 'approve'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'owner_approved');

        // MPK finalizes administrative recording
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/finalize")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseCount('approval_histories', 4);
    }

    public function test_rejection_requires_reason_at_screening_and_owner_stages(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $req1 = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Candaan saja',
            ])->json('data.id');

        // Rejecting screening without reason fails
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$req1}/screen", ['decision' => 'reject'])
            ->assertStatus(422);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$req1}/screen", [
                'decision' => 'reject',
                'rejection_reason' => 'Alasan tidak serius',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Alasan tidak serius');

        // Second request forwarded to owner and rejected by owner
        $req2 = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Latihan mendadak',
            ])->json('data.id');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$req2}/screen", ['decision' => 'accept'])
            ->assertStatus(200);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$req2}/owner-decision", ['decision' => 'reject'])
            ->assertStatus(422);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$req2}/owner-decision", [
                'decision' => 'reject',
                'rejection_reason' => 'Basket ada jadwal sparing internal',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'owner_rejected');

        // MPK finalizes preserving rejection reason
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$req2}/finalize")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Basket ada jadwal sparing internal');
    }

    public function test_workflow_b_mpk_borrow_skips_screening_and_defers_to_owner(): void
    {
        $friday = now()->next('Friday')->toDateString();

        $submitRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '17:00',
                'reason' => 'Upacara pembukaan lomba sekolah',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.workflow_type', 'mpk_borrow')
            ->assertJsonPath('data.status', 'pending_owner');

        $reqId = $submitRes->json('data.id');

        // Cannot screen a Workflow B request
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/screen", ['decision' => 'accept'])
            ->assertStatus(422);

        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/owner-decision", ['decision' => 'approve'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'owner_approved');

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqId}/finalize")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_conflict_detection_blocks_overlapping_fixed_schedules_and_approved_bookings(): void
    {
        // Overlapping fixed schedule rejected (409)
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/fixed-schedules', [
                'facility_id' => $this->court->id,
                'organization_id' => $this->futsalOrg->id,
                'day' => 'Friday',
                'start_time' => '16:00',
                'end_time' => '18:00',
            ])
            ->assertStatus(409);

        $friday = now()->next('Friday')->toDateString();

        // Self-borrowing rejected (422)
        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Latihan sendiri',
            ])
            ->assertStatus(422);

        // Two requests submitted while none approved yet
        $reqA = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:00',
                'end_time' => '16:30',
                'reason' => 'Futsal',
            ])->json('data.id');

        $reqB = $this->actingAs($this->choirOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '15:30',
                'end_time' => '17:00',
                'reason' => 'Padus',
            ])->json('data.id');

        // Approve reqA completely
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$reqA}/screen", ['decision' => 'accept']);
        $this->actingAs($this->basketOwner, 'sanctum')->postJson("/api/borrow-requests/{$reqA}/owner-decision", ['decision' => 'approve']);
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$reqA}/finalize")->assertStatus(200);

        // Now finalizing or even owner-approving reqB must fail with 409 Conflict
        $this->actingAs($this->adminMpk, 'sanctum')->postJson("/api/borrow-requests/{$reqB}/screen", ['decision' => 'accept']);
        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$reqB}/owner-decision", ['decision' => 'approve'])
            ->assertStatus(409);

        // New submission on already-approved slot fails immediately with 409
        $this->actingAs($this->choirOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $friday,
                'start_time' => '16:00',
                'end_time' => '17:00',
                'reason' => 'Padus lagi',
            ])
            ->assertStatus(409);
    }
}

