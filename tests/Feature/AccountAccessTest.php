<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    /** PDF W2.01 AC #1 */
    public function test_unapproved_rider_denied_and_availability_unchanged(): void
    {
        $rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Pending,
        ]);

        Sanctum::actingAs($rider);

        $this->postJson('/api/rider/availability')
            ->assertStatus(403)
            ->assertJson(['error' => 'account_pending', 'account_status' => 'pending']);

        // Availability state never touched
        $this->assertDatabaseMissing('users', [
            'id' => $rider->id, 'account_status' => 'approved',
        ]);
    }

    /** PDF REQ-01 AC #2 — pending restaurant denied operational */
    public function test_pending_restaurant_denied_operational(): void
    {
        $rest = User::factory()->create([
            'role'           => UserRole::Restaurant,
            'account_status' => AccountStatus::Pending,
        ]);
        Sanctum::actingAs($rest);

        $this->postJson('/api/restaurant/orders/1/accept')
            ->assertStatus(403)
            ->assertJson(['error' => 'account_pending']);
    }

    /** PDF REQ-01 AC #1 — role-only features */
    public function test_role_mismatch_returns_403(): void
    {
        $customer = User::factory()->create([
            'role'           => UserRole::Customer,
            'account_status' => AccountStatus::Approved,
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/rider/availability')
            ->assertStatus(403)
            ->assertJson(['error' => 'forbidden_role']);
    }

    /** PDF W1.03 — pending rider login shows account state */
    public function test_pending_rider_login_shows_account_state(): void
    {
        $rider = User::factory()->create([
            'role'           => UserRole::Rider,
            'account_status' => AccountStatus::Pending,
            'password'       => bcrypt('secret123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => $rider->email,
            'password' => 'secret123',
        ])->assertOk()
          ->assertJsonPath('user.account_status', 'pending')
          ->assertJsonPath('user.role', 'rider');
    }

    /** PDF W1.03 — generic error on invalid creds */
    public function test_login_invalid_credentials_generic_error(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);
    }
}