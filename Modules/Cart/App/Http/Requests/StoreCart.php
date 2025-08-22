<?php

namespace Modules\Cart\App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Cart\App\Rules\CartRule;

class StoreCart extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id', new CartRule()],
            'qty' => 'required|integer|min:1|max:10',
            'price' => 'nullable|integer',
            'payload' => 'nullable|array'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
