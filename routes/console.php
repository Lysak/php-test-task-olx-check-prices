<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('prices:check')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
