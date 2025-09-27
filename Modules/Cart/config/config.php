<?php

return [
    'name' => 'Cart',
    'fake_validation' => env('CART_FAKE_VALIDATION_ENABLED', false),
    'locking_period' => env('CART_LOCKING_PERIOD', 15),
];
