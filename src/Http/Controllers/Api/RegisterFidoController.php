<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Http\Controllers\Api;

use Hwkdo\IntranetAppAssets\Http\Requests\RegisterFidoRequest;
use Hwkdo\IntranetAppAssets\Services\RegisterFidoAssetService;
use Illuminate\Http\JsonResponse;

class RegisterFidoController
{
    public function __invoke(RegisterFidoRequest $request, RegisterFidoAssetService $service): JsonResponse
    {
        $asset = $service->register(
            $request->string('username')->toString(),
            $request->serialNumber(),
            $request->pinForNote(),
        );

        return response()->json([
            'status' => 'success',
            'message' => 'FIDO device registered successfully.',
            'asset_id' => $asset->id,
        ]);
    }
}
