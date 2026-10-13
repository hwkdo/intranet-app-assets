<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use App\Models\Standort;
use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Illuminate\Database\Eloquent\Builder;

final class AssetTableSort
{
    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public static function apply(Builder $query, string $column, string $direction): Builder
    {
        $direction = self::direction($direction);
        $asset = (new Asset)->getTable();

        $expression = self::assetExpression($column, $asset);
        if ($expression === null) {
            $query->orderBy($asset.'.id', $direction);

            return $query;
        }

        if (self::isPlainColumn($column)) {
            $query->orderBy($asset.'.'.$column, $direction);
        } else {
            $query->orderByRaw($expression.' '.$direction);
        }

        return $query->orderBy($asset.'.id', $direction);
    }

    /**
     * @param  Builder<Handover>  $query
     * @return Builder<Handover>
     */
    public static function applyHandover(Builder $query, string $column, string $direction): Builder
    {
        $direction = self::direction($direction);
        $handover = (new Handover)->getTable();
        $asset = (new Asset)->getTable();

        $expression = match ($column) {
            'created_at', 'rejected_at' => null,
            'asset' => self::assetDisplayNameSql($asset, $asset.'.id', $handover.'.asset_id'),
            'recipient' => self::userNameSql($handover.'.recipient_user_id'),
            'issuer' => self::userNameSql($handover.'.issuer_user_id'),
            default => null,
        };

        if (in_array($column, ['created_at', 'rejected_at'], true)) {
            $query->orderBy($handover.'.'.$column, $direction);
        } elseif (is_string($expression)) {
            $query->orderByRaw($expression.' '.$direction);
        }

        return $query->orderBy($handover.'.id', $direction);
    }

