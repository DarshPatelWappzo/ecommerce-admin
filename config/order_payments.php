<?php

return [
    'methods' => [
        'cod' => ['label' => 'Cash on delivery', 'enabled' => env('ORDER_PAYMENTS_COD_ENABLED', true)],
        'cash' => ['label' => 'Cash', 'enabled' => env('ORDER_PAYMENTS_CASH_ENABLED', true)],
        'bank_transfer' => ['label' => 'Bank transfer', 'enabled' => env('ORDER_PAYMENTS_BANK_TRANSFER_ENABLED', true)],
        'razorpay' => ['label' => 'Razorpay', 'enabled' => env('ORDER_PAYMENTS_RAZORPAY_ENABLED', false)],
    ],
    'razorpay' => [
        'key_id' => env('ORDER_RAZORPAY_KEY_ID'),
        'key_secret' => env('ORDER_RAZORPAY_KEY_SECRET'),
        // 'webhook_secret' => env('ORDER_RAZORPAY_WEBHOOK_SECRET'),
    ],
];
