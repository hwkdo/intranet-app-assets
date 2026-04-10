<?php

use Illuminate\Support\Facades\Route;

it('registers a dedicated assets search route', function () {
    expect(Route::has('apps.assets.search'))->toBeTrue();
});

it('registers the alle assets liste route', function () {
    expect(Route::has('apps.assets.liste'))->toBeTrue();
});

it('registers the unified admin uebergaben route and legacy redirects', function () {
    expect(Route::has('apps.assets.admin.handovers'))->toBeTrue();
    expect(Route::has('apps.assets.admin.open-handovers'))->toBeTrue();
    expect(Route::has('apps.assets.admin.rejected-handovers'))->toBeTrue();
});

it('registers the sccm compare route', function () {
    expect(Route::has('apps.assets.sccm-compare'))->toBeTrue();
});
