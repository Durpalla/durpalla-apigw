<?php

namespace App\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait FormValidationResponseTrait
{
    protected function failedValidation(Validator $validator)
    {
        $response = [
            'status' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors()
        ];
        throw new HttpResponseException(response()->json($response, 422));
    }

    protected function failedAuthorization()
    {
        $response = [
            'status' => false,
            'message' => __('This action is unauthorized!')
        ];
        throw new HttpResponseException(response()->json($response, 403));
    }
}
