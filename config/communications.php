<?php

return [
    'sms' => [
        // Disabled is the safe default until P-005 is formally approved.
        'enabled' => (bool) env('SMS_ENABLED', false),
        'driver' => env('SMS_DRIVER', 'disabled'),
        // D-052: announcements and Admin direct messages use Semaphore's priority queue
        // (2 credits per 160-character part). Set to "standard" for the 1-credit queue.
        'message_route' => env('SMS_MESSAGE_ROUTE', 'priority'),
        'semaphore' => [
            'api_key' => env('SEMAPHORE_API_KEY'),
            'sender_name' => env('SEMAPHORE_SENDER_NAME'),
            'base_url' => env('SEMAPHORE_BASE_URL', 'https://api.semaphore.co'),
        ],
    ],
];
