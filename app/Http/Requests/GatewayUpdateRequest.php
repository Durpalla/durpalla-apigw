<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GatewayUpdateRequest extends FormRequest
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
            'name' => 'bail|required|string|unique:gateways,name,' . $this->gateway,
            'description' => 'bail|nullable|string',
            'attachment' => 'bail|mimes:jpg,png,gif,jpeg',
            'status' => 'bail|required|numeric|in:1,0'
        ];
    }
}
