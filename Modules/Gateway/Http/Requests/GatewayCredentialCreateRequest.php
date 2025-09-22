<?php

namespace Modules\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GatewayCredentialCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'gateway_id' => 'required|integer|exists:gateways,id',
            'key' => 'required|string',
            'value' => 'required|string'
        ];
    }

    public function authorize(): bool
    {
        return auth()->check();
    }
}
