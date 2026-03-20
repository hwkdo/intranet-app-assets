<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Asset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'intranet_app_assets_assets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invoice_number_pending' => 'boolean',
            'is_clarification' => 'boolean',
            'is_missing' => 'boolean',
            'domain_last_seen' => 'datetime',
            'domain_last_checked' => 'datetime',
            'last_logon' => 'datetime',
            'last_logon_timestamp' => 'datetime',
            'itexia_check_at' => 'datetime',
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
     * @param  \Illuminate\Database\Eloquent\Builder<Asset>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Asset>
     */
    public function scopeInvoiceNumberPending($query)
    {
        return $query->where('invoice_number_pending', true)
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('thumbnail')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Stellt sicher, dass bei gesetztem Besitzer (user_id) ein Handover existiert.
     * Jede Besitzzuordnung soll einen Handover haben (unbestätigt, bis der Empfänger bestätigt).
     */
    public function ensureHandoverForOwner(): void
    {
        if ($this->user_id === null) {
            return;
        }

        $exists = Handover::query()
            ->where('asset_id', $this->id)
            ->where('recipient_user_id', $this->user_id)
            ->exists();

        if ($exists) {
            return;
        }

        Handover::create([
            'asset_id' => $this->id,
            'recipient_user_id' => $this->user_id,
            'issuer_user_id' => auth()->id(),
            'confirmed_at' => null,
            'confirmation_method' => null,
        ]);
    }
}
