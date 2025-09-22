<?php

namespace Modules\Media\App\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait FineUploaderResponseTraits
{
    protected function failedValidation(Validator $validator)
    {
        $response = [
            'success' => false,
            'error' => __($validator->errors()->first()),
            'message' => __($validator->errors()->first())
        ];
        throw new HttpResponseException(response()->json($response, 200));
    }
}
