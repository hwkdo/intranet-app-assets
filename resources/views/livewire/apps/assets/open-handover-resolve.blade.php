<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\OpenHandoverAdminResolutionService;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Offene Übergabe bearbeiten')] class extends Component
{
    public Handover $handover;

    public bool $acknowledgeOpenState = false;

    public string $resolution = OpenHandoverAdminResolutionService::ResolutionNewOwner;

    public ?string $newOwnerUserId = null;

    public string $location = '';

    public function mount(Handover $handover): void
    {
        $this->authorize('manage-app-assets');

        if ($handover->isConfirmed() || $handover->isRejected()) {
            session()->flash('message', 'Diese Übergabe ist nicht mehr offen.');
            $this->redirect(route('apps.assets.admin.handovers', ['filter' => 'open']), navigate: true);
        }

        $handover->load(['recipient', 'issuer']);
        $asset = null;
        if ($handover->asset_id !== null) {
            $asset = Asset::query()
                ->withTrashed()
                ->with(['type', 'vendor', 'owner.standort'])
                ->find($handover->asset_id);
        }
        $handover->setRelation('asset', $asset);

        $this->handover = $handover;
    }

    public function submit(): void
    {
        $this->authorize('manage-app-assets');

        $rules = [
            'acknowledgeOpenState' => ['accepted'],
            'resolution' => ['required', 'string', 'in:'.OpenHandoverAdminResolutionService::ResolutionNewOwner.','.OpenHandoverAdminResolutionService::ResolutionSetLocation.','.OpenHandoverAdminResolutionService::ResolutionMarkMissing],
        ];

        if ($this->resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner) {
            $rules['newOwnerUserId'] = ['required', 'exists:users,id'];
        }
        if ($this->resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation) {
            $rules['location'] = ['required', 'string', 'min:1', 'max:255'];
        }

        $this->validate($rules);

        $rawNewOwner = $this->newOwnerUserId;
        $newOwnerId = $rawNewOwner !== null && $rawNewOwner !== '' ? (int) $rawNewOwner : null;

        Session::put(OpenHandoverAdminResolutionService::PENDING_RESOLUTION_SESSION_KEY, [
            'handover_id' => $this->handover->id,
            'admin_user_id' => auth()->id(),
            'resolution' => $this->resolution,
            'new_owner_user_id' => $this->resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner ? $newOwnerId : null,
            'location' => $this->resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation ? trim($this->location) : null,
        ]);

        $this->redirect(route('apps.assets.admin.open-handover.resolve-commit', $this->handover), navigate: false);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $users = \App\Models\User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return view('intranet-app-assets::livewire.apps.assets.open-handover-resolve', [
            'users' => $users,
        ]);
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout
        heading="Offene Übergabe bearbeiten"
        subheading="{{ $handover->asset?->display_name ?? 'Asset' }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <x-intranet-app-assets::handover-asset-summary :handover="$handover" />

            <flux:card class="space-y-3 text-sm">
                <flux:heading size="sm">Übergabe</flux:heading>
                <flux:text>Empfänger: <span class="font-medium">{{ $handover->recipient?->name ?? '—' }}</span></flux:text>
                <flux:text>Ausgestellt von: <span class="font-medium">{{ $handover->issuer?->name ?? '—' }}</span></flux:text>
                <flux:text>Erstellt am: <span class="font-medium">{{ $handover->created_at?->format('d.m.Y H:i') ?? '—' }}</span></flux:text>
            </flux:card>

            @if($handover->asset && ! $handover->asset->trashed())
                <flux:card class="space-y-3 border-red-200 dark:border-red-900/40">
                    <flux:heading size="sm">Asset löschen</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        Wenn das Asset dauerhaft entfernt werden soll, nutzen Sie die reguläre Löschung.
                    </flux:text>
                    <flux:button href="{{ route('apps.assets.delete', $handover->asset) }}" variant="danger" icon="trash">
                        Zur Löschseite
                    </flux:button>
                </flux:card>
            @endif

            <form wire:submit="submit" class="space-y-6">
                <flux:card class="space-y-4">
                    <flux:checkbox
                        wire:model="acknowledgeOpenState"
                        label="Ich bestätige, dass diese offene Übergabe adminseitig aufgelöst werden soll."
                    />
                    @error('acknowledgeOpenState')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <flux:radio.group wire:model.live="resolution" label="Weiteres Vorgehen">
                        <flux:radio
                            value="{{ OpenHandoverAdminResolutionService::ResolutionNewOwner }}"
                            label="Neuen Besitzer zuweisen"
                            description="Offene Übergabe wird als ersetzt markiert und bleibt in der Historie; neuer Besitzer wird gesetzt (neue Übergabe)."
                        />
                        <flux:radio
                            value="{{ OpenHandoverAdminResolutionService::ResolutionSetLocation }}"
                            label="Besitzer entfernen und Standort setzen (Auf Lager)"
                            description="Besitzer wird entfernt; offene Übergabe wird als ersetzt markiert und bleibt in der Historie. Standort ist Pflicht. Asset wird als Auf Lager markiert."
                        />
                        <flux:radio
                            value="{{ OpenHandoverAdminResolutionService::ResolutionMarkMissing }}"
                            label="Als vermisst markieren"
                            description="Besitzer wird entfernt; offene Übergabe wird als ersetzt markiert und bleibt in der Historie. Asset wird als vermisst gekennzeichnet."
                        />
                    </flux:radio.group>
                    @error('resolution')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    @if($resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner)
                        <flux:select
                            wire:model="newOwnerUserId"
                            variant="listbox"
                            searchable
                            label="Neuer Besitzer"
                            placeholder="Benutzer wählen…"
                            class="min-w-64"
                        >
                            @foreach($users as $user)
                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('newOwnerUserId')
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endif

                    @if($resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation)
                        <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                        @error('location')
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endif
                </flux:card>

                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                    Beim Speichern werden Sie zur Bestätigung mit Ihrem LDAP-/Windows-Passwort weitergeleitet.
                </flux:text>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        Speichern und abschließen
                    </flux:button>
                    <flux:button :href="route('apps.assets.admin.handovers', ['filter' => 'open'])" variant="ghost" wire:navigate>
                        Zurück zur Liste
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
