<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Handover extends Model
{
    protected $table = 'intranet_app_assets_handovers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Handover>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Handover>
     */
    public function scopeRejectedPendingAdmin($query)
    {
        return $query->whereNotNull('rejected_at');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /** @return MorphMany<AssetNote, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(AssetNote::class, 'noteable');
    }

    /** @return HasOne<AssetReturn, $this> */
    public function assetReturn(): HasOne
    {
        return $this->hasOne(AssetReturn::class, 'handover_id');
    }

    /** @return HasMany<AssetReturn, $this> */
    public function assetReturns(): HasMany
    {
        return $this->hasMany(AssetReturn::class, 'handover_id');
    }

    /**
     * Open return workflow (awaiting admin receipt / disposition).
     *
     * @return HasOne<AssetReturn, $this>
     */
    public function pendingAssetReturn(): HasOne
    {
        return $this->hasOne(AssetReturn::class, 'handover_id')->whereNull('completed_at');
    }

    public function isReturned(): bool
    {
        return $this->assetReturns()->exists();
    }
}
