<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAssets\Http\Controllers\Api\RegisterFidoController;
use Illuminate\Support\Facades\Route;

Route::post('/api/asset/registerFido', RegisterFidoController::class)
    ->middleware('throttle:60,1')
    ->name('api.asset.registerFido');
