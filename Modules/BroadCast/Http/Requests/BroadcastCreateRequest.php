<?php

namespace Modules\BroadCast\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'title' => 'bail|required|alpha',
            'type' => 'bail|required|in:all,sms,email,message,notification,fcm,topic',
            'group' => 'bail|required|in:all,individual',
            'customers' => 'bail|required_if:group,individual',
            'message' => 'bail|required|max:555',
            'scheduled_at' => 'bail|nullable',
            'topic' => 'bail|required_if:type,topic'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }
}
