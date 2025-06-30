<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WithdrawalCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'agent_payment_method_id' => 'bail|required|exists:agent_payment_methods,id',
            'balance' => 'bail|required|numeric',
            'amount' => 'bail|required|numeric|lte:balance'
        ];
    }

    protected function failedValidation(Validator $validator) {
        $response = [
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()
        ];
        throw new HttpResponseException(response()->json($response, 200));
    }
}
