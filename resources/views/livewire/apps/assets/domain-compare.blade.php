<?php

use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Domain-Abgleich')] class extends Component
{
    /** @var 'default'|'schulung' */
    #[Url(as: 'domain')]
    public string $connection = 'default';

    public function updatedConnection(): void
    {
        // Force recompute when connection changes
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

    #[Computed]
    public function adHostnamesSet(): \Illuminate\Support\Collection
    {
        return collect($this->adComputers)->pluck('hostname')->filter()->unique()->values();
    }

    /**
     * (a) Assets mit Domain-Typ und dieser Connection, deren Name nicht in AD vorkommt.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Asset>
     */
    #[Computed]
    public function assetsNotInDomain(): \Illuminate\Database\Eloquent\Collection
    {
        $hostnames = $this->adHostnamesSet;
        $domainTypeIds = AssetType::where('is_domain_object', true)->pluck('id');

        return Asset::query()
            ->with(['type', 'vendor', 'owner'])
            ->whereIn('asset_type_id', $domainTypeIds)
            ->where('domain_connection', $this->connection)
            ->whereNotNull('name')
            ->where(fn ($q) => $q->where('name', '!=', ''))
            ->whereNotIn('name', $hostnames->toArray())
            ->orderBy('name')
            ->get();
    }

    /**
     * (b) AD-Hostnames, für die kein Asset mit diesem Namen (und Domain-Typ + dieser Connection) existiert.
     *
     * @return \Illuminate\Support\Collection<int, array{hostname: string, itexia_id: string|null}>
     */
    #[Computed]
    public function domainComputersNotInAssets(): \Illuminate\Support\Collection
    {
        $assetNames = Asset::query()
            ->whereHas('type', fn ($q) => $q->where('is_domain_object', true))
            ->where('domain_connection', $this->connection)
            ->whereNotNull('name')
            ->pluck('name');

        return collect($this->adComputers)
            ->filter(fn ($row) => ! $assetNames->contains($row['hostname']))
            ->values();
    }

    /**
     * AD-Computer ohne Itexia-ID (für gewählte Connection).
     *
     * @return \Illuminate\Support\Collection<int, array{hostname: string, itexia_id: string|null}>
     */
    #[Computed]
    public function adComputersWithoutItexiaId(): \Illuminate\Support\Collection
    {
        return collect($this->adComputers)
            ->filter(fn ($row) => empty($row['itexia_id']))
            ->values();
    }
}; ?>
<div>
<x-intranet-app-assets::assets-layout heading="Domain-Abgleich" subheading="Vergleich: Assets vs. Computer in Active Directory">
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
        @else
            {{-- (a) Assets ohne Domänen-Eintrag --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">Assets ohne Domänen-Eintrag</flux:heading>
                <flux:text class="mb-4">Diese Computer-Assets haben die gewählte Domain-Connection, sind aber in der Domäne (in den konfigurierten OUs) nicht gefunden worden.</flux:text>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Seriennummer</flux:table.column>
                        <flux:table.column>Domäne</flux:table.column>
                        <flux:table.column>Typ / Hersteller</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->assetsNotInDomain as $asset)
                            <flux:table.row wire:key="not-in-domain-{{ $asset->id }}">
                                <flux:table.cell class="font-medium">{{ $asset->name }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $asset->serial_number }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $asset->domain_connection }}</flux:table.cell>
                                <flux:table.cell>{{ $asset->type?->name }} · {{ $asset->vendor?->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button href="{{ route('apps.assets.show', $asset) }}" variant="ghost" size="sm" icon="eye" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-zinc-500 py-6">
                                    Keine passenden Assets gefunden – alle Domain-Assets mit dieser Connection sind in der Domäne vorhanden.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            {{-- (b) Domänen-Computer ohne Asset --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">Domänen-Computer ohne Asset</flux:heading>
                <flux:text class="mb-4">Diese Computer wurden in der Domäne (in den konfigurierten OUs) gefunden, haben aber kein zugehöriges Asset mit diesem Namen und dieser Connection.</flux:text>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Hostname</flux:table.column>
                        <flux:table.column>Itexia-ID (AD)</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->domainComputersNotInAssets as $row)
                            <flux:table.row wire:key="not-in-assets-{{ $row['hostname'] }}">
                                <flux:table.cell class="font-mono font-medium">{{ $row['hostname'] }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $row['itexia_id'] ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2" class="text-center text-zinc-500 py-6">
                                    Keine Computer in der Domäne ohne zugehöriges Asset.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            {{-- Optional: Computer in AD ohne Itexia-ID --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">Computer in AD ohne Itexia-ID</flux:heading>
                <flux:text class="mb-4">Diese Computer wurden in der Domäne gefunden, haben aber keine Itexia-ID im AD-Attribut gesetzt.</flux:text>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Hostname</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($this->adComputersWithoutItexiaId as $row)
                            <flux:table.row wire:key="no-itexia-{{ $row['hostname'] }}">
                                <flux:table.cell class="font-mono font-medium">{{ $row['hostname'] }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell class="text-center text-zinc-500 py-6">
                                    Alle gefundenen Computer haben eine Itexia-ID in AD.
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
