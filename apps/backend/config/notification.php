<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery attempts
    |--------------------------------------------------------------------------
    |
    | How many times one endpoint delivery is attempted before it stays failed.
    | Only failures a channel marks as retryable (timeouts, 429, 5xx) are
    | retried. A manual retry grants one more attempt on top of this.
    |
    */

    'max_attempts' => (int) env('NOTIFICATION_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Retry backoff
    |--------------------------------------------------------------------------
    |
    | Seconds to wait before each automatic retry. The last value is reused
    | when there are more retries than entries.
    |
    */

    'retry_backoff' => [30, 120, 600],

    /*
    |--------------------------------------------------------------------------
    | Stored provider response size
    |--------------------------------------------------------------------------
    |
    | Provider responses are kept on the delivery for inspection. Anything
    | longer than this many characters (JSON encoded) is truncated.
    |
    */

    'response_max_length' => (int) env('NOTIFICATION_RESPONSE_MAX_LENGTH', 4096),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts (seconds)
    |--------------------------------------------------------------------------
    */

    'http_timeout' => (int) env('NOTIFICATION_HTTP_TIMEOUT', 15),

    'http_connect_timeout' => (int) env('NOTIFICATION_HTTP_CONNECT_TIMEOUT', 5),

];
