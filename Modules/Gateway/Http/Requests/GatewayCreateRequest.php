<?php

namespace Modules\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GatewayCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'class_name' => 'required|string|unique:gateways,class_name',
            'status' => 'required|in:1,0'
        ];
    }

    public function authorize(): bool
    {
        return auth()->check();
    }
}
