<?php

namespace App\Http\Requests;

use App\Rules\BDMobile;
use Illuminate\Foundation\Http\FormRequest;

class QuickBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'customer_name' => 'bail|required',
            'customer_mobile' => ['bail', 'required', new BDMobile()],
            'payment_method' => 'bail|required|in:cash,bkash,rocket,nagad',
            'paid_amount' => 'bail|required|numeric|min:0',
            'trx_id' => 'bail|required_if:payment_method,bkash|required_if:payment_method,rocketrequired_if:payment_method,nagad||nullable|unique:payments,bank_tran_id'
        ];
    }
}
