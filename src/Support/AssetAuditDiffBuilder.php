<?php

namespace Hwkdo\IntranetAppAssets\Support;

class AssetAuditDiffBuilder
{
    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $changes
     * @param  list<string>  $allowedFields
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function build(array $original, array $changes, array $allowedFields): array
    {
        $allowed = array_flip($allowedFields);
        $diff = [];

        foreach ($changes as $field => $newValue) {
            if (! isset($allowed[$field])) {
                continue;
            }

            $oldValue = $original[$field] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $diff[$field] = [
                'old' => self::normalize($oldValue),
                'new' => self::normalize($newValue),
            ];
        }

        return $diff;
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_bool($value) || $value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return json_decode((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return json_decode((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
    }
}
