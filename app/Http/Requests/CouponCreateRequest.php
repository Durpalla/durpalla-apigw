<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CouponCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'bail|required|string|max:191,title',
            'description' => 'bail|nullable|string,content',
            'code' => 'bail|required|unique:coupons,code',
            'discount_amount' => 'bail|required|numeric',
            'discount_type' => 'bail|required|in:percent,flat,fixed',
            'type' => 'bail|required',
            'offer_start' => 'bail|required',
            'offer_end' => 'bail|required',
            'poster' => 'bail|nullable|mimes:jpeg,png,jpg,gif,svg|max:2048|dimensions:min_width=460,min_height=340',
            'is_cabin' => 'bail|nullable|integer',
            'is_seat' => 'bail|nullable|integer',
            'is_deck' => 'bail|nullable|integer',
            'is_offer' => 'bail|nullable',
            'items' => 'bail|nullable'
        ];
    }
}
