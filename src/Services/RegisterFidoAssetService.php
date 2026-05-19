<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use RuntimeException;

class RegisterFidoAssetService
{
    public const string ASSET_TYPE_NAME = 'Yubikey';

    public const string ASSET_VENDOR_NAME = 'Yubico';

    public const string DEFAULT_MODEL = 'Yubikey 5 NFC';

    public function register(string $username, string $serialNumber, string $pin): Asset
    {
        $user = User::query()->where('username', $username)->first();
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        $assetType = AssetType::query()->firstOrCreate(
            ['name' => self::ASSET_TYPE_NAME],
            ['is_domain_object' => false],
        );

        $assetVendor = AssetVendor::query()->firstOrCreate(
            ['name' => self::ASSET_VENDOR_NAME],
        );

        $asset = Asset::query()->create([
            'serial_number' => $serialNumber,
            'model' => self::DEFAULT_MODEL,
            'asset_type_id' => $assetType->id,
            'asset_vendor_id' => $assetVendor->id,
            'user_id' => $user->id,
            'location' => $user->raum,
        ]);

        $asset->notes()->create([
            'note' => 'FIDO device registered via API with PIN '.$pin,
            'user_id' => $user->id,
        ]);

        return $asset;
    }
}
