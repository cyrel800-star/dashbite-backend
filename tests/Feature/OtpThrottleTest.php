<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** PDF REQ-01 AC #3 + W2.01 AC #2 */
    public function test_five_otps_allowed_sixth_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp/request', ['email' => 'a@b.com'])
                ->assertOk();
        }

        $this->postJson('/api/auth/otp/request', ['email' => 'a@b.com'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'otp_throttled');
    }

    /** PDF W2.01 AC #2 — throttled and recorded for review */
    public function test_throttled_otp_is_recorded(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/otp/request', ['email' => 'x@y.com']);
        }

        $this->assertDatabaseHas('otp_requests', [
            'email'   => 'x@y.com',
            'outcome' => 'throttled',
        ]);
    }
}