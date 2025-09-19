<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use JetBrains\PhpStorm\ArrayShape;
use Modules\Auth\Rules\ValidateAuth2FaRule;

class Login2FaRequest extends FormRequest
{
    #[ArrayShape(['code' => 'string', 'g-recaptcha-response' => 'string'])]
    public function rules(): array
    {
        return [
            'code' => ['required', new ValidateAuth2FaRule()],
            'g-recaptcha-response' => 'required|captcha'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
