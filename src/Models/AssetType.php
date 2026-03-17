<?php

namespace Hwkdo\IntranetAppAssets\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends Model
{
    protected $table = 'intranet_app_assets_asset_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_domain_object' => 'boolean',
            'is_intune_object' => 'boolean',
            'itexia_creation_allowed' => 'boolean',
        ];
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_type_id');
    }

    public static function allOrdered(): Collection
    {
        return self::orderBy('name')->get();
    }
}
