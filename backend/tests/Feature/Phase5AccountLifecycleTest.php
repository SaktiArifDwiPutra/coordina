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

class Phase5AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $mpkOrg;
    private Organization $basketOrg;
    private User $adminMpk;
    private User $basketOwner;
    private Facility $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mpkOrg = Organization::create(['name' => 'MPK / OSIS', 'advisor_name' => 'Bu Siti', 'is_mpk' => true]);
        $this->basketOrg = Organization::create(['name' => 'Eskul Basket', 'advisor_name' => 'Pak Budi', 'is_mpk' => false]);

        $this->adminMpk = User::create([
            'name' => 'Admin MPK',
            'email' => 'admin@mpk.com',
            'password' => Hash::make('password'),
            'role' => 'admin_mpk',
            'organization_id' => $this->mpkOrg->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->basketOwner = User::create([
            'name' => 'Ketua Basket',
            'email' => 'ketua@basket.com',
            'password' => Hash::make('password'),
            'role' => 'owner_eskul',
            'organization_id' => $this->basketOrg->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->court = Facility::create(['name' => 'Lapangan Basket', 'type' => 'Outdoor']);
    }

    private function withBearerToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_mpk_can_create_eskul_account_with_generated_password_and_forced_first_login_change(): void
    {
        $futsalOrg = Organization::create(['name' => 'Eskul Futsal', 'advisor_name' => 'Pak Andi', 'is_mpk' => false]);

        $createRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Ketua Futsal Baru',
                'email' => 'ketua@futsal.com',
                'generate_password' => true,
                'organization_id' => $futsalOrg->id,
            ])
            ->assertStatus(201);

        $tempPassword = $createRes->json('generated_password');
        $this->assertIsString($tempPassword);
        $this->assertGreaterThanOrEqual(12, strlen($tempPassword));

        $newUser = User::where('email', 'ketua@futsal.com')->firstOrFail();
        $this->assertTrue($newUser->must_change_password);
        $this->assertTrue($newUser->is_active);
        $this->assertNull($newUser->password_changed_at);

        // Login with temporary password succeeds
        $loginRes = $this->postJson('/api/login', [
            'email' => 'ketua@futsal.com',
            'password' => $tempPassword,
        ])->assertStatus(200);

        $token = $loginRes->json('access_token');
        $loginRes->assertJsonPath('user.must_change_password', true);

        // /api/user works, but business endpoints are blocked with 403 MUST_CHANGE_PASSWORD
        $this->withBearerToken($token)->getJson('/api/user')->assertStatus(200);

        $this->withBearerToken($token)
            ->getJson('/api/facilities')
            ->assertStatus(403)
            ->assertJsonPath('code', 'MUST_CHANGE_PASSWORD');

        $this->withBearerToken($token)
            ->postJson('/api/borrow-requests', [
                'facility_id' => $this->court->id,
                'date' => now()->next('Tuesday')->toDateString(),
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Blocked until password changed',
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'MUST_CHANGE_PASSWORD');

        // Wrong current_password rejected
        $this->withBearerToken($token)
            ->postJson('/api/change-password', [
                'current_password' => 'wrongpass',
                'new_password' => 'NewPass123!',
                'new_password_confirmation' => 'NewPass123!',
            ])
            ->assertStatus(422);

        // Create a second stale token to verify other sessions are revoked on password change
        $staleToken = $newUser->createToken('stale')->plainTextToken;

        $changeRes = $this->withBearerToken($token)
            ->postJson('/api/change-password', [
                'current_password' => $tempPassword,
                'new_password' => 'KetuaFutsal2026!',
                'new_password_confirmation' => 'KetuaFutsal2026!',
            ])
            ->assertStatus(200);

        $changeRes->assertJsonPath('user.must_change_password', false);
        $this->assertNotNull($newUser->fresh()->password_changed_at);

        // Stale token was revoked (1 token remaining: current session)
        $this->assertCount(1, $newUser->fresh()->tokens);

        // Current session now has full access
        $this->withBearerToken($token)->getJson('/api/facilities')->assertStatus(200);
        $this->withBearerToken($staleToken)->getJson('/api/user')->assertStatus(401);

        // New login works with new password; old temp password fails
        $this->postJson('/api/login', ['email' => 'ketua@futsal.com', 'password' => $tempPassword])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'ketua@futsal.com', 'password' => 'KetuaFutsal2026!'])->assertStatus(200);
    }

    public function test_mpk_reset_password_generates_handover_password_forces_change_and_revokes_all_tokens(): void
    {
        $oldToken = $this->basketOwner->createToken('old_session')->plainTextToken;
        $this->assertCount(1, $this->basketOwner->tokens);

        // Non-MPK cannot reset passwords
        $this->actingAs($this->basketOwner, 'sanctum')
            ->patchJson("/api/users/{$this->basketOwner->id}/reset-password", ['generate_password' => true])
            ->assertStatus(403);

        // Cannot reset admin_mpk via eskul endpoint
        $this->actingAs($this->adminMpk, 'sanctum')
            ->patchJson("/api/users/{$this->adminMpk->id}/reset-password", ['generate_password' => true])
            ->assertStatus(403);

        $resetRes = $this->actingAs($this->adminMpk, 'sanctum')
            ->patchJson("/api/users/{$this->basketOwner->id}/reset-password", [
                'generate_password' => true,
                'must_change_password' => true,
            ])
            ->assertStatus(200);

        $generated = $resetRes->json('generated_password');
        $this->assertIsString($generated);
        $this->assertGreaterThanOrEqual(12, strlen($generated));

        $fresh = $this->basketOwner->fresh();
        $this->assertTrue($fresh->must_change_password);
        $this->assertNull($fresh->password_changed_at);
        $this->assertCount(0, $fresh->tokens);

        // Prior token no longer accepted
        $this->withBearerToken($oldToken)->getJson('/api/user')->assertStatus(401);
    }

    public function test_mpk_can_toggle_eskul_account_active_status_while_preserving_organization_history(): void
    {
        FixedSchedule::create([
            'facility_id' => $this->court->id,
            'organization_id' => $this->basketOrg->id,
            'day' => 'Friday',
            'start_time' => '15:00',
            'end_time' => '17:00',
        ]);

        $historicRequest = BorrowRequest::create([
            'user_id' => $this->basketOwner->id,
            'organization_id' => $this->basketOrg->id,
            'facility_id' => $this->court->id,
            'workflow_type' => BorrowRequest::WORKFLOW_FREE_SLOT,
            'date' => now()->next('Tuesday')->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'reason' => 'Historical agenda',
            'status' => BorrowRequest::STATUS_APPROVED,
        ]);

        $activeToken = $this->basketOwner->createToken('active')->plainTextToken;

        // Deactivate account
        $this->actingAs($this->adminMpk, 'sanctum')
            ->patchJson("/api/users/{$this->basketOwner->id}/toggle-active")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertCount(0, $this->basketOwner->fresh()->tokens);

        // Login blocked for deactivated account
        $this->postJson('/api/login', [
            'email' => 'ketua@basket.com',
            'password' => 'password',
        ])->assertStatus(403);

        // Stale token rejected
        $this->withBearerToken($activeToken)->getJson('/api/facilities')->assertStatus(401);

        // Organizational history remains intact
        $this->assertDatabaseHas('fixed_schedules', ['organization_id' => $this->basketOrg->id]);
        $this->assertDatabaseHas('borrow_requests', ['id' => $historicRequest->id, 'organization_id' => $this->basketOrg->id]);

        // Reactivate account -> login restored
        $this->actingAs($this->adminMpk, 'sanctum')
            ->patchJson("/api/users/{$this->basketOwner->id}/toggle-active")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/login', [
            'email' => 'ketua@basket.com',
            'password' => 'password',
        ])->assertStatus(200);
    }
}
