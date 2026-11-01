<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if (! $user->isApproved()) {
            return response()->json([
                'error'          => $user->account_status === \App\Enums\AccountStatus::Pending
                                        ? 'account_pending'
                                        : 'account_disabled',
                'account_status' => $user->account_status->value,
            ], 403);
        }

        return $next($request);
    }
}
