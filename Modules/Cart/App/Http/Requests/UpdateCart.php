<?php

namespace Modules\Cart\App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCart extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|integer|min:1|max:10',
            'payload' => 'nullable|array'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
