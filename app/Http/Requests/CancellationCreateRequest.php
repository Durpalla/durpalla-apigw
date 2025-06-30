<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancellationCreateRequest extends FormRequest
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
            'items' => 'bail|required|array',
            'type' => 'bail|required|in:total,partial,t,p,all'
        ];
    }
}
