@php
    use Hwkdo\IntranetAppAssets\Support\AdminHandoverChannel;
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Asset verleihen"
        subheading="{{ $asset->display_name }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <flux:callout variant="subtle" icon="clock">
                <flux:callout.heading>Verleih mit Rückgabe-Termin</flux:callout.heading>
                <flux:callout.text>
                    Das Lager-Asset wird an einen aktiven Mitarbeiter ausgegeben. Nach Bestätigung der Übergabe
                    wird automatisch eine geplante Rückgabe bis zum gewählten Termin angelegt (Erinnerungen wie bei geplanter Rückgabe).
                    Eine Ablehnung durch den Empfänger ist bei Verleihen nicht möglich.
                </flux:callout.text>
            </flux:callout>

            <flux:card>
                <flux:heading size="lg" class="mb-4 dark:text-white">Asset</flux:heading>
                <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <dt class="font-semibold text-zinc-500 dark:text-white">Anzeigename</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->display_name }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Seriennummer</dt>
                    <dd class="font-mono text-zinc-900 dark:text-white">{{ $asset->serial_number }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Typ</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->type?->name ?? '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Standort</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->location ?: '—' }}</dd>
                </dl>
            </flux:card>

            <form wire:submit="submit" class="space-y-6">
                <flux:card class="space-y-4">
                    <flux:heading size="lg" class="dark:text-white">Empfänger</flux:heading>

                    <flux:select
                        wire:model="recipientUserId"
                        variant="listbox"
                        searchable
                        label="Aktiver Mitarbeiter"
                        placeholder="Benutzer wählen…"
                    >
                        @foreach($users as $user)
                            <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="recipientUserId" />
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading size="lg" class="dark:text-white">Rückgabe-Termin</flux:heading>

                    <flux:callout variant="subtle" icon="information-circle">
                        <flux:callout.text>
                            Der Termin muss mindestens {{ $this->reminder2Hours() }} Stunden in der Zukunft liegen
                            und darf maximal {{ $this->loanMaxDays() }} Tage vorausliegen. Der Empfänger erhält Erinnerungen vor dem Termin.
                        </flux:callout.text>
                    </flux:callout>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="scheduledDate"
                            type="date"
                            label="Datum"
                            :min="$this->minScheduleDate()"
                            :max="$this->maxScheduleDate()"
                            required
                        />
                        <flux:input
                            wire:model="scheduledTime"
                            type="time"
                            label="Uhrzeit"
                            required
                        />
                    </div>
                    <flux:error name="scheduledDate" />
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:heading size="lg" class="dark:text-white">Bestätigung</flux:heading>

                    <flux:radio.group wire:model.live="channel" label="Wie soll die Übergabe bestätigt werden?">
                        <flux:radio
                            value="{{ AdminHandoverChannel::Self }}"
                            label="User bestätigt Übergabe selbst"
                            description="Empfänger bestätigt später in Meine Assets (ohne Ablehnen-Option)."
                        />
                        <flux:radio
                            value="{{ AdminHandoverChannel::PasswordNow }}"
                            label="User bestätigt jetzt per Passwort"
                            description="Empfänger gibt sein Windows-/LDAP-Passwort jetzt am Admin-PC ein."
                        />
                        <flux:radio
                            value="{{ AdminHandoverChannel::SignopadZentrale }}"
                            label="User bestätigt an der Zentrale per Signopad"
                            description="Die Übergabe erscheint in der Zentrale-Warteschlange."
                        />
                    </flux:radio.group>
                    <flux:error name="channel" />
                </flux:card>

                <flux:card class="space-y-4">
                    <flux:textarea
                        wire:model="note"
                        label="Hinweis (optional)"
                        placeholder="z. B. Zweck der Leihe …"
                        rows="4"
                    />
                    <flux:error name="note" />
                </flux:card>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="clock">
                        Verleihen
                    </flux:button>
                    <flux:button :href="route('apps.assets.liste')" variant="ghost" wire:navigate>
                        Abbrechen
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
