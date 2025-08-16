<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],  // Ensure that your API paths are correctly mentioned.

    'allowed_methods' => ['*'], // Allow all HTTP methods (GET, POST, PUT, DELETE, etc.)

    'allowed_origins' => ['*'], // Allow requests from all origins (change to specific origins if needed)

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Allow all headers (adjust as per requirement)

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false, // Set this to true if you're using cookies or authentication


];
