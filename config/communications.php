<?php

return [
    'sms' => [
        // Disabled is the safe default until P-005 is formally approved.
        'enabled' => (bool) env('SMS_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'disabled'),
        'semaphore' => [
            'api_key' => env('SEMAPHORE_API_KEY'),
            'sender_name' => env('SEMAPHORE_SENDER_NAME'),
            'base_url' => env('SEMAPHORE_BASE_URL', 'https://api.semaphore.co'),
        ],
    ],
];
