<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAssets\Events\AssetHandoverChanged;
use Hwkdo\IntranetAppAssets\Events\AssetsDashboardChanged;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\AssetDashboardBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

test('eine interaktive übergabe aktualisiert dashboard und empfänger', function (): void {
    Event::fake([AssetsDashboardChanged::class, AssetHandoverChanged::class]);

    $admin = User::factory()->create();
    $this->actingAs($admin);

    $handover = new Handover(['recipient_user_id' => 15]);
    $handover->wasRecentlyCreated = true;

    app(AssetDashboardBroadcaster::class)->handoverSaved($handover);

    Event::assertDispatched(AssetsDashboardChanged::class);
    Event::assertDispatched(AssetHandoverChanged::class, fn (AssetHandoverChanged $event): bool => $event->userId === 15);
});

test('ohne angemeldeten benutzer bleibt eine übergabe ohne broadcast', function (): void {
    Event::fake([AssetsDashboardChanged::class, AssetHandoverChanged::class]);

    $handover = new Handover(['recipient_user_id' => 15]);
    $handover->wasRecentlyCreated = true;

    app(AssetDashboardBroadcaster::class)->handoverSaved($handover);

    Event::assertNotDispatched(AssetsDashboardChanged::class);
    Event::assertNotDispatched(AssetHandoverChanged::class);
});

test('assets dashboard channel verlangt manage-app-assets', function (): void {
    Permission::findOrCreate('manage-app-assets', 'web');

    $user = User::factory()->create();
    $manager = User::factory()->create();
    $manager->givePermissionTo('manage-app-assets');

    $callback = app(BroadcastManager::class)->driver()->getChannels()['assets-dashboard-channel'];

    expect($callback($user))->toBeFalse()
        ->and($callback($manager))->toBeTrue();
});

test('assets dashboard changed sendet auf den admin-kanal', function (): void {
    $event = new AssetsDashboardChanged;

    expect($event->broadcastOn())->toEqual([new PrivateChannel('assets-dashboard-channel')])
        ->and($event->broadcastAs())->toBe('assets.dashboard-changed');
});

test('vermisste assets hören auf das admin-dashboard', function (): void {
    Permission::findOrCreate('see-app-assets', 'web');
    Permission::findOrCreate('manage-app-assets', 'web');

    $manager = User::factory()->create();
    $manager->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $listeners = Livewire::actingAs($manager)
        ->test('intranet-app-assets::apps.assets.widgets.vermisste-assets')
        ->instance()
        ->getListeners();

    expect($listeners)->toHaveKey('echo-private:assets-dashboard-channel,.assets.dashboard-changed');
});
