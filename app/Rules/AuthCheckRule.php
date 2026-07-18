<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Previously blocked mobiles that existed on users (non-customer).
 * Customer identity is now the customers table only – uniqueness is
 * per-table, so merchant/agent/admin mobiles must not block customers.
 */
class AuthCheckRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // No cross-table mobile lock. Intentionally a no-op.
    }
}
