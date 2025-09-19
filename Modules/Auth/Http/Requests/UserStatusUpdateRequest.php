<?php

namespace Modules\Auth\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class UserStatusUpdateRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function rules(): array
    {
        return [
            'status' => 'required|in:1,0'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
