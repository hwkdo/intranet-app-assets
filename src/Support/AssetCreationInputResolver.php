<?php

namespace Hwkdo\IntranetAppAssets\Support;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\AssetType;
use Hwkdo\IntranetAppAssets\Models\AssetVendor;
use Laravel\Mcp\Request;

class AssetCreationInputResolver
{
    public function resolveHerkunft(Request $request): string
    {
        $herkunft = $this->nullableTrimmedString($request->get('herkunft'));
        if ($herkunft !== null) {
            return $herkunft;
        }

        $prozessart = $this->nullableTrimmedString($request->get('prozessart'));
        if ($prozessart !== null) {
            return $prozessart;
        }

        return (string) ($this->nullableTrimmedString($request->get('variant')) ?? '');
    }

    public function resolveOrderNumber(Request $request): ?string
    {
        $orderNumber = $this->nullableTrimmedString($request->get('order_number'));
        if ($orderNumber !== null) {
            return $orderNumber;
        }

        foreach (['ben', 'bestellnummer', 'bestellnr'] as $alias) {
            $candidate = $this->nullableTrimmedString($request->get($alias));
            if ($candidate !== null) {
                return $candidate;
            }
        }

        $text = $this->combinedText($request);
        if ($text !== null && preg_match('/(?:bestell(?:nr|nummer)|ben)\s*[:#]?\s*([a-z0-9\-\/]+)/iu', $text, $matches) === 1) {
            return $this->nullableTrimmedString($matches[1] ?? null);
        }

        return null;
    }

    public function resolveOwnerInput(Request $request): ?string
    {
        $owner = $this->nullableTrimmedString($request->get('owner'));
        if ($owner !== null) {
            return $owner;
        }

        $owner = $this->nullableTrimmedString($request->get('besitzer'));
        if ($owner !== null) {
            return $owner;
        }

        $text = $this->combinedText($request);
        if ($text !== null && preg_match('/(?:besitzer|owner)\s*[:#]?\s*([a-z0-9._-]+)/iu', $text, $matches) === 1) {
            return $this->nullableTrimmedString($matches[1] ?? null);
        }

        return null;
    }

    public function resolveUserId(Request $request): ?int
    {
        if ($request->has('user_id') && $request->get('user_id') !== null) {
            return (int) $request->get('user_id');
        }

        $owner = $this->resolveOwnerInput($request);
        if ($owner === null) {
            return null;
        }

        $normalized = mb_strtolower($owner);
        $exact = User::query()
            ->whereRaw('LOWER(username) = ?', [$normalized])
            ->orWhereRaw('LOWER(nachname) = ?', [$normalized])
            ->orWhereRaw('LOWER(vorname) = ?', [$normalized])
            ->orWhereRaw("LOWER(CONCAT(vorname, ' ', nachname)) = ?", [$normalized])
            ->get();

        if ($exact->count() === 1) {
            return $exact->first()?->id;
        }

        if ($exact->count() > 1) {
            return null;
        }

        $partial = User::query()
            ->whereRaw('LOWER(username) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw('LOWER(nachname) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw('LOWER(vorname) LIKE ?', ['%'.$normalized.'%'])
            ->orWhereRaw("LOWER(CONCAT(vorname, ' ', nachname)) LIKE ?", ['%'.$normalized.'%'])
            ->get();

        if ($partial->count() === 1) {
            return $partial->first()?->id;
        }

        return null;
    }

    public function resolveAssetTypeId(Request $request): ?int
    {
        $assetTypeId = (int) $request->get('asset_type_id', 0);
        if ($assetTypeId > 0) {
            return $assetTypeId;
        }

        $typeName = $this->nullableTrimmedString($request->get('asset_type'))
            ?? $this->nullableTrimmedString($request->get('type'))
            ?? $this->nullableTrimmedString($request->get('typ'))
            ?? $this->nullableTrimmedString($request->get('kategorie'));

        if ($typeName === null) {
            $typeName = $this->inferAssetTypeNameFromText($this->combinedText($request));
        }

        if ($typeName === null) {
            return null;
        }

        $normalized = mb_strtolower($typeName);
        $exact = AssetType::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->get();

        if ($exact->count() === 1) {
            return $exact->first()?->id;
        }

        if ($exact->count() > 1) {
            return null;
        }

        $partial = AssetType::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])
            ->get();

