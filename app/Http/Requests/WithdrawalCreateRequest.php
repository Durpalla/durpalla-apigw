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
        $min = max(1, (float) getOption('withdrawal_limit_agent', 100));
        $max = max($min, (float) getOption('withdrawal_max_agent', 5000));

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
            'amount' => [
                'bail',
                'required',
                'numeric',
                'min:'.$min,
                'max:'.$max,
                'lte:balance',
            ],
        ];
    }

    public function messages(): array
    {
        $min = max(1, (float) getOption('withdrawal_limit_agent', 100));
        $max = max($min, (float) getOption('withdrawal_max_agent', 5000));

        return [
            'amount.min' => __('Minimum withdrawal amount is :min', ['min' => $min]),
            'amount.max' => __('Maximum withdrawal amount is :max', ['max' => $max]),
            'amount.lte' => __('Amount cannot exceed available balance'),
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
