<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string|unique:roles,name',
            'permission' => 'bail|required|array'
        ];
    }

    public function authorize(): bool
    {
        return auth()->check();
    }
}
