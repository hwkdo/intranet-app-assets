<?php

use Illuminate\Support\Facades\Route;

it('registers a dedicated assets search route', function () {
    expect(Route::has('apps.assets.search'))->toBeTrue();
});

it('registers the alle assets liste route', function () {
    expect(Route::has('apps.assets.liste'))->toBeTrue();
});