        if ($partial->count() === 1) {
            return $partial->first()?->id;
        }

        return null;
    }

    public function resolveAssetVendorId(Request $request): ?int
    {
        $assetVendorId = (int) $request->get('asset_vendor_id', 0);
        if ($assetVendorId <= 0) {
            $assetVendorId = (int) $request->get('vendor_id', 0);
        }
        if ($assetVendorId > 0) {
            return $assetVendorId;
        }

        $vendorName = $this->nullableTrimmedString($request->get('vendor'))
            ?? $this->nullableTrimmedString($request->get('hersteller'))
            ?? $this->inferVendorFromText($this->nullableTrimmedString($request->get('model')));

        if ($vendorName === null) {
            return null;
        }

        $normalized = mb_strtolower($vendorName);
        $exact = AssetVendor::query()->whereRaw('LOWER(name) = ?', [$normalized])->get();
        if ($exact->count() === 1) {
            return $exact->first()?->id;
        }
        if ($exact->count() > 1) {
            return null;
        }

        $partial = AssetVendor::query()->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])->get();

        return $partial->count() === 1 ? $partial->first()?->id : null;
    }

    public function resolveSerialNumber(Request $request): ?string
    {
        $serialNumber = $this->nullableTrimmedString($request->get('serial_number'))
            ?? $this->nullableTrimmedString($request->get('seriennummer'));

        if ($serialNumber === null) {
            foreach (['serial', 'serialnr', 'seriennr', 'sn'] as $alias) {
                $candidate = $this->nullableTrimmedString($request->get($alias));
                if ($candidate !== null) {
                    $serialNumber = $candidate;
                    break;
                }
            }
        }

        if ($serialNumber !== null) {
            return $serialNumber;
        }

        $text = $this->combinedText($request);
        if ($text !== null && preg_match('/(?:seriennummer|seriennr|serial(?:_number)?|serialnr|sn)\s*[:#]?\s*([a-z0-9\-]+)/iu', $text, $matches) === 1) {
            return $this->nullableTrimmedString($matches[1] ?? null);
        }

        return null;
    }

    /**
     * @return array{id:?int,name:?string,status:string}
     */
    public function resolveVendorForPrecheck(?string $vendorInput, ?string $modelInput, ?string $textInput): array
    {
        $resolvedByName = $this->matchVendorByName($vendorInput);
        if ($resolvedByName !== null) {
            return ['id' => $resolvedByName->id, 'name' => $resolvedByName->name, 'status' => 'resolved_from_vendor_input'];
        }

        if ($modelInput !== null) {
            $fromModel = $this->resolveVendorFromText($modelInput, 'model');
            if ($fromModel['status'] !== 'unresolved') {
                return $fromModel;
            }
        }

        if ($textInput !== null) {
            $fromText = $this->resolveVendorFromText($textInput, 'text');
            if ($fromText['status'] !== 'unresolved') {
                return $fromText;
            }
        }

        return ['id' => null, 'name' => null, 'status' => 'no_input'];
    }

    /**
     * @return array{id:?int,name:?string,status:string}
     */
    private function resolveVendorFromText(string $text, string $source): array
    {
        $vendors = AssetVendor::query()->select(['id', 'name'])->get();
        $normalizedText = mb_strtolower($text);

        $containsMatches = $vendors->filter(function (AssetVendor $vendor) use ($normalizedText): bool {
            $vendorName = mb_strtolower((string) $vendor->name);

            return $vendorName !== '' && str_contains($normalizedText, $vendorName);
        })->values();

        if ($containsMatches->count() === 1) {
            $vendor = $containsMatches->first();

            return [
                'id' => $vendor?->id,
                'name' => $vendor?->name,
                'status' => $source === 'model' ? 'resolved_from_model_contains' : 'resolved_from_text_contains',
            ];
        }

        if ($containsMatches->count() > 1) {
            return [
                'id' => null,
                'name' => null,
                'status' => $source === 'model' ? 'ambiguous_from_model_contains' : 'ambiguous_from_text_contains',
            ];
        }

        $tokenVendorName = $this->inferVendorFromText($text);
        if ($tokenVendorName !== null) {
            $resolved = $this->matchVendorByName($tokenVendorName);
            if ($resolved !== null) {
                return [
                    'id' => $resolved->id,
                    'name' => $resolved->name,
                    'status' => $source === 'model' ? 'resolved_from_model_token' : 'resolved_from_text_token',
                ];
            }
        }

        return ['id' => null, 'name' => null, 'status' => 'unresolved'];
    }

    public function normalizeModel(?string $modelInput, ?string $textInput, ?string $resolvedVendorName): ?string
    {
        $raw = $modelInput ?? $textInput;
        if ($raw === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if ($normalized === '') {
            return null;
        }

        if ($resolvedVendorName !== null && $resolvedVendorName !== '') {
            $pattern = '/^'.preg_quote($resolvedVendorName, '/').'\s+/i';
            $stripped = preg_replace($pattern, '', $normalized);
            if (is_string($stripped) && trim($stripped) !== '') {
                return trim($stripped);
            }
        }

        return $normalized;
    }

    public function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function inferVendorFromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalizedText = mb_strtolower($text);
        $vendors = AssetVendor::query()->select(['name'])->get();

        $containsMatches = $vendors->filter(function (AssetVendor $vendor) use ($normalizedText): bool {
            $name = mb_strtolower((string) $vendor->name);

            return $name !== '' && str_contains($normalizedText, $name);
        })->values();

        if ($containsMatches->count() === 1) {
            return $containsMatches->first()?->name;
        }

        $tokens = preg_split('/\s+/', $normalizedText) ?: [];
        $firstToken = $tokens[0] ?? null;
        if ($firstToken === null || mb_strlen($firstToken) < 3) {
            return null;
        }

        $tokenMatches = $vendors->filter(function (AssetVendor $vendor) use ($firstToken): bool {
            $vendorName = mb_strtolower((string) $vendor->name);
            if ($vendorName === '') {
                return false;
            }

            $vendorTokens = preg_split('/\s+/', $vendorName) ?: [];
            $vendorFirstToken = $vendorTokens[0] ?? '';

            return str_starts_with($vendorFirstToken, $firstToken) || str_starts_with($firstToken, $vendorFirstToken);
        })->values();

        if ($tokenMatches->count() === 1) {
            return $tokenMatches->first()?->name;
        }

        return null;
    }

    private function matchVendorByName(?string $vendorInput): ?AssetVendor
    {
        if ($vendorInput === null) {
            return null;
        }

        $normalized = mb_strtolower($vendorInput);
        $exact = AssetVendor::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->get();

        if ($exact->count() === 1) {
            return $exact->first();
        }

        if ($exact->count() > 1) {
            return null;
        }

        $partial = AssetVendor::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])
            ->get();

        if ($partial->count() === 1) {
            return $partial->first();
        }

        return null;
    }

    private function inferAssetTypeNameFromText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalizedText = mb_strtolower($text);
        $types = AssetType::query()->select(['name'])->get();

        $containsMatches = $types->filter(function (AssetType $type) use ($normalizedText): bool {
            $name = mb_strtolower((string) $type->name);

            return $name !== '' && str_contains($normalizedText, $name);
        })->values();

        if ($containsMatches->count() === 1) {
            return $containsMatches->first()?->name;
        }

        if (str_contains($normalizedText, 'iphone') || str_contains($normalizedText, 'smartphone') || str_contains($normalizedText, 'handy')) {
            $smartphone = $types->first(function (AssetType $type): bool {
                return mb_strtolower((string) $type->name) === 'smartphone';
            });
            if ($smartphone !== null) {
                return $smartphone->name;
            }
        }

        return null;
    }

    private function combinedText(Request $request): ?string
    {
        $text = $this->nullableTrimmedString($request->get('text'));
        $model = $this->nullableTrimmedString($request->get('model'));

        $combined = trim(($text ?? '').' '.($model ?? ''));

        return $combined === '' ? null : $combined;
    }
}
