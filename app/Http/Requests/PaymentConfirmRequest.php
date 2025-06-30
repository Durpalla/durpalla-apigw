<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentConfirmRequest extends FormRequest
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
            'booking_id' => 'bail|required|numeric|exists:bookings,id',
            'bank_tran_id' => 'bail|required|string',
            'paid_amount' => 'bail|required|numeric',
            'store_amount' => 'bail|required|numeric'
        ];
    }
}