    /**
     * @param  Builder<AssetReturn>  $query
     * @return Builder<AssetReturn>
     */
    public static function applyReturn(Builder $query, string $column, string $direction): Builder
    {
        $direction = self::direction($direction);
        $return = (new AssetReturn)->getTable();
        $handover = (new Handover)->getTable();
        $asset = (new Asset)->getTable();

        if ($column === 'scheduled_at' || $column === 'created_at') {
            $query->orderBy($return.'.'.$column, $direction);
        } elseif ($column === 'asset') {
            $query->orderByRaw(self::assetDisplayNameSql(
                $asset,
                $asset.'.id',
                '(SELECT '.$handover.'.asset_id FROM '.$handover.' WHERE '.$handover.'.id = '.$return.'.handover_id)',
            ).' '.$direction);
        } elseif ($column === 'returned_by') {
            $query->orderByRaw('CASE WHEN '.$return.'.initiated_by_user_id IS NOT NULL THEN '.self::userNameSql($return.'.initiated_by_user_id').' ELSE '.self::userNameSql('(SELECT '.$handover.'.recipient_user_id FROM '.$handover.' WHERE '.$handover.'.id = '.$return.'.handover_id)').' END '.$direction);
        }

        return $query->orderBy($return.'.id', $direction);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function applyYubikeyUsers(Builder $query, string $column, string $direction, ?int $typeId): Builder
    {
        $direction = self::direction($direction);
        $users = (new User)->getTable();
        $asset = (new Asset)->getTable();

        $query->reorder();

        if ($column === 'owner') {
            return $query
                ->orderBy($users.'.nachname', $direction)
                ->orderBy($users.'.vorname', $direction)
                ->orderBy($users.'.id', $direction);
        }

        if ($typeId === null) {
            return $query->orderBy($users.'.id', $direction);
        }

        $assetFilter = $asset.'.user_id = '.$users.'.id AND '.$asset.'.asset_type_id = '.(int) $typeId.' AND '.$asset.'.deleted_at IS NULL';

        $expression = match ($column) {
            'display_name' => '(SELECT '.self::displayNameExpression($asset).' FROM '.$asset.' WHERE '.$assetFilter.' ORDER BY '.$asset.'.id LIMIT 1)',
            'serial_number' => '(SELECT '.$asset.'.serial_number FROM '.$asset.' WHERE '.$assetFilter.' ORDER BY '.$asset.'.id LIMIT 1)',
            'type' => '(SELECT '.(new AssetType)->getTable().'.name FROM '.$asset.' INNER JOIN '.(new AssetType)->getTable().' ON '.(new AssetType)->getTable().'.id = '.$asset.'.asset_type_id WHERE '.$assetFilter.' ORDER BY '.$asset.'.id LIMIT 1)',
            'vendor' => '(SELECT '.(new AssetVendor)->getTable().'.name FROM '.$asset.' INNER JOIN '.(new AssetVendor)->getTable().' ON '.(new AssetVendor)->getTable().'.id = '.$asset.'.asset_vendor_id WHERE '.$assetFilter.' ORDER BY '.$asset.'.id LIMIT 1)',
            'status' => '(SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END FROM '.$asset.' WHERE '.$assetFilter.')',
            default => null,
        };

        if (! is_string($expression)) {
            return $query->orderBy($users.'.id', $direction);
        }

        return $query
            ->orderByRaw($expression.' '.$direction)
            ->orderBy($users.'.id', $direction);
    }

    public static function direction(string $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    private static function isPlainColumn(string $column): bool
    {
        return in_array($column, [
            'model',
            'serial_number',
            'imei',
            'itexia_id',
            'itexia_uuid',
            'invoice_number',
            'order_number',
            'domain_connection',
            'domain_last_seen',
            'last_logon_timestamp',
            'created_at',
            'updated_at',
            'deleted_at',
        ], true);
    }

    private static function assetExpression(string $column, string $asset): ?string
    {
        if (self::isPlainColumn($column)) {
            return $column;
        }

        return match ($column) {
            'display_name' => self::displayNameExpression($asset),
            'type' => '(SELECT name FROM '.(new AssetType)->getTable().' WHERE id = '.$asset.'.asset_type_id)',
            'vendor' => '(SELECT name FROM '.(new AssetVendor)->getTable().' WHERE id = '.$asset.'.asset_vendor_id)',
            'owner' => self::userNameSql($asset.'.user_id'),
            'created_by' => self::userNameSql($asset.'.created_by_user_id'),
            'location' => self::locationSql($asset),
            'status' => 'CASE WHEN '.$asset.'.is_missing = 1 THEN 1 WHEN '.$asset.'.is_clarification = 1 THEN 2 WHEN '.$asset.'.is_in_stock = 1 THEN 3 WHEN '.$asset.'.user_id IS NULL THEN 4 ELSE 5 END',
            'deleted_by' => self::latestHistoryUserSql($asset),
            'deleted_reason' => self::latestHistoryValueSql($asset, 'reason'),
            default => null,
        };
    }

    private static function displayNameExpression(string $asset): string
    {
        $vendor = '(SELECT name FROM '.(new AssetVendor)->getTable().' WHERE id = '.$asset.'.asset_vendor_id)';

        return 'CASE WHEN NULLIF(TRIM('.$asset.'.name), \'\') IS NOT NULL THEN '.$asset.'.name ELSE TRIM('.self::concat('COALESCE('.$vendor.', \'\')', "' '", 'COALESCE('.$asset.'.model, \'\')').') END';
    }

    /**
     * @param  non-empty-string  $asset
     */
    private static function assetDisplayNameSql(string $asset, string $assetIdColumn, string $matchingIdSql): string
    {
        return '(SELECT '.self::displayNameExpression($asset).' FROM '.$asset.' WHERE '.$assetIdColumn.' = '.$matchingIdSql.')';
    }

    private static function userNameSql(string $userIdSql): string
    {
        $users = (new User)->getTable();

        return '(SELECT TRIM('.self::concat('COALESCE('.$users.'.vorname, \'\')', "' '", 'COALESCE('.$users.'.nachname, \'\')').') FROM '.$users.' WHERE '.$users.'.id = '.$userIdSql.')';
    }

    private static function locationSql(string $asset): string
    {
        $users = (new User)->getTable();
        $standorte = (new Standort)->getTable();

        return 'CASE WHEN '.$asset.'.user_id IS NULL THEN COALESCE('.$asset.'.location, \'\') ELSE COALESCE(NULLIF(TRIM((SELECT '.$users.'.raum FROM '.$users.' WHERE '.$users.'.id = '.$asset.'.user_id)), \'\'), NULLIF(TRIM((SELECT '.$standorte.'.name FROM '.$standorte.' INNER JOIN '.$users.' ON '.$users.'.standort_id = '.$standorte.'.id WHERE '.$users.'.id = '.$asset.'.user_id)), \'\'), \'\') END';
    }

    private static function latestHistoryUserSql(string $asset): string
    {
        $history = (new AssetHistory)->getTable();
        $users = (new User)->getTable();

        return '(SELECT TRIM('.self::concat('COALESCE('.$users.'.vorname, \'\')', "' '", 'COALESCE('.$users.'.nachname, \'\')').') FROM '.$history.' INNER JOIN '.$users.' ON '.$users.'.id = '.$history.'.user_id WHERE '.$history.'.asset_id = '.$asset.'.id AND '.$history.'.event = \''.AssetHistory::EventDeleted.'\' ORDER BY '.$history.'.created_at DESC LIMIT 1)';
    }

    private static function latestHistoryValueSql(string $asset, string $historyColumn): string
    {
        $history = (new AssetHistory)->getTable();

        return '(SELECT '.$history.'.'.$historyColumn.' FROM '.$history.' WHERE '.$history.'.asset_id = '.$asset.'.id AND '.$history.'.event = \''.AssetHistory::EventDeleted.'\' ORDER BY '.$history.'.created_at DESC LIMIT 1)';
    }

    private static function concat(string ...$parts): string
    {
        if ((new Asset)->getConnection()->getDriverName() === 'sqlite') {
            return implode(' || ', $parts);
        }

        return 'CONCAT('.implode(', ', $parts).')';
    }
}
