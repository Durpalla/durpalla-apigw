<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WithdrawalMethodUpdateRequest extends FormRequest
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
            'type' => 'bail|required|in:bkash,rocket,nagad,bank',
            'account_name' => 'bail|required',
            'account_no' => 'bail|required|unique:agent_payment_methods,account_no',
            'bank_name' => 'bail|nullable|required_if:type,bank',
            'branch' => 'bail|nullable|required_if:type,bank'
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
