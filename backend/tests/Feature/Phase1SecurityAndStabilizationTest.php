<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\Facility;
use App\Models\FixedSchedule;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase1SecurityAndStabilizationTest extends TestCase
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
        $sigMpk = UploadedFile::fake()->image('mpk.png', 100, 50)->storeAs('organizations/mpk', 'ttd.png', 'local');
        $sigBasket = UploadedFile::fake()->image('basket.png', 100, 50)->storeAs('organizations/basket', 'ttd.png', 'local');
        $sigFutsal = UploadedFile::fake()->image('futsal.png', 100, 50)->storeAs('organizations/futsal', 'ttd.png', 'local');

        $this->mpkOrg = Organization::create([
            'name' => 'MPK / OSIS',
            'advisor_name' => 'Bu Siti',
            'is_mpk' => true,
            'chairperson_name' => 'Ketua MPK',
            'chairperson_title' => 'Ketua Komisi 2 MPK',
            'signature_image_path' => $sigMpk,
        ]);
        $this->basketOrg = Organization::create([
            'name' => 'Eskul Basket',
            'advisor_name' => 'Pak Budi',
            'chairperson_name' => 'Ketua Basket',
            'chairperson_title' => 'Ketua Eskul Basket',
            'signature_image_path' => $sigBasket,
        ]);
        $this->futsalOrg = Organization::create([
            'name' => 'Eskul Futsal',
            'advisor_name' => 'Pak Andi',
            'chairperson_name' => 'Ketua Futsal',
            'chairperson_title' => 'Ketua Eskul Futsal',
            'signature_image_path' => $sigFutsal,
        ]);

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

        $this->court = Facility::create([
            'name' => 'Lapangan Basket',
            'type' => 'Outdoor',
            'description' => 'Lapangan utama sekolah',
        ]);
    }

    public function test_debug_routes_are_removed(): void
    {
        $this->getJson('/api/test')->assertStatus(404);
        $this->getJson('/api/dns-test')->assertStatus(404);
    }

    public function test_login_logout_and_legacy_user_role_blocking(): void
    {
        $legacyUser = User::create([
            'name' => 'Legacy User',
            'email' => 'legacy@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'organization_id' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => $legacyUser->email,
            'password' => 'password',
        ])->assertStatus(403);

        $loginRes = $this->postJson('/api/login', [
            'email' => 'ketua@basket.com',
            'password' => 'password',
        ])->assertStatus(200)
            ->assertJsonPath('user.organization.name', 'Eskul Basket');

        $token = $loginRes->json('access_token');
        $this->flushSession();

        $this->withToken($token)->getJson('/api/user')->assertStatus(200);
        $this->withToken($token)->postJson('/api/logout')->assertStatus(200);

        // Token was deleted from personal_access_tokens
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', [
                'email' => 'admin@mpk.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'admin@mpk.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_facility_creation_with_description_works_for_mpk_only(): void
    {
        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson('/api/facilities', [
                'name' => 'Studio Musik',
                'type' => 'Indoor',
                'description' => 'Ruang latihan musik',
            ])
            ->assertStatus(403);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/facilities', [
                'name' => 'Studio Musik',
                'type' => 'Indoor',
                'description' => 'Ruang latihan musik',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.description', 'Ruang latihan musik');
    }

    public function test_fixed_schedule_validation_and_authorization(): void
    {
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/fixed-schedules', [
                'facility_id' => $this->court->id,
                'organization_id' => $this->basketOrg->id,
                'day' => 'Funday',
                'start_time' => '15:00',
                'end_time' => '14:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['day', 'end_time']);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/fixed-schedules', [
                'facility_id' => $this->court->id,
                'organization_id' => $this->basketOrg->id,
                'day' => 'Friday',
                'start_time' => '15:00',
                'end_time' => '17:00',
            ])
            ->assertStatus(201);
    }

    public function test_borrow_request_index_does_not_leak_null_owner_requests(): void
    {
        $orglessUser = User::create([
            'name' => 'Orgless Owner',
            'email' => 'orgless@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => null,
        ]);

        // Futsal submits a free-slot request (owner_organization_id = null)
        BorrowRequest::create([
            'user_id' => $this->futsalOwner->id,
            'organization_id' => $this->futsalOrg->id,
            'facility_id' => $this->court->id,
            'owner_organization_id' => null,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'reason' => 'Latihan tambahan',
            'status' => 'pending_mpk',
        ]);

        $response = $this->actingAs($orglessUser, 'sanctum')
            ->getJson('/api/borrow-requests')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
    }

    public function test_borrow_request_status_authorization_and_null_bypass_prevention(): void
    {
        $futureDate = now()->next('Friday')->toDateString();

        FixedSchedule::create([
            'facility_id' => $this->court->id,
            'organization_id' => $this->basketOrg->id,
            'day' => 'Friday',
            'start_time' => '15:00',
            'end_time' => '17:00',
        ]);

        // Futsal submits request overlapping Basket's Friday schedule
        $createRes = $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => $futureDate,
                'start_time' => '15:30',
                'end_time' => '16:30',
                'reason' => 'Sparring Futsal',
                'confirm_signature' => true,
            ])
            ->assertStatus(201);

        $requestId = $createRes->json('data.id');

        // Legacy user role cannot approve even if same organization_id
        $legacyInBasket = User::create([
            'name' => 'Legacy Member',
            'email' => 'member@basket.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'organization_id' => $this->basketOrg->id,
        ]);

        $this->actingAs($legacyInBasket, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/owner-decision", ['decision' => 'approve'])
            ->assertStatus(403);

        // MPK cannot inject invalid screening decision
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/screen", ['decision' => 'hacked_decision'])
            ->assertStatus(422);

        // MPK cannot finalize owned slot before owner approval
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(422);

        // MPK forwards to owner via screening
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/screen", ['decision' => 'accept', 'confirm_signature' => true])
            ->assertStatus(200);

        // Unrelated eskul (Futsal) cannot approve Basket's schedule
        $this->actingAs($this->futsalOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/owner-decision", ['decision' => 'approve', 'confirm_signature' => true])
            ->assertStatus(403);

        // Affected owner (Basket owner_eskul) approves, then MPK finalizes
        $this->actingAs($this->basketOwner, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/owner-decision", ['decision' => 'approve', 'confirm_signature' => true])
            ->assertStatus(200);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson("/api/borrow-requests/{$requestId}/finalize")
            ->assertStatus(200);

        $this->assertDatabaseHas('borrow_requests', [
            'id' => $requestId,
            'status' => 'approved',
        ]);
    }

    public function test_user_controller_manages_owner_eskul_and_revokes_tokens_on_password_reset(): void
    {
        $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Ketua Voli',
                'email' => 'ketua@voli.com',
                'password' => 'secret123',
                'organization_id' => $this->futsalOrg->id,
            ])
            ->assertStatus(201);

        $createdUser = User::where('email', 'ketua@voli.com')->firstOrFail();
        $this->assertSame('owner_eskul', $createdUser->role);

        $createdUser->createToken('auth_token');
        $this->assertCount(1, $createdUser->tokens);

        $this->actingAs($this->adminMpk, 'sanctum')
            ->patchJson("/api/users/{$createdUser->id}/reset-password", [
                'password' => 'newsecret123',
            ])
            ->assertStatus(200);

        $this->assertCount(0, $createdUser->fresh()->tokens);
    }

    public function test_legacy_user_migration_updates_only_rows_with_organization_id(): void
    {
        $withOrg = User::create([
            'name' => 'Old Org Account',
            'email' => 'oldorg@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'organization_id' => $this->futsalOrg->id,
        ]);

        $withoutOrg = User::create([
            'name' => 'Old Personal Account',
            'email' => 'oldpersonal@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'organization_id' => null,
        ]);

        $migration = require database_path('migrations/2026_11_25_000002_migrate_legacy_user_roles_to_owner_eskul.php');
        $migration->up();

        $this->assertSame('owner_eskul', $withOrg->fresh()->role);
        $this->assertSame('user', $withoutOrg->fresh()->role);
    }
}

