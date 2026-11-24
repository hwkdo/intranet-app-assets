@php
    use Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService;
    use Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver;

    $h = $assetReturn->handover;
    $asset = $h?->asset;
    $locationDisplay = $asset ? AssetLocationDisplayResolver::resolve($asset) : null;
    $isLoanReturn = $assetReturn->isLoan();
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="{{ $isLoanReturn ? 'Leihe-Rückgabe bestätigen' : 'Rückgabe abschließen' }}"
        subheading="{{ $asset?->display_name ?? 'Asset' }}"
    >
        <div class="space-y-4">
            <section class="glass-card overflow-hidden p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="lg" class="dark:text-white">Asset</flux:heading>
                    @if($asset)
                        <flux:button
                            :href="route('apps.assets.show', [$asset, 'from' => 'liste'])"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="ghost"
                            size="sm"
                            icon="eye"
                        >
                            Zum Asset
                        </flux:button>
                    @endif
                </div>
                <dl class="hwk-rows">
                    <dt class="font-semibold text-slate-600 dark:text-white">Anzeigename</dt>
                    <dd class="text-[#073070] dark:text-white">
                        @if($asset)
                            <a
                                href="{{ route('apps.assets.show', [$asset, 'from' => 'liste']) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-[var(--color-accent)] hover:underline"
                            >
                                {{ $asset->display_name }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="font-semibold text-slate-600 dark:text-white">Seriennummer</dt>
                    <dd class="font-mono text-[#073070] dark:text-white">{{ $asset?->serial_number ?? '—' }}</dd>
                    <dt class="font-semibold text-slate-600 dark:text-white">Besitzer (laut System)</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $asset?->owner?->name ?? '—' }}</dd>
                    <dt class="font-semibold text-slate-600 dark:text-white">{{ $locationDisplay['label'] ?? 'Standort' }}</dt>
                    @if($asset)
                        <x-intranet-app-assets::asset-location-display :asset="$asset" class="text-[#073070] dark:text-white" />
                    @else
                        <dd class="text-[#073070] dark:text-white">—</dd>
                    @endif
                    <dt class="font-semibold text-slate-600 dark:text-white">{{ $isLoanReturn ? 'Verliehen an' : 'Zurückgegeben von' }}</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $h?->recipient?->name ?? $assetReturn->initiatedBy?->name ?? '—' }}</dd>
                    @if($isLoanReturn && $assetReturn->scheduled_at)
                        <dt class="font-semibold text-slate-600 dark:text-white">Rückgabe-Termin</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $assetReturn->scheduled_at->format('d.m.Y H:i') }}</dd>
                    @endif
                    <dt class="font-semibold text-slate-600 dark:text-white">Eingeleitet am</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $assetReturn->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </dl>
            </section>

            @php $lastNote = $assetReturn->notes()->latest('id')->first(); @endphp
            @if($lastNote)
                <section class="glass-card overflow-hidden space-y-2 text-sm">
                    <flux:heading size="sm" class="dark:text-white">Hinweis</flux:heading>
                    <flux:text class="whitespace-pre-wrap text-slate-600 dark:text-white/70">{{ $lastNote->note }}</flux:text>
                </section>
            @endif

            @if($isLoanReturn)
                <flux:callout variant="subtle" icon="archive-box">
                    <flux:callout.heading>Leihe zurücknehmen</flux:callout.heading>
                    <flux:callout.text>
                        Nach Bestätigung des physischen Empfangs wird das Asset
                        <strong>ohne Besitzer</strong> und wieder <strong>Auf Lager</strong> gesetzt.
                        Der <strong>Standort ist Pflicht</strong>, damit klar ist, wo sich das Gerät befindet.
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if($asset && ! $asset->trashed() && ! $isLoanReturn)
                <section class="glass-card overflow-hidden space-y-3 border-red-200 dark:border-red-900/40">
                    <flux:heading size="sm" class="dark:text-white">Asset löschen</flux:heading>
                    <flux:text class="text-sm text-slate-600 dark:text-white/70">
                        Zur regulären Löschseite mit Löschgrund, Verlauf und LDAP-Bestätigung.
                    </flux:text>
                    <flux:button href="{{ route('apps.assets.delete', $asset) }}" variant="danger" icon="trash">
                        Zur Löschseite
                    </flux:button>
                </section>
            @endif

            <form wire:submit="submit" class="hwk-fields glass-card space-y-6 p-5 sm:p-6" style="overflow: visible">
                <div class="space-y-4">
                    <flux:checkbox
                        wire:model="acknowledgeReceipt"
                        label="Ich bestätige den physischen Empfang des Geräts."
                    />
                    @error('acknowledgeReceipt')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    @if($isLoanReturn)
                        <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                        @error('location')
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @else
                        <flux:radio.group wire:model.live="resolution" label="Weiteres Vorgehen">
                            <flux:radio
                                value="{{ AssetReturnAdminCompletionService::ResolutionNewOwner }}"
                                label="Neuen Besitzer zuweisen"
                                description="Neue Übergabe an den gewählten Benutzer wird erzeugt."
                            />
                            <flux:radio
                                value="{{ AssetReturnAdminCompletionService::ResolutionSetLocation }}"
                                label="Kein neuer Besitzer — Standort setzen"
                                description="Besitzer wird entfernt. Standort und Gerätetyp (Lager oder Gemeinschaftsgerät) sind Pflicht."
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
                            <x-intranet-app-assets::unowned-device-type-select
                                class="mt-4"
                                wire-model="deviceType"
                                error-name="deviceType"
                            />
                        @endif
                    @endif
                </div>

                <flux:text class="text-sm text-slate-600 dark:text-white/70">
                    Beim Speichern werden Sie zur Bestätigung mit Ihrem LDAP-/Windows-Passwort weitergeleitet.
                </flux:text>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ $isLoanReturn ? 'Empfang bestätigen und Auf Lager legen' : 'Speichern und abschließen' }}
                    </flux:button>
                    <flux:button :href="route('apps.assets.admin.returns.pending')" variant="ghost" wire:navigate>
                        Zurück zur Liste
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
