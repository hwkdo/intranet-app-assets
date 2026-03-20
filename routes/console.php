<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('intranet-app-assets:domain-check')
    ->dailyAt('03:02')
    ->when(fn () => config('app.env') === 'production');

Schedule::command('intranet-app-assets:intune-sync')
    ->dailyAt('03:12')
    ->when(fn () => config('app.env') === 'production');

Schedule::command('intranet-app-assets:sync-configmgr-data')
    ->dailyAt('03:22')
    ->when(fn () => config('app.env') === 'production');

Schedule::command('intranet-app-assets:itexia-uuid-sync --limit=120')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->when(fn () => config('app.env') === 'production');

Schedule::command('intranet-app-assets:itexia-image-sync --limit=60')
    ->cron('7,22,37,52 * * * *')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->when(fn () => config('app.env') === 'production');
