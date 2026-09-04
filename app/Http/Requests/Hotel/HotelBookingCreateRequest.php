<?php

namespace App\Http\Requests\Hotel;

use Illuminate\Foundation\Http\FormRequest;

class HotelBookingCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:users,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'rooms' => 'required|array|min:1',
            'rooms.*.hotel_id' => 'required|exists:hotels,id',
            'rooms.*.room_type_id' => 'required|exists:room_types,id',
            'rooms.*.rate_plan_id' => 'required|exists:room_rate_plans,id',
            'rooms.*.room_id' => 'nullable|exists:hotel_rooms,id',
            'rooms.*.adults' => 'nullable|integer|min:1',
            'rooms.*.children' => 'nullable|integer|min:0',
            'rooms.*.children_ages' => 'nullable|array',
            'rooms.*.children_ages.*' => 'integer|min:0|max:17',
            'rooms.*.book_token' => 'nullable|string',
            'service_charge' => 'nullable|numeric|min:0',
            'vat_amount' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'platform' => 'nullable|string|in:web,android,ios,admin,merchant_desk',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rooms = $this->input('rooms', []);
            if (! is_array($rooms)) {
                return;
            }
            foreach ($rooms as $i => $room) {
                $children = (int) ($room['children'] ?? $this->input('children', 0));
                $ages = $room['children_ages'] ?? [];
                if ($children > 0 && (! is_array($ages) || count($ages) !== $children)) {
                    $validator->errors()->add(
                        "rooms.$i.children_ages",
                        "Provide exactly {$children} child age(s) for room #".($i + 1).'.'
                    );
                }
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
