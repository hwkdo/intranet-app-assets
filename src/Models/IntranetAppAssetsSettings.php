<?php

namespace Hwkdo\IntranetAppAssets\Models;

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

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
}
