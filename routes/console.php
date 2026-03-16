<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('intranet-app-assets:domain-check')
    ->dailyAt('03:02')
    ->when(fn () => config('app.env') === 'production');

Schedule::command('intranet-app-assets:intune-sync')
    ->dailyAt('03:12')
    ->when(fn () => config('app.env') === 'production');
