<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('pdf-processing.{sessionId}', function ($user, $sessionId) {
    return true; // Allow all authenticated users for now
});
