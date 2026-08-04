<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WithdrawalMethodCreateRequest extends FormRequest
{
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
            // Same number allowed across wallet types (e.g. bKash + Nagad);
            // block only duplicate account_no for the same agent + type.
            'account_no' => [
                'bail',
                'required',
                Rule::unique('agent_payment_methods', 'account_no')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()?->id)
                        ->where('type', $this->input('type'));
                }),
            ],
            'bank_name' => 'bail|nullable|required_if:type,bank',
            'branch' => 'bail|nullable|required_if:type,bank',
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
