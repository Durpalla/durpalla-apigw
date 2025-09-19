<?php

namespace Modules\Auth\Http\Requests;

use App\Traits\FormValidationResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class UserAccessUnblockRequest extends FormRequest
{
    use FormValidationResponseTrait;

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'exists:user_access_blocks,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
