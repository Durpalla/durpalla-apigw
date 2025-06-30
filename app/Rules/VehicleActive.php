<?php

namespace App\Rules;

use App\Constants\AppConst;
use Illuminate\Contracts\Validation\Rule;
use App\Vehicle;

class VehicleActive implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return (bool) Vehicle::where(['id' => $value, 'status' => AppConst::LAUNCH_ACTIVE])->get()->count();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The selected vehicle is not active';
    }
}
