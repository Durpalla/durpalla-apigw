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
            'items.*.item_id' => 'bail|required|integer|exists:schedule_cabin_mappings,id',
            'items.*.lock_id' => 'bail|required|integer|exists:cabin_locks,id',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'You must provide at least one item.',
            'items.array' => 'Items must be provided as an array.',
            'items.*.item_id.required' => 'Each item must have a valid Item ID.',
            'items.*.item_id.integer' => 'Item ID must be a valid number.',
            'items.*.item_id.exists' => 'The selected cabin does not exist in the schedule.',
            'items.*.lock_id.required' => 'Each item must include a lock reference.',
            'items.*.lock_id.integer' => 'You must be lock the item first the confirm booking',
            'items.*.lock_id.exists' => 'The Item is not locked for you.',
        ];
    }
}
