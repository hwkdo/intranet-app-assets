<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Support\Facades\Route;

Route::bind('asset', function (string $value): Asset {
    return Asset::withTrashed()->findOrFail($value);
});

Route::bind('handover', function (string $value): Handover {
    return Handover::findOrFail($value);
});

Route::bind('assetReturn', function (string $value): AssetReturn {
    return AssetReturn::query()->findOrFail((int) $value);
});

Route::middleware(['web', 'auth', 'can:see-app-assets'])->group(function () {
    Route::livewire('apps/assets', 'intranet-app-assets::apps.assets.dashboard')->name('apps.assets.index');
    Route::livewire('apps/assets/suche', 'intranet-app-assets::apps.assets.search')->name('apps.assets.search');
    Route::livewire('apps/assets/meine-assets', 'intranet-app-assets::apps.assets.meine-assets')->name('apps.assets.meine-assets');
    Route::livewire('apps/assets/liste', 'intranet-app-assets::apps.assets.liste')->name('apps.assets.liste');
    Route::livewire('apps/assets/mobilgeraete', 'intranet-app-assets::apps.assets.mobilgeraete')->name('apps.assets.mobilgeraete');
    Route::livewire('apps/assets/domaenengeraete', 'intranet-app-assets::apps.assets.domaenengeraete')->name('apps.assets.domaenengeraete');
    Route::livewire('apps/assets/domain-compare', 'intranet-app-assets::apps.assets.domain-compare')->name('apps.assets.domain-compare');
    Route::livewire('apps/assets/itexiageraete', 'intranet-app-assets::apps.assets.itexiageraete')->name('apps.assets.itexiageraete');
    Route::livewire('apps/assets/legacy-assets', 'intranet-app-assets::apps.assets.legacy-assets')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.legacy-assets');
    Route::livewire('apps/assets/settings/user', 'intranet-app-assets::apps.assets.settings.user')->name('apps.assets.settings.user');

    // Mehrfach-Übergaben (vor handover/{handover}, damit „bulk“ nicht als Handover-ID gilt)
    Route::livewire('apps/assets/handover/bulk/confirm', 'intranet-app-assets::apps.assets.handover-bulk-confirm')
        ->name('apps.assets.handover.bulk.confirm');
    Route::livewire('apps/assets/handover/bulk/confirm-by-password', 'intranet-app-assets::apps.assets.handover-bulk-confirm-by-password')
        ->middleware('ldap.password.confirm')
        ->name('apps.assets.handover.bulk.confirm-by-password');
    Route::livewire('apps/assets/handover/bulk/confirm-by-signopad', 'intranet-app-assets::apps.assets.handover-bulk-confirm-by-signopad')
        ->name('apps.assets.handover.bulk.confirm-by-signopad');
    Route::livewire('apps/assets/handover/bulk/reject', 'intranet-app-assets::apps.assets.handover-bulk-reject')
        ->name('apps.assets.handover.bulk.reject');
    Route::livewire('apps/assets/handover/bulk/reject-commit', 'intranet-app-assets::apps.assets.handover-bulk-reject-commit')
        ->middleware('ldap.password.confirm')
        ->name('apps.assets.handover.bulk.reject-commit');

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
    Route::livewire('apps/assets/handover/{handover}/reject', 'intranet-app-assets::apps.assets.handover-reject')
        ->middleware('ldap.password.confirm')
        ->name('apps.assets.handover.reject');
    Route::livewire('apps/assets/handover/{handover}/rueckgabe/einleiten', 'intranet-app-assets::apps.assets.handover-return-initiate')
        ->name('apps.assets.handover.return.initiate');
    Route::livewire('apps/assets/{asset}/klarung-melden', 'intranet-app-assets::apps.assets.asset-request-clarification')
        ->name('apps.assets.clarification.request');

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
    Route::livewire('apps/assets/admin/mehrfachaktion/pruefen', 'intranet-app-assets::apps.assets.admin-bulk-workflow-review')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.bulk.review');
    Route::livewire('apps/assets/admin/mehrfachaktion/speichern', 'intranet-app-assets::apps.assets.admin-bulk-workflow-commit')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.admin.bulk.commit');
    Route::livewire('apps/assets/fehlende-rechnung', 'intranet-app-assets::apps.assets.fehlende-rechnung-overview')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.fehlende-rechnung');
    Route::livewire('apps/assets/deleted', 'intranet-app-assets::apps.assets.deleted')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.deleted');
    Route::livewire('apps/assets/admin/abgelehnte-uebergaben', 'intranet-app-assets::apps.assets.rejected-handovers-overview')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.rejected-handovers');
    Route::livewire('apps/assets/admin/abgelehnte-uebergaben/{handover}/bearbeiten', 'intranet-app-assets::apps.assets.rejected-handover-resolve')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.rejected-handover.resolve');
    Route::livewire('apps/assets/admin/abgelehnte-uebergaben/{handover}/speichern', 'intranet-app-assets::apps.assets.rejected-handover-resolve-commit')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.admin.rejected-handover.resolve-commit');
    Route::livewire('apps/assets/admin/offene-uebergaben', 'intranet-app-assets::apps.assets.open-handovers-overview')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.open-handovers');
    Route::livewire('apps/assets/admin/offene-uebergaben/{handover}/bearbeiten', 'intranet-app-assets::apps.assets.open-handover-resolve')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.open-handover.resolve');
    Route::livewire('apps/assets/admin/offene-uebergaben/{handover}/speichern', 'intranet-app-assets::apps.assets.open-handover-resolve-commit')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.admin.open-handover.resolve-commit');
    Route::livewire('apps/assets/admin/klarung', 'intranet-app-assets::apps.assets.clarifications-overview')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.clarifications');
    Route::livewire('apps/assets/admin/klarung/{asset}/bearbeiten', 'intranet-app-assets::apps.assets.clarification-resolve')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.clarification.resolve');
    Route::livewire('apps/assets/admin/klarung/{asset}/speichern', 'intranet-app-assets::apps.assets.clarification-resolve-commit')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.admin.clarification.resolve-commit');
    Route::livewire('apps/assets/admin/rueckgaben', 'intranet-app-assets::apps.assets.pending-returns-overview')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.returns.pending');
    Route::livewire('apps/assets/admin/rueckgaben/{assetReturn}/bearbeiten', 'intranet-app-assets::apps.assets.return-complete-resolve')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.admin.return.complete');
    Route::livewire('apps/assets/admin/rueckgaben/{assetReturn}/speichern', 'intranet-app-assets::apps.assets.return-complete-resolve-commit')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.admin.return.complete-commit');

    // Wildcard-Routen zuletzt
    Route::livewire('apps/assets/{asset}', 'intranet-app-assets::apps.assets.show')->name('apps.assets.show');
    Route::livewire('apps/assets/{asset}/edit', 'intranet-app-assets::apps.assets.edit')
        ->middleware('can:manage-app-assets')
        ->name('apps.assets.edit');
    Route::livewire('apps/assets/{asset}/delete', 'intranet-app-assets::apps.assets.delete')
        ->middleware(['can:manage-app-assets', 'ldap.password.confirm'])
        ->name('apps.assets.delete');
});
