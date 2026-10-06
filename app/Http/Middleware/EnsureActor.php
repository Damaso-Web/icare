<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Keeps the two kinds of accounts apart. Sanctum happily resolves ANY token,
 * so without this a student's token would pass `auth:sanctum` on staff routes
 * (and a staff token would pass on student routes).
 *   actor:staff   -> only App\Models\User
 *   actor:student -> only App\Models\Student
 */
class EnsureActor
{
    public function handle(Request $request, Closure $next, string $actor)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $ok = $actor === 'student' ? $user instanceof Student : $user instanceof User;

        if (!$ok) {
            abort(403, 'Unauthorized. You do not have permission to access this resource.');
        }

        return $next($request);
    }
}