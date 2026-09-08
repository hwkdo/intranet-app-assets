<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledOwnerAssignment extends Model
{
    protected $table = 'intranet_app_assets_scheduled_owner_assignments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'execute_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * @param  Builder<ScheduledOwnerAssignment>  $query
     * @return Builder<ScheduledOwnerAssignment>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    /**
     * @param  Builder<ScheduledOwnerAssignment>  $query
     * @return Builder<ScheduledOwnerAssignment>
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->pending()
            ->where('execute_at', '<=', now());
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function newUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }
}
