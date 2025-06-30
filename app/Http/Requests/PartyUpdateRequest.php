<?php

namespace App\Http\Requests;

use App\Models\Party;
use Illuminate\Foundation\Http\FormRequest;

class PartyUpdateRequest extends FormRequest
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
        $party = Party::find($this->party);
        return [
            'name' => 'bail|required|string|max:191',
            'slug' => 'bail|required|string|max:25|unique:parties,slug,' . $this->party,
            'email' => 'bail|email|required|max:191,unique:users,email,' . $party->user_id,
            'mobile' => 'bail|required|regex:/^(01){1}[3456789]{1}(\d){8}$/|unique:users,mobile,' . $party->user_id,
            'services' => 'bail|required|array'
        ];
    }
}
