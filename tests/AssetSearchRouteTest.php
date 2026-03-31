<?php

use Illuminate\Support\Facades\Route;

it('registers a dedicated assets search route', function () {
    expect(Route::has('apps.assets.search'))->toBeTrue();
});
