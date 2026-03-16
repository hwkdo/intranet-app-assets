<?php

namespace Hwkdo\IntranetAppAssets\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetVendor extends Model
{
    protected $table = 'intranet_app_assets_asset_vendors';

    protected $guarded = [];

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_vendor_id');
    }

    public static function allOrdered(): Collection
    {
        return self::orderBy('name')->get();
    }
}
