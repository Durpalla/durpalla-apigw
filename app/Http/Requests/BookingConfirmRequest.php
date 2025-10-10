<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'items' => 'bail|required|array',
            'items.item_id' => 'bail|required|integer|exists:schedule_cabin_mappings,id',
            'items.lock_id' => 'bail|required|integer|exists:cabin_locks,id',
        ];
    }
}
