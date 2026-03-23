<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
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
            'received_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
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
}
