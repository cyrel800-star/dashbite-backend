<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpOutcome;
use App\Http\Controllers\Controller;
use App\Models\OtpRequest;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function request(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Record successful send (throttled path is logged by RateLimiter).
        OtpRequest::create([
            'user_id'      => optional($request->user())->id,
            'email'        => $data['email'],
            'ip_address'   => $request->ip(),
            'outcome'      => OtpOutcome::Sent,
            'requested_at' => now(),
        ]);

        // TODO (W2.01 scope): generate + email OTP.

        return response()->json([
            'message'     => 'OTP sent.',
            'retry_after' => 60,
        ]);
    }
}