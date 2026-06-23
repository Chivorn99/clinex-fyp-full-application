<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stale Job Threshold (minutes)
    |--------------------------------------------------------------------------
    |
    | Jobs stuck in "processing" status for longer than this threshold are
    | considered stale and can be automatically reset by the scheduled
    | cleanup command or manually via the admin dashboard.
    |
    */
    'stale_job_threshold_minutes' => (int) env('CLINEX_STALE_JOB_THRESHOLD_MINUTES', 120),

];
