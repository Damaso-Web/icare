<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * An account that must change its password (temporary password, reset by an
 * administrator, or just reactivated) can only reach the few endpoints needed
 * to do that. Everything else is refused here, so the frontend cannot be
 * bypassed by calling the API directly.
 */
class EnsurePasswordChanged
{
    private const ALLOWED = [
        'api/logout', 'api/me', 'api/me/password',
        'api/student/logout', 'api/student/me', 'api/student/password',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && !empty($user->must_change_password)) {
            $path = trim($request->path(), '/');
            if (!in_array($path, self::ALLOWED, true) && !str_contains($path, 'notifications')) {
                return response()->json([
                    'message' => 'You must change your password before continuing.',
                    'code'    => 'password_change_required',
                ], 403);
            }
        }
        return $next($request);
    }
}