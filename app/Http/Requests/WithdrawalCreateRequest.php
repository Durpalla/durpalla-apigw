<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WithdrawalCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_payment_method_id' => [
                'bail',
                'required',
                Rule::exists('agent_payment_methods', 'id')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()?->id)
                        ->whereNull('deleted_at');
                }),
            ],
            'balance' => 'bail|required|numeric',
            'amount' => 'bail|required|numeric|lte:balance',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = [
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ];
        throw new HttpResponseException(response()->json($response, 200));
    }
}
