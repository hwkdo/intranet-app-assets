<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AssetReturn extends Model
{
    protected $table = 'intranet_app_assets_returns';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'schedule_type' => ReturnScheduleType::class,
            'scheduled_at' => 'datetime',
            'reminder1_sent_at' => 'datetime',
            'reminder2_sent_at' => 'datetime',
            'last_overdue_reminder_sent_at' => 'datetime',
            'received_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isScheduled(): bool
    {
        return $this->schedule_type === ReturnScheduleType::Scheduled
            && $this->scheduled_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->isScheduled()
            && ! $this->isCompleted()
            && $this->scheduled_at?->isPast();
    }

    /**
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query
            ->where('schedule_type', ReturnScheduleType::Scheduled)
            ->whereNotNull('scheduled_at');
    }

    /**
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public function scopeImmediate(Builder $query): Builder
    {
        return $query->where('schedule_type', ReturnScheduleType::Immediate);
    }

    /**
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public function scopeFutureScheduled(Builder $query): Builder
    {
        return $query
            ->scheduled()
            ->open()
            ->where('scheduled_at', '>', now());
    }

    /**
     * Offene Rückgaben für Admin-Tasks: Sofort + fällig/überfällig geplant (nicht zukünftig geplant).
     *
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public function scopeAdminOpenTask(Builder $query): Builder
    {
        return $query
            ->open()
            ->where(function (Builder $query): void {
                $query->where('schedule_type', ReturnScheduleType::Immediate)
                    ->orWhereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    /**
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public function scopeOverdueScheduled(Builder $query): Builder
    {
        return $query
            ->scheduled()
            ->open()
            ->where('scheduled_at', '<=', now());
    }

    /** @return BelongsTo<Handover, $this> */
    public function handover(): BelongsTo
    {
        return $this->belongsTo(Handover::class, 'handover_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** User who initiated the return (current holder). */
    /** @return BelongsTo<User, $this> */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /** @return MorphMany<AssetNote, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(AssetNote::class, 'noteable');
    }

    public function currentOwner(): ?User
    {
        return $this->handover?->asset?->owner;
    }
}
