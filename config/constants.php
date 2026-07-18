<?php
return [
    'cart_expires' => env("CART_EXPIRE_IN_MINUTES", 5),
    'cancellation' => [
        0 => 'pending',
        1 => 'approved',
        2 => 'processing',
        3 => 'refunded',
        9 => 'declined'
    ],
    'booking' => [
        0 => 'pending'
    ],
    'owners' => [
        'durpalla' => 'Durpalla',
        'merchant' => 'Merchant'
    ],
    'floors' => [
        1 => '1st Floor',
        2 => '2nd Floor',
        3 => '3rd Floor',
        4 => '4th Floor',
        5 => '5th Floor',
        6 => '6th Floor',
        7 => '7th Floor',
        8 => '8th Floor',
        9 => '9th Floor',
        10 => '10th Floor',
        11 => '11th Floor'
    ],
    'floors_numbers' => [
        1 => '1 Floors',
        2 => '2 Floors',
        3 => '3 Floors',
        4 => '4 Floors',
        5 => '5 Floors',
        6 => '6 Floors',
        7 => '7 Floors',
        8 => '8 Floors',
        9 => '9 Floors',
        10 => '10 Floors',
        11 => '11 Floors'
    ],
    'default_parties' => [
        'merchant' => 'Merchant',
        'durpalla' => 'Durpalla'
    ],
    'incentive_types' => [
        'percent' => '%',
        'fixed' => 'Tk.',
        'p' => '%',
        'f' => 'Tk.'
    ],
    'user_status' => [
        1 => 'Active',
        0 => 'Pending',
        2 => 'Disabled'
    ],
    'vehicle_status' => [
        1 => 'Active',
        0 => 'Inactive',
    ],
    'service_status' => [
        1 => 'Enable',
        0 => 'Disable'
    ],
    'withdrawal_status' => [
        0 => 'Pending',
        1 => 'Complete',
        2 => 'Cancelled'
    ],
    'broadcast_types' => [
        'sms' => 'SMS',
        'email' => 'Email',
        'message' => 'Message',
        'notification' => 'Notification',
        'topic' => 'Topics'
    ],
    'topics' => [
        'agent' => 'Agents',
        'customer' => 'Customers',
        'merchant' => 'Merchants',
        'party' => 'Parties',
        'supervisor' => 'Supervisors'
    ],
    'seals' => [
        'COMPLETE' => 'PAID',
        'ADVANCE' => 'DUE',
        'CANCELLED' => 'CANCELLED',
        'FAILED' => 'PAYMENT FAILED'
    ]
];
