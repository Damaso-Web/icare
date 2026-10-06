<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Server-side input sanitization. Strips HTML/script tags and control
 * characters from every string in the request so stored data can never carry
 * markup (defence in depth - Vue already escapes output). Passwords, tokens and
 * OTP codes are left untouched. Uploaded files are not affected.
 */
class SanitizeInput
{
    private const SKIP = ['password', 'password_confirmation', 'current_password', 'token', 'otp', 'otp_token'];

    public function handle(Request $request, Closure $next)
    {
        $request->merge($this->clean($request->except(self::SKIP)));

        return $next($request);
    }

    private function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->clean($value);
            } elseif (is_string($value)) {
                $value = strip_tags($value);
                // drop NUL and other control chars, keep tab / newline / CR
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
                $data[$key] = $value;
            }
        }

        return $data;
    }
}