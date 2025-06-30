<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingConfirmApiRequest extends FormRequest
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
            'items' => 'bail|required|string',
            'coupon' => 'bail|nullable|string',
            'platform' => 'bail|nullable|string',
            'customer_token' => 'bail|string'
        ];
    }
}
