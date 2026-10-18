<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;

/**
 * One email address belongs to ONE account, across students AND staff
 * (faculty, deans, GCU...). Case does not matter. Used by every place that
 * saves an email, so a duplicate is caught before it is stored.
 */
class AccountEmail
{
    public const MESSAGE = 'This email address is already used by another account. Each account must have its own unique email.';

    /** @var array<string, array{type:string,id:int,student_id:?string}>|null */
    private static ?array $map = null;

    private static function norm(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /** Who owns this email? ['type' => 'student'|'staff', 'id' => int, 'student_id' => ?string] or null. */
    public static function owner(?string $email): ?array
    {
        $email = self::norm($email);
        if ($email === '') {
            return null;
        }
        $s = Student::whereRaw('lower(email) = ?', [$email])->first(['id', 'student_id']);
        if ($s) {
            return ['type' => 'student', 'id' => $s->id, 'student_id' => $s->student_id];
        }
        $u = User::whereRaw('lower(email) = ?', [$email])->first(['id']);
        return $u ? ['type' => 'staff', 'id' => $u->id, 'student_id' => null] : null;
    }

    /** True when the email belongs to an account other than the one given. */
    public static function takenByOther(?string $email, ?string $ignoreType = null, ?int $ignoreId = null): bool
    {
        $o = self::owner($email);
        return $o !== null && !($ignoreType !== null && $o['type'] === $ignoreType && $o['id'] === $ignoreId);
    }

    /**
     * For big imports: every email in use, loaded once (two queries) instead of
     * one lookup per row. Call forget() if accounts change in the same request.
     */
    public static function map(): array
    {
        if (self::$map === null) {
            self::$map = [];
            foreach (User::whereNotNull('email')->get(['id', 'email']) as $u) {
                self::$map[self::norm($u->email)] = ['type' => 'staff', 'id' => $u->id, 'student_id' => null];
            }
            foreach (Student::whereNotNull('email')->get(['id', 'email', 'student_id']) as $s) {
                if (trim((string) $s->email) !== '') {
                    self::$map[self::norm($s->email)] = ['type' => 'student', 'id' => $s->id, 'student_id' => $s->student_id];
                }
            }
        }
        return self::$map;
    }

    public static function forget(): void
    {
        self::$map = null;
    }

    /** Import helper: owner of $email from the preloaded map, or null. */
    public static function mappedOwner(?string $email): ?array
    {
        return self::map()[self::norm($email)] ?? null;
    }
}