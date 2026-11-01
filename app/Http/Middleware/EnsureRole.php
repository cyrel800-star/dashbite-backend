<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return response()->json(['error' => 'forbidden_role'], 403);
        }

        return $next($request);
    }
}
