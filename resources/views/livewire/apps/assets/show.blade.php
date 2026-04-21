<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Contracts\LdapComputerServiceInterface;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Support\AssetShowBackOrigin;
use Hwkdo\IntranetAppAssets\Support\DmsLinkHelper;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Asset Details')] class extends Component
{
    public Asset $asset;

    #[Url(except: null)]
    public ?string $from = null;

    /** Suchbegriff für „Zurück zur Suche“ (URL-Parameter `sq`, wird als `q` an die Suchseite übergeben). */
    #[Url(as: 'sq', except: null)]
    public ?string $searchReturnQuery = null;

    #[Validate('required|string|min:3')]
    public string $newNote = '';

    public bool $showD3InvoiceModal = false;

    /**
     * Gültige Rechnungsnummer: beginnt mit "T", danach nur Ziffern.
     */
    public static function isInvalidInvoiceNumber(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return ! preg_match('/^T\d+$/', trim($value));
    }

    public function mount(Asset $asset): void
    {
        $this->asset = $asset->load([
            'type',
            'vendor',
            'owner',
            'historyEntries.user',
            'notes.author',
            'attachments.uploader',
            'handovers.recipient',
            'handovers.issuer',
            'handovers.notes.author',
            'handovers.assetReturn.recipient',
            'handovers.assetReturn.notes.author',
        ]);

    }

    #[Computed]
    public function history(): Collection
    {
        $entries = collect();

        foreach ($this->asset->historyEntries as $historyEntry) {
            $entries->push([
                'type' => 'asset_history',
                'date' => $historyEntry->created_at,
                'model' => $historyEntry,
            ]);
        }

        foreach ($this->asset->notes as $note) {
            $entries->push([
                'type' => 'note',
                'date' => $note->created_at,
                'model' => $note,
            ]);
        }

        foreach ($this->asset->attachments as $attachment) {
            $entries->push([
                'type' => 'attachment',
                'date' => $attachment->created_at,
                'model' => $attachment,
            ]);
        }

        foreach ($this->asset->handovers as $handover) {
            $entries->push([
                'type' => 'handover',
                'date' => $handover->created_at,
                'model' => $handover,
            ]);

            foreach ($handover->notes as $note) {
                $entries->push([
                    'type' => 'note',
                    'date' => $note->created_at,
                    'model' => $note,
                ]);
            }

            if ($handover->assetReturn) {
                $entries->push([
                    'type' => 'return',
                    'date' => $handover->assetReturn->created_at,
                    'model' => $handover->assetReturn,
                ]);

                foreach ($handover->assetReturn->notes as $note) {
                    $entries->push([
                        'type' => 'note',
                        'date' => $note->created_at,
                        'model' => $note,
                    ]);
                }
            }
        }

        return $entries->sortByDesc('date')->values();
    }

    #[Computed]
    public function handoversSorted(): \Illuminate\Support\Collection
    {
        return $this->asset->handovers->sortByDesc('created_at')->values();
    }

    #[Computed]
    public function dmsBaseUrl(): string
    {
        $fromSettings = trim(IntranetAppAssetsSettings::current()?->settings?->dmsBaseUrl ?? '');

        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return DmsLinkHelper::baseUrlFromDmsSearchUrl(config('d3-rest-laravel.dms-search-url', ''));
    }

    public function invoiceNumberLink(?string $number): ?string
    {
        return DmsLinkHelper::invoiceUrl($this->dmsBaseUrl, $number);
    }

    public function orderNumberLink(?string $number): ?string
    {
        return DmsLinkHelper::orderNumberUrl($this->dmsBaseUrl, $number);
    }

    #[Computed]
    public function assetImageUrl(): ?string
    {
        return $this->asset->getFirstMedia('image')?->getFullUrl();
    }

    #[Computed]
    public function assetThumbnailUrl(): ?string
    {
        $thumb = $this->asset->getFirstMedia('thumbnail');
        if ($thumb !== null) {
            return $thumb->getFullUrl();
        }

        return $this->assetImageUrl;
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|min:3']);

        $this->asset->notes()->create([
            'note' => $this->newNote,
            'user_id' => auth()->id(),
        ]);

        $this->newNote = '';
        $this->mount($this->asset->fresh([
            'type', 'vendor', 'owner', 'notes.author',
            'attachments.uploader', 'handovers.recipient',
            'handovers.issuer', 'handovers.notes.author',
            'handovers.assetReturn.recipient', 'handovers.assetReturn.notes.author',
        ]));

        Flux::toast('Notiz wurde hinzugefügt.', variant: 'success');
    }

    public function openD3InvoiceModal(): void
    {
        $this->showD3InvoiceModal = true;
    }

    #[On('invoice-number-set')]
    public function refreshAssetAndCloseD3InvoiceModal(): void
    {
        $this->showD3InvoiceModal = false;
        $this->asset = $this->asset->fresh(['type', 'vendor', 'owner']);
    }

    #[On('asset-updated')]
    public function refreshAsset(): void
    {
        $this->asset = $this->asset->fresh([
            'type', 'vendor', 'owner',
            'historyEntries.user', 'notes.author', 'attachments.uploader',
            'handovers.recipient', 'handovers.issuer', 'handovers.notes.author',
            'handovers.assetReturn.recipient', 'handovers.assetReturn.notes.author',
        ]);
    }

    #[Computed]
    public function ldapItexiaId(): ?string
    {
        if (! $this->asset->type?->is_domain_object || ! $this->asset->domain_last_seen || ! filled($this->asset->name) || ! filled($this->asset->domain_connection)) {
            return null;
        }
        if (! app()->bound(LdapComputerServiceInterface::class)) {
            return null;
        }
        try {
            return app(LdapComputerServiceInterface::class)->getItexiaId($this->asset->name, $this->asset->domain_connection);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setItexiaIdInLdap(): void
    {
        if (! filled($this->asset->itexia_id) || ! filled($this->asset->name) || ! filled($this->asset->domain_connection)) {
            Flux::toast('Itexia-ID, Name und Domain-Connection müssen gesetzt sein.', variant: 'error');

            return;
        }
        if (! app()->bound(LdapComputerServiceInterface::class)) {
            Flux::toast('LDAP-Computer-Service ist nicht verfügbar.', variant: 'error');

            return;
        }
        try {
            $ok = app(LdapComputerServiceInterface::class)->setItexiaId($this->asset->name, $this->asset->itexia_id, $this->asset->domain_connection);
            if ($ok) {
                Flux::toast('Itexia-ID wurde erfolgreich in AD gesetzt.', variant: 'success');
                $this->dispatch('asset-updated');
            } else {
                Flux::toast('Computer in der Domäne nicht gefunden.', variant: 'error');
            }
        } catch (\Throwable $e) {
            Flux::toast('Fehler: '.$e->getMessage(), variant: 'error');
        }
    }

    #[Computed]
    public function returnInitiatableHandover(): ?Handover
    {
        if ($this->asset->user_id === null) {
            return null;
        }

        return Handover::query()
            ->where('asset_id', $this->asset->id)
            ->where('recipient_user_id', $this->asset->user_id)
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->whereDoesntHave('assetReturns')
            ->latest('confirmed_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{key: string, href: string, label: string, buttonLabel: string}
     */
    #[Computed]
    public function assetShowBack(): array
    {
        return AssetShowBackOrigin::resolve($this->from, auth()->user(), $this->searchReturnQuery);
    }

}; ?>
<div>
<x-intranet-app-assets::assets-layout
    heading="{{ $asset->display_name }}"
    subheading="{{ $asset->type?->name }} · {{ $asset->vendor?->name }}"
>
    <div class="space-y-8">

        {{-- Aktionsleiste --}}
        <div class="flex items-center gap-3">
            @php
                $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
                $canInitiateReturnFromShow = (($asset->user_id !== null && (int) $asset->user_id === (int) auth()->id()) || $isAdmin);
                $returnHandover = $canInitiateReturnFromShow ? $this->returnInitiatableHandover : null;
            @endphp
            @can('manage-app-assets')
                <flux:button href="{{ route('apps.assets.edit', array_filter(['asset' => $asset, 'from' => $this->assetShowBack['key'], 'sq' => $this->searchReturnQuery], fn ($v) => $v !== null && $v !== '')) }}" variant="primary" icon="pencil" size="sm">
                    Bearbeiten
                </flux:button>

                @if(! $asset->trashed())
                    <flux:button href="{{ route('apps.assets.delete', array_filter(['asset' => $asset, 'from' => $this->assetShowBack['key'], 'sq' => $this->searchReturnQuery], fn ($v) => $v !== null && $v !== '')) }}" variant="danger" icon="trash" size="sm">
                        Löschen
                    </flux:button>
                @endif
            @endcan

            @if($returnHandover)
                <flux:button
                    href="{{ route('apps.assets.handover.return.initiate', $returnHandover) }}"
                    variant="primary"
                    icon="arrow-uturn-left"
                    size="sm"
                >
                    Rückgabe einleiten
                </flux:button>
            @endif

            <flux:button href="{{ $this->assetShowBack['href'] }}" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                {{ $this->assetShowBack['buttonLabel'] }}
            </flux:button>
        </div>

        {{-- Asset-Stammdaten --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:card class="overflow-hidden transition-colors hover:border-zinc-400/60 dark:hover:border-zinc-500/60">
                <div class="space-y-4">
                    <flux:heading size="lg">Stammdaten</flux:heading>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @if(filled($asset->name))
                        <dt class="font-semibold">Name</dt>
                        <dd>{{ $asset->name }}</dd>
                    @endif

                    <dt class="font-semibold">Seriennummer</dt>
                    <dd class="font-mono">{{ $asset->serial_number }}</dd>

                    @if(filled($asset->imei))
                        <dt class="font-semibold">IMEI</dt>
                        <dd class="font-mono">{{ $asset->imei }}</dd>
                    @endif

                    @if(filled($asset->smbios_guid))
                        <dt class="font-semibold">SMBIOS-GUID</dt>
                        <dd class="font-mono text-xs">{{ $asset->smbios_guid }}</dd>
                    @endif

                    @if(filled($asset->configmgr_last_logon_user))
                        <dt class="font-semibold">Last-Logon-User</dt>
                        <dd>{{ $asset->configmgr_last_logon_user }}</dd>
                    @endif

                    @if(is_array($asset->configmgr_mac_addresses) && count($asset->configmgr_mac_addresses) > 0)
                        <dt class="font-semibold">MAC-Adresse(n)</dt>
                        <dd class="font-mono text-xs">
                            <ul class="list-inside list-disc space-y-0.5">
                                @foreach($asset->configmgr_mac_addresses as $mac)
                                    <li>{{ $mac }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    @endif

                    <dt class="font-semibold">Modell</dt>
                    <dd>{{ $asset->model }}</dd>

                    <dt class="font-semibold">Typ</dt>
                    <dd>{{ $asset->type?->name ?? '—' }}</dd>

                    <dt class="font-semibold">Hersteller</dt>
                    <dd>{{ $asset->vendor?->name ?? '—' }}</dd>

                    <dt class="font-semibold">Besitzer</dt>
                    <dd>{{ $asset->owner?->name ?? '—' }}</dd>

                    <dt class="font-semibold">Standort</dt>
                    <dd>{{ $asset->location ?? '—' }}</dd>

                    <dt class="font-semibold">Itexia-ID</dt>
                    <dd>
                        @if($asset->itexia_id)
                            <div class="flex flex-col gap-1">
                                <span class="font-mono">{{ $asset->itexia_id }}</span>
                                @if(filled($asset->itexia_uuid))
                                    <span class="text-xs">
                                        UUID:
                                        <span class="font-mono">{{ $asset->itexia_uuid }}</span>
                                    </span>
                                @endif
                            </div>
                        @else
                            —
                            @if($asset->type?->itexia_creation_allowed)
                                <flux:text class="text-xs text-zinc-500">Bitte zuerst Itexia-ID (Barcode) eintragen.</flux:text>
                            @endif
                        @endif
                    </dd>

                    @if(filled($asset->intune_last_check_in))
                        <dt class="font-semibold">Letzter Check-in</dt>
                        <dd>{{ $asset->intune_last_check_in->format('d.m.Y H:i') }}</dd>
                    @endif

                    <dt class="font-semibold">Bestellnummer</dt>
                    <dd>
                        @php $orderLink = $this->orderNumberLink($asset->order_number); @endphp
                        @if($orderLink)
                            <a href="{{ $orderLink }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 underline hover:no-underline">{{ $asset->order_number }}</a>
                        @else
                            {{ $asset->order_number ?? '—' }}
                        @endif
                    </dd>

                    <dt class="font-semibold">Rechnungsnummer</dt>
                    <dd class="flex flex-col gap-2">
                        @if(filled($asset->invoice_number))
                            @php $invoiceLink = $this->invoiceNumberLink($asset->invoice_number); @endphp
                            @if($invoiceLink)
                                <a href="{{ $invoiceLink }}" target="_blank" rel="noopener noreferrer" class="font-mono text-primary-600 dark:text-primary-400 underline hover:no-underline">{{ $asset->invoice_number }}</a>
                            @else
                                <span class="font-mono">{{ $asset->invoice_number }}</span>
                            @endif
                            @if($this->isInvalidInvoiceNumber($asset->invoice_number))
                                <flux:callout variant="warning" icon="exclamation-triangle" class="mt-0">
                                    <flux:callout.text>Das Format der Rechnungsnummer ist ungültig. Eine gültige Rechnungsnummer beginnt mit „T“ und enthält danach nur Ziffern (z. B. T12345).</flux:callout.text>
                                </flux:callout>
                            @endif
                        @else
                            <span class="opacity-70">—</span>
                        @endif
                        @can('manage-app-assets')
                            @if(!filled($asset->invoice_number) || $this->isInvalidInvoiceNumber($asset->invoice_number))
                                <flux:button wire:click="openD3InvoiceModal" variant="outline" size="sm" icon="document-magnifying-glass">
                                    Rechnung in D3 suchen
                                </flux:button>
                            @endif
                        @endcan
                    </dd>

                    <dt class="font-semibold">Erstellt</dt>
                    <dd>{{ $asset->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>

                    <dt class="font-semibold">Aktualisiert</dt>
                    <dd>{{ $asset->updated_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </dl>
                </div>
            </flux:card>

            <flux:card class="overflow-hidden transition-colors hover:border-zinc-400/60 dark:hover:border-zinc-500/60">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="lg">Status</flux:heading>
                        @if($asset->is_missing)
                            <flux:badge color="red" size="lg" icon="exclamation-triangle">Vermisst</flux:badge>
                        @endif
                        @if($asset->is_clarification)
                            <flux:badge color="amber" size="lg" icon="question-mark-circle">In Klärung</flux:badge>
                        @endif
                        @if(!$asset->is_missing && !$asset->is_clarification)
                            <flux:badge color="green" size="lg" icon="check-circle">Aktiv</flux:badge>
                        @endif
                    </div>

                    <div class="space-y-2">
                        @if($this->assetThumbnailUrl)
                            <div class="text-sm font-semibold">Bild</div>
                            <a
                                href="{{ $this->assetImageUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-block overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <img
                                    src="{{ $this->assetThumbnailUrl }}"
                                    alt="Asset-Bild {{ $asset->display_name }}"
                                    class="h-40 w-40 object-cover"
                                    loading="lazy"
                                >
                            </a>
                        @endif
                    </div>

                    @if($asset->type?->is_domain_object)
                    <div class="mt-4 space-y-3">
                        <flux:heading size="sm" class="font-semibold">Domain</flux:heading>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <dt class="font-semibold">Last Seen</dt>
                            <dd>{{ $asset->domain_last_seen?->format('d.m.Y H:i') ?? 'N/A' }}</dd>

                            <dt class="font-semibold">Last Checked</dt>
                            <dd>{{ $asset->domain_last_checked?->format('d.m.Y H:i') ?? 'N/A' }}</dd>

                            <dt class="font-semibold">Last Logon</dt>
                            <dd>{{ $asset->last_logon?->format('d.m.Y H:i') ?? 'N/A' }}</dd>

                            <dt class="font-semibold">Last Logon Timestamp</dt>
                            <dd>{{ $asset->last_logon_timestamp?->format('d.m.Y H:i') ?? 'N/A' }}</dd>

                            <dt class="font-semibold">Connection</dt>
                            <dd class="font-mono text-xs">{{ $asset->domain_connection ?? 'N/A' }}</dd>

                            @if($asset->domain_last_seen && filled($asset->name) && filled($asset->domain_connection))
                                <dt class="font-semibold">Itexia-ID (AD)</dt>
                                <dd class="flex flex-wrap items-center gap-2">
                                    @if($this->ldapItexiaId !== null)
                                        <span class="font-mono">{{ $this->ldapItexiaId }}</span>
                                    @else
                                        —
                                        @can('manage-app-assets')
                                            @if(filled($asset->itexia_id))
                                                <flux:button wire:click="setItexiaIdInLdap" variant="outline" size="sm" icon="cloud-arrow-up">
                                                    Itexia-ID in AD setzen
                                                </flux:button>
                                            @endif
                                        @endcan
                                    @endif
                                </dd>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Intune- und Itexia-Daten (lazy, laden erst beim Öffnen der Accordions) – untereinander rechts von Stammdaten --}}
                <div class="mt-4 space-y-3">
                    @if($asset->type?->is_intune_object)
                        <livewire:intranet-app-assets::apps.assets.intune-data
                            :serial-number="$asset->serial_number"
                            :intune-device-id="$asset->intune_device_id"
                            lazy
                            wire:key="intune-data-{{ $asset->id }}"
                        />
                    @endif
                    @if($asset->itexia_id)
                        <livewire:intranet-app-assets::apps.assets.itexia-data
                            :itexia-id="$asset->itexia_id"
                            :asset-id="$asset->id"
                            lazy
                            wire:key="itexia-data-{{ $asset->id }}"
                        />
                    @endif
                    @if($asset->type?->is_domain_object)
                        <livewire:intranet-app-assets::apps.assets.configmgr-data
                            :asset-id="$asset->id"
                            :computer-name="$asset->name"
                            lazy
                            wire:key="configmgr-data-{{ $asset->id }}"
                        />
                    @endif
                </div>
                </div>
            </flux:card>
        </div>

        {{-- Übergaben --}}
        <div class="space-y-3">
            <flux:heading size="lg">Übergaben</flux:heading>
            @if($this->handoversSorted->isEmpty())
                <flux:text class="text-zinc-500">Noch keine Übergaben für dieses Asset.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Datum</flux:table.column>
                        <flux:table.column>Empfänger</flux:table.column>
                        <flux:table.column>Ausgestellt von</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->handoversSorted as $handover)
                            <flux:table.row wire:key="handover-{{ $handover->id }}">
                                <flux:table.cell>{{ $handover->created_at->format('d.m.Y H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $handover->recipient?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $handover->issuer?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if($handover->isConfirmed())
                                        <flux:badge color="green" size="sm">Bestätigt</flux:badge>
                                        @if($handover->confirmed_at)
                                            <span class="text-xs text-zinc-500">{{ $handover->confirmed_at->format('d.m.Y') }}</span>
                                        @endif
                                    @else
                                        <flux:badge color="amber" size="sm">Offen</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button href="{{ route('apps.assets.handover.show', $handover) }}" variant="ghost" size="sm" icon="eye">
                                        Details
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        {{-- Notiz hinzufügen --}}
        <div class="space-y-3">
            <flux:heading size="lg">Notiz hinzufügen</flux:heading>
            <form wire:submit="addNote" class="flex gap-3 items-start">
                <div class="flex-1">
                    <flux:textarea
                        wire:model="newNote"
                        placeholder="Neue Notiz eingeben…"
                        rows="2"
                    />
                    <flux:error name="newNote" />
                </div>
                <flux:button type="submit" variant="primary" icon="plus" class="mt-0.5">
                    Hinzufügen
                </flux:button>
            </form>
        </div>

        {{-- History Timeline --}}
        <div class="space-y-3">
            <flux:heading size="lg">Verlauf</flux:heading>

            @if($this->history->isEmpty())
                <flux:text class="text-zinc-500">Noch keine Ereignisse vorhanden.</flux:text>
            @else
                <flux:timeline>
                    @foreach($this->history as $entry)
                        @if($entry['type'] === 'asset_history')
                            @php
                                $historyEntry = $entry['model'];
                                $histEvent = $historyEntry->event;
                                $histDeleted = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventDeleted;
                                $histUpdated = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventUpdated;
                                $histRestored = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventRestored;
                                $histMailSent = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventItexiaInventoryMailSent;
                                $histMailFailed = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventItexiaInventoryMailFailed;
                                $histItexiaMissing = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventItexiaNotFoundOnDelete;
                                $histSeventhingsOff = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventItexiaSeventhingsUnavailableOnDelete;
                                $histHandoverRejected = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventHandoverRejectedByRecipient;
                                $histRejectionAck = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventHandoverRejectionAdminAcknowledged;
                                $histRejectionNewOwner = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventHandoverRejectionAdminResolvedNewOwner;
                                $histRejectionLocation = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventHandoverRejectionAdminResolvedLocation;
                                $histRejectionMissing = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventHandoverRejectionAdminResolvedMissing;
                                $histOwnerClarification = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventOwnerRequestedClarification;
                                $histClarClear = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventClarificationAdminResolvedCleared;
                                $histClarNewOwner = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventClarificationAdminResolvedNewOwner;
                                $histClarLocation = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventClarificationAdminResolvedLocation;
                                $histClarMissing = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventClarificationAdminResolvedMissing;
                                $histReturnInitiated = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventReturnInitiatedByHolder;
                                $histReturnCompleted = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventReturnCompletedByAdmin;
                                $histHandoverConfirmCleared = \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventHandoverConfirmedStatusCleared;
                                $histIndicatorColor = match ($histEvent) {
                                    $histDeleted => 'red',
                                    $histRestored => 'green',
                                    $histMailSent => 'blue',
                                    $histMailFailed => 'red',
                                    $histItexiaMissing => 'amber',
                                    $histSeventhingsOff => 'zinc',
                                    $histHandoverRejected => 'red',
                                    $histRejectionAck => 'blue',
                                    $histRejectionNewOwner, $histRejectionLocation, $histRejectionMissing => 'green',
                                    $histOwnerClarification => 'amber',
                                    $histClarClear, $histClarNewOwner, $histClarLocation, $histClarMissing => 'green',
                                    $histReturnInitiated => 'amber',
                                    $histReturnCompleted => 'green',
                                    $histHandoverConfirmCleared => 'green',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator color="{{ $histIndicatorColor }}">
                                    @if($histEvent === $histDeleted)
                                        <flux:icon.trash variant="micro" />
                                    @elseif($histEvent === $histUpdated)
                                        <flux:icon.wrench variant="micro" />
                                    @elseif($histEvent === $histRestored)
                                        <flux:icon.arrow-uturn-left variant="micro" />
                                    @elseif($histEvent === $histMailSent)
                                        <flux:icon.envelope variant="micro" />
                                    @elseif($histEvent === $histMailFailed)
                                        <flux:icon.exclamation-triangle variant="micro" />
                                    @elseif($histEvent === $histItexiaMissing)
                                        <flux:icon.magnifying-glass variant="micro" />
                                    @elseif($histEvent === $histSeventhingsOff)
                                        <flux:icon.cloud variant="micro" />
                                    @elseif($histEvent === $histHandoverRejected)
                                        <flux:icon.x-circle variant="micro" />
                                    @elseif($histEvent === $histRejectionAck)
                                        <flux:icon.shield-check variant="micro" />
                                    @elseif(in_array($histEvent, [$histRejectionNewOwner, $histRejectionLocation, $histRejectionMissing], true))
                                        <flux:icon.arrow-path variant="micro" />
                                    @elseif($histEvent === $histOwnerClarification)
                                        <flux:icon.question-mark-circle variant="micro" />
                                    @elseif(in_array($histEvent, [$histClarClear, $histClarNewOwner, $histClarLocation, $histClarMissing], true))
                                        <flux:icon.arrow-path variant="micro" />
                                    @elseif($histEvent === $histReturnInitiated)
                                        <flux:icon.arrow-uturn-left variant="micro" />
                                    @elseif($histEvent === $histReturnCompleted)
                                        <flux:icon.arrow-path variant="micro" />
                                    @elseif($histEvent === $histHandoverConfirmCleared)
                                        <flux:icon.check-circle variant="micro" />
                                    @else
                                        <flux:icon.wrench variant="micro" />
                                    @endif
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading>
                                        @if($histEvent === $histDeleted)
                                            Gelöscht
                                        @elseif($histEvent === $histUpdated)
                                            Asset aktualisiert
                                        @elseif($histEvent === $histRestored)
                                            Wiederhergestellt
                                        @elseif($histEvent === $histMailSent)
                                            Inventar: E-Mail-Benachrichtigung
                                        @elseif($histEvent === $histMailFailed)
                                            Inventar: E-Mail fehlgeschlagen
                                        @elseif($histEvent === $histItexiaMissing)
                                            Itexia-ID in Seventhings nicht gefunden
                                        @elseif($histEvent === $histSeventhingsOff)
                                            Seventhings-Abgleich nicht möglich
                                        @elseif($histEvent === $histHandoverRejected)
                                            Übergabe vom Empfänger abgelehnt
                                        @elseif($histEvent === $histRejectionAck)
                                            Abgelehnte Übergabe: Admin-Bestätigung (nicht beim Benutzer)
                                        @elseif($histEvent === $histRejectionNewOwner)
                                            Abgelehnte Übergabe: Neuer Besitzer zugewiesen
                                        @elseif($histEvent === $histRejectionLocation)
                                            Abgelehnte Übergabe: Besitzer entfernt, Standort gesetzt
                                        @elseif($histEvent === $histRejectionMissing)
                                            Abgelehnte Übergabe: Als vermisst markiert
                                        @elseif($histEvent === $histOwnerClarification)
                                            Klärung vom Besitzer angefordert
                                        @elseif($histEvent === $histClarClear)
                                            Klärung: ohne Änderung abgeschlossen
                                        @elseif($histEvent === $histClarNewOwner)
                                            Klärung: Neuer Besitzer zugewiesen
                                        @elseif($histEvent === $histClarLocation)
                                            Klärung: Besitzer entfernt, Standort gesetzt
                                        @elseif($histEvent === $histClarMissing)
                                            Klärung: Als vermisst markiert
                                        @elseif($histEvent === $histReturnInitiated)
                                            Rückgabe eingeleitet
                                        @elseif($histEvent === $histReturnCompleted)
                                            Rückgabe abgeschlossen (Admin)
                                        @elseif($histEvent === $histHandoverConfirmCleared)
                                            Übergabe bestätigt: Status bereinigt
                                        @else
                                            Verlaufseintrag
                                        @endif
                                        @if($historyEntry->user)
                                            <flux:text inline> von {{ $historyEntry->user->name }}</flux:text>
                                        @endif
                                        <flux:text inline class="text-zinc-400"> · {{ $historyEntry->created_at->format('d.m.Y H:i') }}</flux:text>
                                    </flux:heading>
                                    @if($histEvent === $histDeleted && filled($historyEntry->reason))
                                        <flux:text class="mt-1">{{ $historyEntry->reason }}</flux:text>
                                    @elseif(in_array($histEvent, [$histMailSent, $histMailFailed, $histItexiaMissing, $histSeventhingsOff], true) && filled($historyEntry->reason))
                                        <flux:text class="mt-1">{{ $historyEntry->reason }}</flux:text>
                                    @elseif(in_array($histEvent, [$histHandoverRejected, $histRejectionAck, $histRejectionNewOwner, $histRejectionLocation, $histRejectionMissing], true) && filled($historyEntry->reason))
                                        <flux:text class="mt-1">{{ $historyEntry->reason }}</flux:text>
                                    @elseif(in_array($histEvent, [$histOwnerClarification, $histClarClear, $histClarNewOwner, $histClarLocation, $histClarMissing], true) && filled($historyEntry->reason))
                                        <flux:text class="mt-1">{{ $historyEntry->reason }}</flux:text>
                                    @elseif(in_array($histEvent, [$histReturnInitiated, $histReturnCompleted], true) && filled($historyEntry->reason))
                                        <flux:text class="mt-1">{{ $historyEntry->reason }}</flux:text>
                                    @elseif($histEvent === $histHandoverConfirmCleared && filled($historyEntry->reason))
                                        <flux:text class="mt-1">{{ $historyEntry->reason }}</flux:text>
                                    @endif
                                    @if(filled($historyEntry->meta))
                                        <flux:text class="mt-1 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ json_encode($historyEntry->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</flux:text>
                                    @endif
                                </flux:timeline.content>
                            </flux:timeline.item>

                        @elseif($entry['type'] === 'handover')
                            @php $handover = $entry['model']; @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator color="green">
                                    <flux:icon.arrow-right-circle variant="micro" />
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading>
                                        <a href="{{ route('apps.assets.handover.show', $handover) }}" class="hover:underline">
                                            Übergabe an
                                            <flux:text inline class="font-semibold">{{ $handover->recipient?->name ?? 'Unbekannt' }}</flux:text>
                                            @if($handover->issuer)
                                                <flux:text inline> durch {{ $handover->issuer->name }}</flux:text>
                                            @endif
                                            <flux:text inline class="text-zinc-400"> · {{ $handover->created_at->format('d.m.Y H:i') }}</flux:text>
                                        </a>
                                    </flux:heading>
                                    @if($handover->signature)
                                        <flux:badge size="sm" color="green" icon="check">Unterschrift vorhanden</flux:badge>
                                    @endif
                                </flux:timeline.content>
                            </flux:timeline.item>

                        @elseif($entry['type'] === 'return')
                            @php $return = $entry['model']; @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator color="blue">
                                    <flux:icon.arrow-left-circle variant="micro" />
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading>
                                        Rückgabe
                                        @if($return->recipient)
                                            <flux:text inline> entgegengenommen von {{ $return->recipient->name }}</flux:text>
                                        @endif
                                        <flux:text inline class="text-zinc-400"> · {{ $return->created_at->format('d.m.Y H:i') }}</flux:text>
                                    </flux:heading>
                                </flux:timeline.content>
                            </flux:timeline.item>

                        @elseif($entry['type'] === 'note')
                            @php $note = $entry['model']; @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator color="zinc">
                                    <flux:icon.chat-bubble-left variant="micro" />
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading>
                                        Notiz
                                        @if($note->author)
                                            <flux:text inline> von {{ $note->author->name }}</flux:text>
                                        @endif
                                        <flux:text inline class="text-zinc-400"> · {{ $note->created_at->format('d.m.Y H:i') }}</flux:text>
                                    </flux:heading>
                                    <flux:text class="mt-1">{!! nl2br(e($note->note)) !!}</flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>

                        @elseif($entry['type'] === 'attachment')
                            @php $attachment = $entry['model']; @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator color="amber">
                                    <flux:icon.paper-clip variant="micro" />
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading>
                                        Anhang
                                        @if($attachment->uploader)
                                            <flux:text inline> von {{ $attachment->uploader->name }}</flux:text>
                                        @endif
                                        <flux:text inline class="text-zinc-400"> · {{ $attachment->created_at->format('d.m.Y H:i') }}</flux:text>
                                    </flux:heading>
                                    <flux:text class="font-mono text-sm">{{ basename($attachment->file) }}</flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endif
                    @endforeach
                </flux:timeline>
            @endif
        </div>

        {{-- D3 Rechnungssuche Modal --}}
        <flux:modal wire:model="showD3InvoiceModal" class="md:max-w-2xl" variant="flyout">
            <div class="space-y-4">
                <flux:heading size="lg">Rechnung in D3 suchen</flux:heading>
                <p class="text-sm text-zinc-500">Suchbegriff (z. B. BEN, Seriennummer, IMEI) eingeben oder die vorgeschlagene Suche nutzen. Treffer in D3 prüfen und bei Bedarf als Rechnungsnummer übernehmen.</p>
                @if($showD3InvoiceModal)
                    <livewire:intranet-app-assets::apps.assets.d3-invoice-search
                        :asset-id="$asset->id"
                        lazy
                        wire:key="d3-invoice-{{ $asset->id }}"
                    />
                @endif
                <div class="flex justify-end pt-2">
                    <flux:button wire:click="refreshAssetAndCloseD3InvoiceModal" variant="ghost">Schließen</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</x-intranet-app-assets::assets-layout>
</div>