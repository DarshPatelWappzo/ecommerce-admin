<?php

return [
    'methods' => [
        'cod' => ['label' => 'Cash on delivery', 'enabled' => env('ORDER_PAYMENTS_COD_ENABLED', true)],
        'cash' => ['label' => 'Cash', 'enabled' => env('ORDER_PAYMENTS_CASH_ENABLED', true)],
        'bank_transfer' => ['label' => 'Bank transfer', 'enabled' => env('ORDER_PAYMENTS_BANK_TRANSFER_ENABLED', true)],
        'razorpay' => ['label' => 'Razorpay'],
    ],
];
