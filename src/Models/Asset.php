<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Services\AssetPermanentDeletionArchiveRecorder;
use Hwkdo\IntranetAppAssets\Support\AssetStockState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Asset extends Model implements HasMedia
{
    use InteractsWithMedia;
    use Searchable;
    use SoftDeletes;

    protected $table = 'intranet_app_assets_assets';

    protected $guarded = [];

    public function searchableAs(): string
    {
        return 'intranet_app_assets_assets';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['owner', 'type', 'vendor']);

        return [
            'id' => (string) $this->id,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : 0,
            'name' => $this->normalizedString($this->name),
            'model' => $this->normalizedString($this->model),
            'location' => $this->normalizedString($this->location),
            'owner_name' => $this->normalizedString($this->owner?->name),
            'owner_vorname' => $this->normalizedString($this->owner?->vorname),
            'owner_nachname' => $this->normalizedString($this->owner?->nachname),
            'type_name' => $this->normalizedString($this->type?->name),
            'vendor_name' => $this->normalizedString($this->vendor?->name),
            'serial_number' => $this->normalizedString($this->serial_number),
            'imei' => $this->normalizedString($this->imei),
            'itexia_id' => $this->normalizedString($this->itexia_id),
            'itexia_uuid' => $this->normalizedString($this->itexia_uuid),
            'itexia_actual_room_id' => $this->itexia_actual_room_id !== null ? (string) $this->itexia_actual_room_id : null,
            'itexia_target_room_id' => $this->itexia_target_room_id !== null ? (string) $this->itexia_target_room_id : null,
            'intune_device_id' => $this->normalizedString($this->intune_device_id),
            'configmgr_serial_number' => $this->normalizedString($this->configmgr_serial_number),
            'smbios_guid' => $this->normalizedString($this->smbios_guid),
            'order_number' => $this->normalizedString($this->order_number),
            'invoice_number' => $this->normalizedString($this->invoice_number),
            'domain_connection' => $this->normalizedString($this->domain_connection),
            'configmgr_last_logon_user' => $this->normalizedString($this->configmgr_last_logon_user),
            'status_tokens' => $this->statusTokens(),
            'history_text' => $this->historyText(),
            'notes_text' => $this->notesText(),
            'created_at' => $this->created_at?->timestamp ?? now()->timestamp,
        ];
    }

    protected function casts(): array
    {
        return [
            'invoice_number_pending' => 'boolean',
            'is_clarification' => 'boolean',
            'is_missing' => 'boolean',
            'is_in_stock' => 'boolean',
            'domain_last_seen' => 'datetime',
            'domain_last_checked' => 'datetime',
            'last_logon' => 'datetime',
            'last_logon_timestamp' => 'datetime',
            'itexia_check_at' => 'datetime',
            'itexia_rooms_synced_at' => 'datetime',
            'intune_last_check_in' => 'datetime',
            'configmgr_mac_addresses' => 'array',
        ];
    }

    /** @return BelongsTo<AssetType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    /** @return BelongsTo<AssetVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(AssetVendor::class, 'asset_vendor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function scopeInvoiceNumberPending($query)
    {
        return $query->where('invoice_number_pending', true)
            ->where(function ($q) {
                $q->whereNull('invoice_number')->orWhere('invoice_number', '');
            });
    }

    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function scopeInStock($query)
    {
        return $query->where('is_in_stock', true);
    }

    /**
     * Volltextsuche für die „Alle Assets“-Liste (alle sichtbaren Tabellenspalten).
     *
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function scopeMatchingListeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.trim($search).'%';

        if ($term === '%%') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('serial_number', 'like', $term)
                ->orWhere('model', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('itexia_id', 'like', $term)
                ->orWhere('location', 'like', $term)
                ->orWhereHas('type', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', $term))
                ->orWhereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('name', 'like', $term))
                ->orWhereHas('owner', function (Builder $ownerQuery) use ($term): void {
                    $ownerQuery->where('vorname', 'like', $term)
                        ->orWhere('nachname', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('raum', 'like', $term)
                        ->orWhereHas('standort', fn (Builder $standortQuery) => $standortQuery->where('name', 'like', $term));
                });
        });
    }

    /**
     * Assets mit BEN, aber ohne Rechnungsnummer (Kandidaten für automatische D3-Suche).
     *
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function scopeEligibleForInvoiceAutoResolve($query)
    {
        return $query
            ->whereNotNull('order_number')
            ->where('order_number', '!=', '')
            ->where(function ($q) {
                $q->whereNull('invoice_number')->orWhere('invoice_number', '');
            });
    }

    /** @return MorphMany<AssetNote, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(AssetNote::class, 'noteable');
    }

    /** @return HasMany<AssetAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class, 'asset_id');
    }

    /** @return HasMany<Handover, $this> */
    public function handovers(): HasMany
    {
        return $this->hasMany(Handover::class, 'asset_id');
    }

    /** @return HasMany<AssetHistory, $this> */
    public function historyEntries(): HasMany
    {
        return $this->hasMany(AssetHistory::class, 'asset_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: "{$this->vendor?->name} {$this->model}";
    }

    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            AssetStockState::enforceInvariants($asset);
        });

        static::forceDeleting(function (self $asset): void {
            app(AssetPermanentDeletionArchiveRecorder::class)->record($asset);
        });

        static::deleting(function (self $asset): void {
            if (! $asset->isForceDeleting()) {
                return;
            }

            $asset->notes()->delete();

            $asset->attachments()->each(function (AssetAttachment $attachment): void {
                $attachment->notes()->delete();
                $attachment->delete();
            });

            $asset->handovers()->each(function (Handover $handover): void {
                $handover->notes()->delete();

                $return = $handover->assetReturn;
                if ($return !== null) {
                    $return->notes()->delete();
                    $return->delete();
                }

                $handover->delete();
            });
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    private function statusTokens(): string
    {
        $tokens = [];

        if ($this->is_missing) {
            $tokens[] = 'missing';
        }

        if ($this->is_clarification) {
            $tokens[] = 'clarification';
        }

        if ($this->is_in_stock) {
            $tokens[] = 'in_stock';
        }

        if ($this->invoice_number_pending) {
            $tokens[] = 'invoice_pending';
        }

        return implode(' ', $tokens);
    }

    private function historyText(): string
    {
        $historyEntries = $this->relationLoaded('historyEntries')
            ? $this->historyEntries
            : $this->historyEntries()->latest('id')->limit(50)->get(['event', 'reason', 'meta']);

        $parts = $historyEntries
            ->map(function (AssetHistory $entry): string {
                $meta = $entry->meta;
                $metaText = is_array($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

                return trim(implode(' ', array_filter([
                    $this->normalizedString($entry->event),
                    $this->normalizedString($entry->reason),
                    $this->normalizedString($metaText),
                ])));
            })
            ->filter()
            ->values();

        return $this->normalizedString($parts->implode(' '));
    }

    private function notesText(): string
    {
        $parts = $this->collectRelatedNoteTexts()
            ->map(fn (mixed $text): string => $this->normalizedString($text))
            ->filter()
            ->values();

        return $parts->implode(' ');
    }

    /**
     * @return Collection<int, string>
     */
    private function collectRelatedNoteTexts(): Collection
    {
        if ($this->relationLoaded('notes') || $this->relationLoaded('handovers') || $this->relationLoaded('attachments')) {
            $collected = collect();

            if ($this->relationLoaded('notes')) {
                $collected = $collected->merge($this->notes->pluck('note'));
            }

            if ($this->relationLoaded('handovers')) {
                $collected = $collected->merge(
                    $this->handovers
                        ->flatMap(function (Handover $handover): Collection {
                            $notes = $handover->relationLoaded('notes') ? $handover->notes->pluck('note') : collect();
                            $returnNotes = collect();

                            if ($handover->relationLoaded('assetReturn') && $handover->assetReturn !== null && $handover->assetReturn->relationLoaded('notes')) {
                                $returnNotes = $handover->assetReturn->notes->pluck('note');
                            }

                            return $notes->merge($returnNotes);
                        })
                );
            }

            if ($this->relationLoaded('attachments')) {
                $collected = $collected->merge(
                    $this->attachments
                        ->flatMap(fn (AssetAttachment $attachment): Collection => $attachment->relationLoaded('notes') ? $attachment->notes->pluck('note') : collect())
                );
            }

            return $collected->map(fn (mixed $value): string => (string) $value)->values();
        }

        $assetNotes = AssetNote::query()
            ->where('noteable_type', self::class)
            ->where('noteable_id', $this->id)
            ->pluck('note');

        $handoverIds = Handover::query()
            ->where('asset_id', $this->id)
            ->pluck('id');

        $handoverNotes = AssetNote::query()
            ->where('noteable_type', Handover::class)
            ->whereIn('noteable_id', $handoverIds)
            ->pluck('note');

        $assetReturnIds = AssetReturn::query()
            ->whereIn('handover_id', $handoverIds)
            ->pluck('id');

        $assetReturnNotes = AssetNote::query()
            ->where('noteable_type', AssetReturn::class)
            ->whereIn('noteable_id', $assetReturnIds)
            ->pluck('note');

        $attachmentIds = AssetAttachment::query()
            ->where('asset_id', $this->id)
            ->pluck('id');

        $attachmentNotes = AssetNote::query()
            ->where('noteable_type', AssetAttachment::class)
            ->whereIn('noteable_id', $attachmentIds)
            ->pluck('note');

        return $assetNotes
            ->merge($handoverNotes)
            ->merge($assetReturnNotes)
            ->merge($attachmentNotes)
            ->map(fn (mixed $value): string => (string) $value)
            ->values();
    }

    private function normalizedString(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }
}
