<?php

namespace Modules\Cart\App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class CartValidateRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'product_id' => [
                'bail',
                'required',
                'integer',
                'exists:cabins,id',
            ],
            'qty' => 'bail|required|integer|min:1|max:20',
            'params' => ['bail', 'nullable', 'array']
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
