<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:roles,name,' . $this->role->id,
            'permission' => 'bail|required|array'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
