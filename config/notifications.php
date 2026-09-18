<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Overdue digest weekday
    |--------------------------------------------------------------------------
    |
    | ISO-8601 weekday (1 = Monday … 7 = Sunday) when past-due emails and
    | in-app/FCM notifications are sent as one merged digest per recipient.
    | Defaults to Friday. Due-soon reminders stay daily.
    |
    */
    'overdue_digest_weekday' => (int) env('OVERDUE_DIGEST_WEEKDAY', 5),

];
