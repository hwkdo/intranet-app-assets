<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Asset Details')] class extends Component
{
    public Asset $asset;

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

    public function delete(): void
    {
        if ($this->asset->itexia_id) {
            abort(403, 'Assets mit Itexia-ID müssen über die gesicherte Löschseite entfernt werden.');
        }

        $name = $this->asset->display_name;
        $this->asset->historyEntries()->create([
            'event' => \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventDeleted,
            'user_id' => auth()->id(),
        ]);
        $this->asset->delete();

        session()->flash('success', "Asset \"{$name}\" wurde gelöscht.");
        $this->redirect(route('apps.assets.liste'), navigate: true);
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
}; ?>
<div>
<x-intranet-app-assets::assets-layout
    heading="{{ $asset->display_name }}"
    subheading="{{ $asset->type?->name }} · {{ $asset->vendor?->name }}"
>
    <div class="space-y-8">

        {{-- Aktionsleiste --}}
        <div class="flex items-center gap-3">
            @can('manage-app-assets')
                <flux:button href="{{ route('apps.assets.edit', $asset) }}" variant="primary" icon="pencil" size="sm">
                    Bearbeiten
                </flux:button>

                @if($asset->itexia_id)
                    <flux:button href="{{ route('apps.assets.delete', $asset) }}" variant="danger" icon="trash" size="sm">
                        Löschen
                    </flux:button>
                @else
                    <flux:button
                        wire:click="delete"
                        wire:confirm="Asset '{{ $asset->display_name }}' wirklich löschen?"
                        variant="danger"
                        icon="trash"
                        size="sm"
                    >
                        Löschen
                    </flux:button>
                @endif
            @endcan

            <flux:button href="{{ route('apps.assets.liste') }}" variant="ghost" icon="arrow-left" size="sm">
                Zurück zur Liste
            </flux:button>
        </div>

        {{-- Asset-Stammdaten --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <flux:card class="overflow-hidden transition-colors hover:border-zinc-400/60 dark:hover:border-zinc-500/60">
                <div class="space-y-4">
                    <flux:heading size="lg">Stammdaten</flux:heading>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="font-semibold">Seriennummer</dt>
                    <dd class="font-mono">{{ $asset->serial_number }}</dd>

                    @if(filled($asset->imei))
                        <dt class="font-semibold">IMEI</dt>
                        <dd class="font-mono">{{ $asset->imei }}</dd>
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
                            <span class="font-mono">{{ $asset->itexia_id }}</span>                            
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="font-semibold">Bestellnummer</dt>
                    <dd>{{ $asset->order_number ?? '—' }}</dd>

                    <dt class="font-semibold">Rechnungsnummer</dt>
                    <dd class="flex flex-col gap-2">
                        @if(filled($asset->invoice_number))
                            <span class="font-mono">{{ $asset->invoice_number }}</span>
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
                            @php $historyEntry = $entry['model']; @endphp
                            <flux:timeline.item>
                                <flux:timeline.indicator color="{{ $historyEntry->event === \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventDeleted ? 'red' : ($historyEntry->event === \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventUpdated ? 'zinc' : 'green') }}">
                                    @if($historyEntry->event === \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventDeleted)
                                        <flux:icon.trash variant="micro" />
                                    @elseif($historyEntry->event === \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventUpdated)
                                        <flux:icon.wrench variant="micro" />
                                    @else
                                        <flux:icon.arrow-uturn-left variant="micro" />
                                    @endif
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading>
                                        @if($historyEntry->event === \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventDeleted)
                                            Gelöscht
                                        @elseif($historyEntry->event === \Hwkdo\IntranetAppAssets\Models\AssetHistory::EventUpdated)
                                            Asset aktualisiert
                                        @else
                                            Wiederhergestellt
                                        @endif
                                        @if($historyEntry->user)
                                            <flux:text inline> von {{ $historyEntry->user->name }}</flux:text>
                                        @endif
                                        <flux:text inline class="text-zinc-400"> · {{ $historyEntry->created_at->format('d.m.Y H:i') }}</flux:text>
                                    </flux:heading>
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
                @livewire('intranet-app-assets::apps.assets.d3-invoice-search', ['assetId' => $asset->id], key('d3-invoice-'.$asset->id))
                <div class="flex justify-end pt-2">
                    <flux:button wire:click="refreshAssetAndCloseD3InvoiceModal" variant="ghost">Schließen</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
</x-intranet-app-assets::assets-layout>
</div>