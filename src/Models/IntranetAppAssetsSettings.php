<?php

namespace Hwkdo\IntranetAppAssets\Models;

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class IntranetAppAssetsSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): ?IntranetAppAssetsSettings
    {
        return self::orderBy('version', 'desc')->first();
    }

    /**
     * App-Settings für D3-Vision u. a.: ohne Tabelle (z. B. frische Tests) → Defaults.
     */
    public static function resolvedAppSettings(): AppSettings
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return new AppSettings;
        }

        $row = static::current();

        return $row?->settings instanceof AppSettings ? $row->settings : new AppSettings;
    }
}
