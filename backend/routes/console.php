<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatically clean up jobs stuck in 'processing' status (runs every hour)
Schedule::command('clinex:clean-stale-jobs')->hourly();
