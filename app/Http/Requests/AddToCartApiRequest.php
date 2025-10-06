<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartApiRequest extends FormRequest
{
    public function authorize(): true
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => 'bail|required|integer|exists:schedule_cabin_mappings,id',
            'customer_token' => 'bail|nullable|string'
        ];
    }
}
