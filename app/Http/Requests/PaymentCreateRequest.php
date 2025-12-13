<?php

namespace App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class PaymentCreateRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'order_id' => 'bail|required|integer|exists:bookings,id',
            'gateway_id' => 'bail|required|integer|exists:gateways,id',
        ];
    }
}
