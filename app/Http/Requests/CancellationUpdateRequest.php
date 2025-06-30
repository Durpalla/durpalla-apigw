<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancellationUpdateRequest extends FormRequest
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
            'booking_cancellation_id' => 'bail|required|numeric|exists:booking_cancellations,id',
            'action_type' => 'bail|required|in:confirm,reject'
        ];
    }
}
