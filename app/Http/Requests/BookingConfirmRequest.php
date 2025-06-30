<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingConfirmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_name' => 'bail|required',
            'customer_mobile' => 'bail|required|regex:/^(01){1}[3456789]{1}(\d){8}$/',
            'payment_method' => 'bail|required|in:cash,bkash,rocket,nagad',
            'paid_amount' => 'bail|required|numeric|min:0',
            'trx_id' => 'bail|nullable|string'
        ];
    }
}
