<?php

namespace App\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class PaymentCreateRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function authorize(): bool
    {
        // Route is auth:customer,api — web/mobile customers use Sanctum (customer),
        // legacy clients use Passport (api).
        return auth('customer')->check()
            || auth('api')->check()
            || auth()->check();
    }

    public function rules(): array
    {
        return [
            'order_id' => 'bail|required|integer|exists:bookings,id',
            'gateway_id' => 'bail|required|integer|exists:gateways,id',
        ];
    }
}
