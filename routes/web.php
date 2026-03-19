<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Facades\Route;

Route::bind('asset', function (string $value): Asset {
    return Asset::findOrFail($value);
});

Route::bind('handover', function (string $value): Handover {
    return Handover::findOrFail($value);
});

Route::middleware(['web', 'auth', 'can:see-app-assets'])->group(function () {
    Route::livewire('apps/assets', 'intranet-app-assets::apps.assets.index')->name('apps.assets.index');
    Route::livewire('apps/assets/meine-assets', 'intranet-app-assets::apps.assets.meine-assets')->name('apps.assets.meine-assets');
    Route::livewire('apps/assets/liste', 'intranet-app-assets::apps.assets.liste')->name('apps.assets.liste');
    Route::livewire('apps/assets/mobilgeraete', 'intranet-app-assets::apps.assets.mobilgeraete')->name('apps.assets.mobilgeraete');
    Route::livewire('apps/assets/domaenengeraete', 'intranet-app-assets::apps.assets.domaenengeraete')->name('apps.assets.domaenengeraete');
    Route::livewire('apps/assets/domain-compare', 'intranet-app-assets::apps.assets.domain-compare')->name('apps.assets.domain-compare');
    Route::livewire('apps/assets/itexiageraete', 'intranet-app-assets::apps.assets.itexiageraete')->name('apps.assets.itexiageraete');
    Route::livewire('apps/assets/settings/user', 'intranet-app-assets::apps.assets.settings.user')->name('apps.assets.settings.user');

    // Handover-Routen (vor {asset}-Wildcard)
    Route::livewire('apps/assets/handover/{handover}', 'intranet-app-assets::apps.assets.handover-show')
        ->name('apps.assets.handover.show');
    Route::livewire('apps/assets/handover/{handover}/confirm', 'intranet-app-assets::apps.assets.handover-confirm')
        ->name('apps.assets.handover.confirm');
    Route::livewire('apps/assets/handover/{handover}/confirm-by-password', 'intranet-app-assets::apps.assets.handover-confirm-by-password')
        ->middleware('ldap.password.confirm')
        ->name('apps.assets.handover.confirm-by-password');
    Route::livewire('apps/assets/handover/{handover}/confirm-by-signopad', 'intranet-app-assets::apps.assets.handover-confirm-by-signopad')
        ->name('apps.assets.handover.confirm-by-signopad');

    // Spezifische manage-Routen VOR dem {asset}-Wildcard
    Route::livewire('apps/assets/create/wizard', 'intranet-app-assets::apps.assets.create-wizard')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.create.wizard');
    Route::livewire('apps/assets/create', 'intranet-app-assets::apps.assets.create')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.create');
    Route::livewire('apps/assets/admin', 'intranet-app-assets::apps.assets.admin.index')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.index');
    Route::livewire('apps/assets/fehlende-rechnung', 'intranet-app-assets::apps.assets.fehlende-rechnung-overview')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.fehlende-rechnung');

    // Wildcard-Routen zuletzt
    Route::livewire('apps/assets/{asset}', 'intranet-app-assets::apps.assets.show')->name('apps.assets.show');
    Route::livewire('apps/assets/{asset}/edit', 'intranet-app-assets::apps.assets.edit')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.edit');
    Route::livewire('apps/assets/{asset}/delete', 'intranet-app-assets::apps.assets.delete')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.delete');
});
