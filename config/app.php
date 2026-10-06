<?php

return [
    'name' => env('APP_NAME', 'Rice Ledger'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'timezone' => env('APP_TIMEZONE', 'Asia/Rangoon'),
    'session_name' => env('SESSION_NAME', 'rice_ledger_session'),
    'layout' => 'layouts/app',
];
