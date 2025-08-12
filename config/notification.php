<?php

return [
    
    /*
     |--------------------------------------------------------------------------
     | Protected Keys
     |--------------------------------------------------------------------------
     |
     | To protect sensitive data being logged in the database in plain text
     | you can add keys to this array that should be masked with an astrix.
     |
     */

     'protected_keys' => [
        'password',
        'password_confirmation',
    ],

    /*
     |--------------------------------------------------------------------------
     | Max Loggable Length
     |--------------------------------------------------------------------------
     |
     | Configure the maximum amount of data (in bytes) that can be logged as part of the
     | request/response header and body elements.
     |
     */

     'max_loggable_length' => env('NOTIFICATION_LOG_MAX_LENGTH', 10024),

     /*
     |--------------------------------------------------------------------------
     | HTTP Client Timeout
     |--------------------------------------------------------------------------
     |
     | The timeout in seconds if the endpoint is not responded.
     |
     */

    'timeout' => env('NOTIFICATION_TIMEOUT', 5.0),

    /*
     |--------------------------------------------------------------------------
     | Database Migration
     |--------------------------------------------------------------------------
     |
     | The database migration file is created and the generated file could be
     | imported to application.
     |
     */

    'database' => [
        'table' => env('NOTIFICATION_LOGS_TABLE', 'notification_logs'),
    ],

     /*
     |--------------------------------------------------------------------------
     | Authentication Guard
     |--------------------------------------------------------------------------
     |
     | The authentication guard being used by the system e.g. sanctum
     |
     */

    'auth_guard' => null,

    /*
     |--------------------------------------------------------------------------
     | Notification Template
     |--------------------------------------------------------------------------
     |
     | The template used by the PayPoint Sendgrid
     |
     */

     'template' => [
        'default_id' => env('TEMPLATE_ID'),
        env('TEMPLATE_ID') => [
            'validation_rule' => [
                '*.*.Recipients.*.Name' => ['required', 'string', 'max:255'],
                '*.*.Recipients.*.Email' => ['required', 'email', 'max:255'],
            ],
        ]
     ],

     
];