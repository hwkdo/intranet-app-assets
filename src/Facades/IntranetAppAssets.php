<?php

namespace Hwkdo\IntranetAppAssets\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppAssets\IntranetAppAssets
 */
class IntranetAppAssets extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppAssets\IntranetAppAssets::class;
    }
}
