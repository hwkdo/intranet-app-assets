@php
    use Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService;
    $h = $assetReturn->handover;
    $asset = $h?->asset;
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Rückgabe abschließen"
        subheading="{{ $asset?->display_name ?? 'Asset' }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4 dark:text-white">Asset</flux:heading>
                <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <dt class="font-semibold text-zinc-500 dark:text-white">Anzeigename</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset?->display_name ?? '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Seriennummer</dt>
                    <dd class="font-mono text-zinc-900 dark:text-white">{{ $asset?->serial_number ?? '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Zurückgegeben von</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $assetReturn->initiatedBy?->name ?? $h?->recipient?->name ?? '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Eingeleitet am</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $assetReturn->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </dl>
            </flux:card>

            @php $lastNote = $assetReturn->notes()->latest('id')->first(); @endphp
            @if($lastNote)
                <flux:card class="space-y-2 text-sm">
                    <flux:heading size="sm" class="dark:text-white">Hinweis</flux:heading>
                    <flux:text class="whitespace-pre-wrap text-zinc-600 dark:text-zinc-300">{{ $lastNote->note }}</flux:text>
                </flux:card>
            @endif

            @if($asset && ! $asset->trashed())
                <flux:card class="space-y-3 border-red-200 dark:border-red-900/40">
                    <flux:heading size="sm" class="dark:text-white">Asset löschen</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        Zur regulären Löschseite mit Löschgrund, Verlauf und LDAP-Bestätigung.
                    </flux:text>
                    <flux:button href="{{ route('apps.assets.delete', $asset) }}" variant="danger" icon="trash">
                        Zur Löschseite
                    </flux:button>
                </flux:card>
            @endif

            <form wire:submit="submit" class="space-y-6">
                <flux:card class="space-y-4 dark:[&_[data-flux-label]]:text-white dark:[&_legend]:text-white">
                    <flux:checkbox
                        wire:model="acknowledgeReceipt"
                        label="Ich bestätige den physischen Empfang des Geräts."
                    />
                    @error('acknowledgeReceipt')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <flux:radio.group wire:model.live="resolution" label="Weiteres Vorgehen">
                        <flux:radio
                            value="{{ AssetReturnAdminCompletionService::ResolutionNewOwner }}"
                            label="Neuen Besitzer zuweisen"
                            description="Neue Übergabe an den gewählten Benutzer wird erzeugt."
                        />
                        <flux:radio
                            value="{{ AssetReturnAdminCompletionService::ResolutionSetLocation }}"
                            label="Kein neuer Besitzer — Standort setzen (Auf Lager)"
                            description="Besitzer wird entfernt; Standort ist Pflicht (z. B. Lager). Asset wird als Auf Lager markiert."
                        />
                    </flux:radio.group>
                    @error('resolution')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    @if($resolution === AssetReturnAdminCompletionService::ResolutionNewOwner)
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

                    @if($resolution === AssetReturnAdminCompletionService::ResolutionSetLocation)
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
                    <flux:button :href="route('apps.assets.admin.returns.pending')" variant="ghost" wire:navigate>
                        Zurück zur Liste
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
