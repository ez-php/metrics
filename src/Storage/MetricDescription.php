<?php

declare(strict_types=1);

namespace EzPhp\Metrics\Storage;

/**
 * Class MetricDescription
 *
 * Decodes a stored metric description, rejecting anything malformed.
 *
 * @internal Used by the shared storages.
 * @package EzPhp\Metrics\Storage
 */
final class MetricDescription
{
    /**
     * @param mixed $json The stored value (a JSON string when well-formed).
     *
     * @return array{type: string, help: string, buckets: list<float>}|null
     */
    public static function decode(mixed $json): ?array
    {
        $data = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($data) || !is_string($data['type'] ?? null) || !is_string($data['help'] ?? null)) {
            return null;
        }

        $buckets = [];

        foreach (is_array($data['buckets'] ?? null) ? $data['buckets'] : [] as $bucket) {
            if (is_int($bucket) || is_float($bucket)) {
                $buckets[] = (float) $bucket;
            }
        }

        return ['type' => $data['type'], 'help' => $data['help'], 'buckets' => $buckets];
    }
}
