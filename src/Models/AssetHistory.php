<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetHistory extends Model
{
    public const EventDeleted = 'deleted';

    public const EventRestored = 'restored';

    public const EventUpdated = 'updated';

    public const EventItexiaInventoryMailSent = 'itexia_inventory_mail_sent';

    public const EventItexiaInventoryMailFailed = 'itexia_inventory_mail_failed';

    public const EventItexiaNotFoundOnDelete = 'itexia_not_found_on_delete';

    public const EventItexiaSeventhingsUnavailableOnDelete = 'itexia_seventhings_unavailable_on_delete';

    public const EventInvoiceAutoResolveExhausted = 'invoice_auto_resolve_exhausted';

    protected $table = 'intranet_app_assets_asset_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
