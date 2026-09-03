<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
            'superseded_at' => 'datetime',
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

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    /**
     * @param  Builder<Handover>  $query
     * @return Builder<Handover>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /**
     * @param  Builder<Handover>  $query
     * @return Builder<Handover>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at');
    }

    /**
     * @param  Builder<Handover>  $query
     * @return Builder<Handover>
     */
    public function scopeRejectedPendingAdmin(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereNotNull('rejected_at');
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

    /** @return BelongsTo<User, $this> */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'superseded_by_user_id');
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
        return $this->hasCompletedReturn();
    }

    public function hasPendingReturn(): bool
    {
        if ($this->relationLoaded('assetReturns')) {
            return $this->assetReturns->contains(
                static fn (AssetReturn $return): bool => $return->completed_at === null,
            );
        }

        return $this->assetReturns()->whereNull('completed_at')->exists();
    }

    public function hasCompletedReturn(): bool
    {
        return $this->completedAssetReturn() !== null;
    }

    public function completedAssetReturn(): ?AssetReturn
    {
        if ($this->relationLoaded('assetReturns')) {
            return $this->assetReturns
                ->filter(static fn (AssetReturn $return): bool => $return->completed_at !== null)
                ->sortByDesc(static fn (AssetReturn $return) => $return->completed_at)
                ->first();
        }

        return $this->assetReturns()
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();
    }

    /**
     * @return array{label: string, color: string, hint: ?string}
     */
    public function displayStatus(): array
    {
        if ($this->isSuperseded()) {
            return [
                'label' => 'Ersetzt',
                'color' => 'zinc',
                'hint' => filled($this->superseded_reason) ? $this->superseded_reason : null,
            ];
        }

        if ($this->isRejected()) {
            return [
                'label' => 'Abgelehnt',
                'color' => 'red',
                'hint' => $this->rejected_at?->format('d.m.Y H:i'),
            ];
        }

        if ($this->hasCompletedReturn()) {
            $completedAt = $this->completedAssetReturn()?->completed_at;

            return [
                'label' => 'Zurückgegeben',
                'color' => 'blue',
                'hint' => $completedAt?->format('d.m.Y H:i'),
            ];
        }

        if ($this->hasPendingReturn()) {
            $pendingReturn = $this->relationLoaded('assetReturns')
                ? $this->assetReturns->first(fn (AssetReturn $return): bool => $return->completed_at === null)
                : $this->assetReturns()->whereNull('completed_at')->first();

            if ($pendingReturn?->isScheduled()) {
                if ($pendingReturn->isOverdue()) {
                    return [
                        'label' => 'Rückgabe überfällig',
                        'color' => 'red',
                        'hint' => $pendingReturn->scheduled_at?->format('d.m.Y H:i'),
                    ];
                }

                return [
                    'label' => 'Rückgabe geplant',
                    'color' => 'blue',
                    'hint' => $pendingReturn->scheduled_at?->format('d.m.Y H:i'),
                ];
            }

            return [
                'label' => 'Rückgabe offen',
                'color' => 'amber',
                'hint' => null,
            ];
        }

        if ($this->isConfirmed()) {
            return [
                'label' => 'Bestätigt',
                'color' => 'green',
                'hint' => $this->confirmed_at?->format('d.m.Y H:i'),
            ];
        }

        return [
            'label' => 'Offen',
            'color' => 'amber',
            'hint' => null,
        ];
    }
}
