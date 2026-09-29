<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $userRole = strtolower(trim((string) $user->role));
        $allowed  = array_map(fn ($r) => strtolower(trim($r)), $roles);

        if (!in_array($userRole, $allowed, true)) {
            abort(403, 'Unauthorized. You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
