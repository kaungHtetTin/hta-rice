<?php

return [
    'name' => env('APP_NAME', 'My Small App'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'session_name' => env('SESSION_NAME', 'mini_php_session'),
    'layout' => 'layouts/app',
];
