<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PermissionUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string|unique:permissions,name,' . $this->permission->id
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
