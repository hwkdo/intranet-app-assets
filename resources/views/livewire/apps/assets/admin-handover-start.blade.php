@php
    use Hwkdo\IntranetAppAssets\Support\AdminHandoverChannel;
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Asset übergeben"
        subheading="{{ $asset->display_name }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4 dark:text-white">Asset</flux:heading>
                <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <dt class="font-semibold text-zinc-500 dark:text-white">Anzeigename</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->display_name }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Seriennummer</dt>
                    <dd class="font-mono text-zinc-900 dark:text-white">{{ $asset->serial_number }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Typ</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->type?->name ?? '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Aktueller Besitzer</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->owner?->name ?? '—' }}</dd>
                </dl>
            </flux:card>

            <form wire:submit="submit" class="space-y-6">
                <flux:card class="space-y-4">
                    <flux:heading size="lg" class="dark:text-white">Empfänger</flux:heading>

                    @if($asset->user_id === null)
                        <flux:select
                            wire:model="recipientUserId"
                            variant="listbox"
                            searchable
                            label="Empfänger"
                            placeholder="Benutzer wählen…"
                        >
                            @foreach($this->users as $user)
                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="recipientUserId" />
                    @else
                        <flux:callout variant="subtle" icon="user">
                            <flux:callout.text>
                                Empfänger ist bereits {{ $asset->owner?->name ?? 'gesetzt' }}
                                @if($this->openHandover)
                                    (offene Übergabe #{{ $this->openHandover->id }}).
                                @else
                                    .
                                @endif
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading size="lg" class="dark:text-white">Bestätigung</flux:heading>

                    <flux:radio.group wire:model.live="channel" label="Wie soll die Übergabe bestätigt werden?">
                        <flux:radio
                            value="{{ AdminHandoverChannel::Self }}"
                            label="User bestätigt Übergabe selbst"
                            description="Wie bisher: Empfänger bestätigt später in Meine Assets (Passwort / Signopad / Touchscreen)."
                        />
                        <flux:radio
                            value="{{ AdminHandoverChannel::PasswordNow }}"
                            label="User bestätigt jetzt per Passwort"
                            description="Empfänger gibt sein Windows-/LDAP-Passwort jetzt am Admin-PC ein. Sie bleiben angemeldet."
                        />
                        <flux:radio
                            value="{{ AdminHandoverChannel::SignopadZentrale }}"
                            label="User bestätigt an der Zentrale per Signopad"
                            description="Die Übergabe erscheint in der Zentrale-Warteschlange. Empfänger unterschreibt dort am Signopad."
                        />
                    </flux:radio.group>
                    <flux:error name="channel" />
                </flux:card>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="hand-raised">
                        Weiter
                    </flux:button>
                    <flux:button :href="route('apps.assets.liste')" variant="ghost" wire:navigate>
                        Abbrechen
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
