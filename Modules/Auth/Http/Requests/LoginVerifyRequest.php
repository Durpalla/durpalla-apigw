<?php

namespace Modules\Auth\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class LoginVerifyRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string']
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
