<div>
    <x-intranet-app-assets::assets-layout
        heading="{{ $this->flowHeading() }}"
        subheading="Bitte prüfen Sie die Auswahl und bestätigen Sie die Aktion mit Ihrem LDAP-Passwort."
    >
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="glass-card overflow-hidden space-y-3">
                <flux:heading size="sm" class="dark:text-white">Zusammenfassung</flux:heading>
                <flux:text class="text-sm text-slate-600 dark:text-white/70">
                    <strong class="text-[#073070] dark:text-white">Vorgang:</strong> {{ $this->resolutionSummary() }}
                </flux:text>
                @foreach($this->extraSummaryLines() as $line)
                    <flux:text class="text-sm whitespace-pre-wrap text-slate-600 dark:text-white/70">{{ $line }}</flux:text>
                @endforeach
                <flux:text class="text-sm text-slate-600 dark:text-white/70">
                    <strong class="text-[#073070] dark:text-white">Anzahl Datensätze:</strong> {{ count($workflow['ids'] ?? []) }}
                </flux:text>
            </section>

            <section class="glass-card overflow-hidden p-5">
                <flux:heading size="sm" class="mb-3 dark:text-white">Ausgewählte Einträge</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Asset / Vorgang</flux:table.column>
                        <flux:table.column>Details</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->previewRows() as $row)
                            <flux:table.row wire:key="bulk-preview-{{ $loop->index }}">
                                <flux:table.cell class="font-medium">{{ $row['primary'] }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-slate-600 dark:text-white/70">{{ $row['secondary'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </section>

            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('apps.assets.admin.bulk.commit')" variant="primary" icon="lock-closed">
                    Mit LDAP-Passwort bestätigen und ausführen
                </flux:button>
                <flux:button :href="$this->backUrl()" variant="ghost" wire:navigate>
                    Zurück zur Übersicht
                </flux:button>
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
