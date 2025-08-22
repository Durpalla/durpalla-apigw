<?php

namespace Modules\Cart\App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Bundle\App\Rules\BundleActiveRule;
use Modules\Bundle\App\Rules\BundlePurchaseLimitRule;
use Modules\Cart\App\Rules\CartParamValidationRule;
use Modules\Operator\App\Rules\OperatorActiveRule;

class CartValidateRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'operator_id' => ['bail', 'required', 'integer', 'exists:operators,id', new OperatorActiveRule()],
            'product_id' => [
                'bail',
                'required',
                'integer',
                'exists:bundles,id',
                new BundleActiveRule(),
                new BundlePurchaseLimitRule()
            ],
            'qty' => 'bail|required|integer|min:1|max:20',
            'params' => ['bail', 'nullable', 'array', new CartParamValidationRule()]
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
