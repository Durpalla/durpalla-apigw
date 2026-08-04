<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WithdrawalMethodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('id');

        return [
            'type' => 'bail|required|in:bkash,rocket,nagad,bank',
            'account_name' => 'bail|required',
            'account_no' => [
                'bail',
                'required',
                Rule::unique('agent_payment_methods', 'account_no')
                    ->ignore($ignoreId)
                    ->where(function ($query) {
                        return $query
                            ->where('user_id', $this->user()?->id)
                            ->where('type', $this->input('type'))
                            ->whereNull('deleted_at');
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
