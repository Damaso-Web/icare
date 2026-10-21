<?php

namespace App\Rules;

use App\Support\EmailCheck;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Validation rule: the email must look complete (see App\Support\EmailCheck).
class CompleteEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if ($msg = EmailCheck::problem((string) $value)) {
            $fail($msg);
        }
    }
}