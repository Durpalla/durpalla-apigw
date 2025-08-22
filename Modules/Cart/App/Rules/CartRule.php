<?php

namespace Modules\Cart\App\Rules;

use App\Helpers\LogHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Product\Entities\Product;

class CartRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $product = Product::find($value);
            if(!$product) {
                $fail('product_id', 'Product not found!');
            }
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'ADD_TO_CART_RULE_EXCEPTION'
            ]);
            $fail(__('Internal server error'));
        }
    }
}
