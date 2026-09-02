<?php

return [

    'paths' => [
        'api/*',
        'oauth/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [
        '#^https://[a-zA-Z0-9-]+\.ngrok-free\.app$#',
        '#^https://[a-zA-Z0-9-]+\.ngrok\.io$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
