<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alert status timeline slot count
    |--------------------------------------------------------------------------
    |
    | Each alert rule timeline is split into this many equal-width time slots
    | before consecutive slots from the same status period are merged. Segment
    | count values in the API response always sum to this number.
    |
    */

    'timeline_slot_count' => (int) env('ALERT_STATUS_TIMELINE_SLOT_COUNT', 100),

];
