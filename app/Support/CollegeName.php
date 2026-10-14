<?php

namespace App\Support;

use App\Models\College;

// Older accounts saved a college with other spellings ("College of Nursing"
// for the official "College of Nursing (CN)"), while students carry the
// official name. Everything that limits a Dean to "their college" compares
// against all spellings of it, so those Deans still see their students.
// Same rules as resources/js/utils/colleges.js.
class CollegeName
{
    /** The official college (Management > Colleges) a saved name refers to, or null. */
    public static function official(?string $name): ?string
    {
        $raw = trim((string) $name);
        if ($raw === '') {
            return null;
        }
        $names = College::pluck('name')->all();
        $bare  = fn(string $s) => strtolower(trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $s)));

        foreach ($names as $n) {
            if (strcasecmp($n, $raw) === 0) return $n;
        }
        $code = preg_match('/\(([A-Za-z]{2,8})\)\s*$/', $raw, $m) ? $m[1] : (preg_match('/^[A-Za-z]{2,8}$/', $raw) ? $raw : '');
        if ($code !== '') {
            foreach ($names as $n) {
                if (str_ends_with(strtoupper($n), '(' . strtoupper($code) . ')')) return $n;
            }
        }
        foreach ($names as $n) {
            if ($bare($n) === $bare($raw)) return $n;
        }
        return null;
    }

    /** Every spelling to compare with: the saved name and its official name. */
    public static function equivalents(?string $name): array
    {
        return array_values(array_unique(array_filter([trim((string) $name), self::official($name)])));
    }
}
