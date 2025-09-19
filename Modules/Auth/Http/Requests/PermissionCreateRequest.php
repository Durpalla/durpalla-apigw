<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermissionCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string|unique:permissions,name'
        ];
    }

    public function authorize(): bool
    {
        return auth()->check();
    }
}
