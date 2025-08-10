<?php

namespace App\Rules;

use App\Models\Ghat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GhatUniqueRule implements ValidationRule
{
    private ?int $ghatId;

    public function __construct($ghatId = null)
    {
        $this->ghatId = $ghatId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $ghat = Ghat::where('service_type', request()->input('service_type'))
                ->where('name', $value);

            if($this->ghatId) {
                $ghat->where('id', '!=', $this->ghatId);
            }

            if ($ghat->exists()) {
                $fail("Ghat with this name already exists.");
            }
        } catch (\Exception $e) {
            $fail($e->getMessage());
        }
    }
}
