@php
    use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Abgelehnte Übergabe bearbeiten"
        subheading="{{ $handover->asset?->display_name ?? 'Asset' }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <x-intranet-app-assets::handover-asset-summary :handover="$handover" />

            <flux:card class="space-y-3 text-sm">
                <flux:heading size="sm">Übergabe</flux:heading>
                <flux:text>Empfänger: <span class="font-medium">{{ $handover->recipient?->name ?? '—' }}</span></flux:text>
                <flux:text>Abgelehnt am: <span class="font-medium">{{ $handover->rejected_at?->format('d.m.Y H:i') ?? '—' }}</span></flux:text>
                @php $lastNote = $handover->notes()->latest('id')->first(); @endphp
                @if($lastNote)
                    <div>
                        <flux:heading size="sm" class="mb-1">Letzte Notiz</flux:heading>
                        <flux:text class="whitespace-pre-wrap text-zinc-600 dark:text-zinc-300">{{ $lastNote->note }}</flux:text>
                    </div>
                @endif
            </flux:card>

            @if($handover->asset && ! $handover->asset->trashed())
                <flux:card class="space-y-3 border-red-200 dark:border-red-900/40">
                    <flux:heading size="sm">Asset löschen</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        Wenn das Asset dauerhaft entfernt werden soll, nutzen Sie die reguläre Löschung: Löschgrund, Verlauf und ggf. Itexia-Hinweis wie gewohnt; Sie werden zur Bestätigung mit Ihrem LDAP-/Windows-Passwort aufgefordert.
                    </flux:text>
                    <flux:button
                        href="{{ route('apps.assets.delete', $handover->asset) }}"
                        variant="danger"
                        icon="trash"
                    >
                        Zur Löschseite
                    </flux:button>
                </flux:card>
            @endif

            <form wire:submit="submit" class="space-y-6">
                <flux:card class="space-y-4">
                    <flux:checkbox
                        wire:model="acknowledgeNotWithUser"
                        label="Ich bestätige, dass das Asset physisch nicht beim genannten Benutzer liegt."
                    />
                    @error('acknowledgeNotWithUser')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <flux:radio.group wire:model.live="resolution" label="Weiteres Vorgehen">
                        <flux:radio
                            value="{{ HandoverRejectionAdminResolutionService::ResolutionNewOwner }}"
                            label="Neuen Besitzer zuweisen"
                            description="Abgelehnte Übergabe wird als ersetzt markiert und bleibt in der Historie; neuer Besitzer wird gesetzt (neue Übergabe)."
                        />
                        <flux:radio
                            value="{{ HandoverRejectionAdminResolutionService::ResolutionSetLocation }}"
                            label="Besitzer entfernen und Standort setzen (Auf Lager)"
                            description="Besitzer wird entfernt; abgelehnte Übergabe wird als ersetzt markiert und bleibt in der Historie. Standort ist Pflicht. Asset wird als Auf Lager markiert."
                        />
                        <flux:radio
                            value="{{ HandoverRejectionAdminResolutionService::ResolutionMarkMissing }}"
                            label="Als vermisst markieren"
                            description="Besitzer wird entfernt; abgelehnte Übergabe wird als ersetzt markiert und bleibt in der Historie. Asset wird als vermisst gekennzeichnet."
                        />
                    </flux:radio.group>
                    @error('resolution')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    @if($resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner)
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

                    @if($resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation)
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
                    <flux:button :href="route('apps.assets.admin.handovers', ['filter' => 'rejected'])" variant="ghost" wire:navigate>
                        Zurück zur Liste
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
