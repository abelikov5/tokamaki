<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posters:dispatch-scheduled-import')
    ->everyMinute()
    ->name('posters:auto-import')
    ->withoutOverlapping(10)
    ->onOneServer();
