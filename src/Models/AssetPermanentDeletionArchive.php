<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPermanentDeletionArchive extends Model
{
    protected $table = 'intranet_app_assets_permanent_deletion_archives';

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function displayNameFromPayload(): string
    {
        $payload = $this->payload ?? [];

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $vendor = trim((string) ($payload['asset_vendor_name'] ?? ''));
        $model = trim((string) ($payload['model'] ?? ''));

        return trim(implode(' ', array_filter([$vendor, $model]))) ?: '—';
    }
}
