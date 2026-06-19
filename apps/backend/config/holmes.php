<?php

return [
    'base_url' => rtrim(env('HOLMES_BASE_URL', ''), '/'),
    'api_key' => env('HOLMES_API_KEY'),
    'model' => env('HOLMES_MODEL'),
    'timeout' => (int) env('HOLMES_TIMEOUT', 120),
];
