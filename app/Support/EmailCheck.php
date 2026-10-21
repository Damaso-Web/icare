<?php

namespace App\Support;

/**
 * Checks that a personal email looks complete, not just "has a dot".
 * Well-known providers must end exactly as the provider does, so
 * "name@gmail.co" is refused as unfinished. Keep in step with
 * resources/js/utils/emailCheck.js.
 */
class EmailCheck
{
    private const PROVIDERS = [
        'gmail' => ['gmail.com'],
        'yahoo' => ['yahoo.com', 'yahoo.com.ph'],
        'outlook' => ['outlook.com', 'outlook.ph'],
        'hotmail' => ['hotmail.com'],
        'icloud' => ['icloud.com'],
        'live' => ['live.com'],
        'proton' => ['proton.me'],
        'protonmail' => ['protonmail.com'],
    ];

    private const TYPOS = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gamil.com' => 'gmail.com', 'gmail.con' => 'gmail.com',
        'yaho.com' => 'yahoo.com', 'yahoo.con' => 'yahoo.com', 'hotmial.com' => 'hotmail.com',
    ];

    public const EXAMPLE = 'Enter a complete email address, like name@gmail.com.';

    /** null when fine, otherwise the message. */
    public static function problem(string $value): ?string
    {
        $v = strtolower(trim($value));
        if (! preg_match('/^([^\s@]+)@([^\s@]+)$/', $v, $m)) {
            return self::EXAMPLE;
        }
        [, $local, $domain] = $m;
        if (str_starts_with($local, '.') || str_ends_with($local, '.') || str_contains($local, '..')) {
            return self::EXAMPLE;
        }
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain) || ! preg_match('/[a-z]{2,}$/', $domain)) {
            return self::EXAMPLE;
        }
        if (isset(self::TYPOS[$domain])) {
            return 'Did you mean ' . $local . '@' . self::TYPOS[$domain] . '? ' . self::EXAMPLE;
        }
        $first = explode('.', $domain)[0];
        if (isset(self::PROVIDERS[$first]) && ! in_array($domain, self::PROVIDERS[$first], true)) {
            return '"' . $domain . '" looks incomplete. Did you mean ' . $local . '@' . self::PROVIDERS[$first][0] . '?';
        }
        return null;
    }
}