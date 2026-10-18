<?php

namespace App\Rules;

use App\Support\AccountEmail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Validation rule: the email may not belong to any other student or staff account.
// Pass the type ('student' | 'staff') and id of the record being edited so it
// does not clash with itself.
class UniqueAccountEmail implements ValidationRule
{
    public function __construct(private ?string $ignoreType = null, private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && $value !== '' && AccountEmail::takenByOther((string) $value, $this->ignoreType, $this->ignoreId)) {
            $fail(AccountEmail::MESSAGE);
        }
    }
}