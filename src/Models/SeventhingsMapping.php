<?php

namespace Hwkdo\IntranetAppAssets\Models;

use Illuminate\Database\Eloquent\Model;

class SeventhingsMapping extends Model
{
    protected $table = 'intranet_app_assets_seventhings_mappings';

    protected $fillable = [
        'local_attribute',
        'itexia_attribute',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
