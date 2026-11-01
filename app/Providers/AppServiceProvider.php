<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('email') . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    // PDF AC #2: throttled AND recorded for review.
                    \App\Models\OtpRequest::create([
                        'user_id'      => optional($request->user())->id,
                        'email'        => (string) $request->input('email'),
                        'ip_address'   => $request->ip(),
                        'outcome'      => \App\Enums\OtpOutcome::Throttled,
                        'requested_at' => now(),
                    ]);

                    return response()->json([
                        'error'       => 'otp_throttled',
                        'message'     => 'Too many OTP requests. Please try again shortly.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}