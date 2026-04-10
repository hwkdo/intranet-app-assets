<?php

use Hwkdo\ConfigmgrLaravel\ConfigmgrLaravel;
use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('SCCM-Abgleich')] class extends Component
{
    /** @var 'default'|'schulung' */
    #[Url(as: 'domain')]
    public string $connection = 'default';

    public function updatedConnection(): void
    {
        // Recompute when connection changes
    }

    #[Computed]
    public function connectionLabel(): string
    {
        return config('intranet-app-assets.domain_connections.'.$this->connection, $this->connection);
    }

    #[Computed]
    public function ldapAvailable(): bool
    {
        return app()->bound(LdapComputerServiceInterface::class);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function ouDns(): array
    {
        $settings = IntranetAppAssetsSettings::current()?->settings;
        $ous = $settings?->computerSearchOus ?? ['default' => [], 'schulung' => []];
        $list = $ous[$this->connection] ?? [];

        return is_array($list) ? $list : [];
    }

    /**
     * @return array<int, array{hostname: string, itexia_id: string|null}>
     */
    #[Computed]
    public function adComputers(): array
    {
        if (! $this->ldapAvailable || $this->ouDns === []) {
            return [];
        }
        try {
            return app(LdapComputerServiceInterface::class)->getComputersInOus($this->connection, $this->ouDns);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * SCCM-Abfrage: ok, Namensliste, Fehlercode, Fehlertext.
     *
     * @return array{ok: bool, names: array<int, string>, reason: ?string, error: ?string}
     */
    #[Computed]
    public function sccmData(): array
    {
        $defaults = (new AppSettings)->sccmResourceDomains;
        $stored = IntranetAppAssetsSettings::current()?->settings;
        $storedDomains = is_array($stored?->sccmResourceDomains) ? $stored->sccmResourceDomains : [];
        $map = array_merge($defaults, $storedDomains);
        $raw = $map[$this->connection] ?? '';
        $domain = is_string($raw) ? trim($raw) : '';
        if ($domain === '') {
            return ['ok' => false, 'names' => [], 'reason' => 'unconfigured', 'error' => null];
        }

        if (! class_exists(ConfigmgrLaravel::class)) {
            return ['ok' => false, 'names' => [], 'reason' => 'no_package', 'error' => null];
        }

        try {
            $names = app(ConfigmgrLaravel::class)->getDistinctComputerNamesByResourceDomains([$domain]);

            return ['ok' => true, 'names' => $names, 'reason' => null, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'names' => [], 'reason' => 'exception', 'error' => $e->getMessage()];
        }
    }

    /**
     * AD-Computer (konfigurierte OUs), die unter den gefilterten SCCM-Namen nicht vorkommen.
     *
     * @return \Illuminate\Support\Collection<int, array{hostname: string, itexia_id: string|null}>
     */
    #[Computed]
    public function adComputersNotInSccm(): \Illuminate\Support\Collection
    {
        if (! $this->sccmData['ok']) {
            return collect();
        }

        $sccmSet = array_flip($this->sccmData['names']);

        return collect($this->adComputers)
            ->filter(function (array $row) use ($sccmSet): bool {
                $h = strtoupper(trim($row['hostname'] ?? ''));
                if ($h === '') {
                    return false;
                }

                return ! isset($sccmSet[$h]);
            })
            ->values();
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="SCCM-Abgleich" subheading="Vergleich: Computer in Active Directory (OUs) vs. Einträge in ConfigMgr/SCCM pro Domäne">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:select wire:model.live="connection" variant="listbox" label="Domäne" class="max-w-xs">
                <flux:select.option value="default">{{ config('intranet-app-assets.domain_connections.default', 'Verwaltung') }}</flux:select.option>
                <flux:select.option value="schulung">{{ config('intranet-app-assets.domain_connections.schulung', 'Schulung') }}</flux:select.option>
            </flux:select>
        </div>

        @if(!$this->ldapAvailable)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>Der LDAP-Computer-Service ist nicht registriert. Binden Sie <code>LdapComputerServiceInterface</code> in der App (z. B. AppServiceProvider).</flux:callout.text>
            </flux:callout>
        @elseif($this->ouDns === [])
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>Für die gewählte Domäne sind keine Such-OUs konfiguriert. Bitte in den Admin-Einstellungen unter „Computer Search Ous“ die OU-DNs pro Connection eintragen (JSON).</flux:callout.text>
            </flux:callout>
        @elseif($this->sccmData['reason'] === 'unconfigured')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>Für die gewählte Domäne (<strong>{{ $this->connectionLabel }}</strong>) ist keine SCCM-Ressourcendomäne gesetzt. Tragen Sie in den <strong>Assets-App-Einstellungen</strong> unter <code>sccmResourceDomains</code> (Schlüssel <code>{{ $this->connection }}</code>) den NetBIOS-Wert aus <code>v_R_System.Resource_Domain_OR_Workgr0</code> ein.</flux:callout.text>
            </flux:callout>
        @elseif($this->sccmData['reason'] === 'no_package')
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>Das Paket ConfigMgr Laravel ist nicht installiert.</flux:callout.text>
            </flux:callout>
        @elseif($this->sccmData['reason'] === 'exception')
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.text>SCCM-Datenbank nicht erreichbar oder Abfrage fehlgeschlagen: {{ $this->sccmData['error'] }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:card>
                <flux:heading size="lg" class="mb-4">In AD (OUs), aber nicht in SCCM</flux:heading>
                <flux:text class="mb-4">
                    Diese Hostnamen wurden in den konfigurierten Active-Directory-OUs für <strong>{{ $this->connectionLabel }}</strong> gefunden, kommen aber unter der zugeordneten SCCM-Ressourcendomäne nicht in <code>v_R_System</code> vor (Abgleich case-insensitive).
                </flux:text>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Hostname</flux:table.column>
                        <flux:table.column>Itexia-ID (AD)</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->adComputersNotInSccm as $row)
                            <flux:table.row wire:key="ad-not-sccm-{{ $row['hostname'] }}">
                                <flux:table.cell class="font-mono font-medium">{{ $row['hostname'] }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $row['itexia_id'] ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2" class="text-center text-zinc-500 py-6">
                                    Keine Abweichungen – alle gefundenen AD-Computer erscheinen in SCCM (für die gewählte Ressourcendomäne).
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    </div>
</x-intranet-app-assets::assets-layout>
</div>
