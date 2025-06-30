<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleScheduleUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'route_id' => 'bail|required|string|max:191|exists:vehicle_routes,id',
            'vehicle_id' => 'bail|required|integer|exists:vehicles,id',
            'schedule_type' => 'bail|required',
            'schedule_time' => 'bail|required|string',
            'operation_hour' => 'bail|numeric'
        ];
    }
}
