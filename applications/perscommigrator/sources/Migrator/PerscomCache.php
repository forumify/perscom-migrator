<?php

declare(strict_types=1);

namespace IPS\perscommigrator\Migrator;

class _perscomCache
{
    protected $cache = [];

    public function set(string $resource, array $items): void
    {
        $this->cache[$resource] = $items;
    }

    public function get(string $resource): array
    {
        return $this->cache[$resource] ?? [];
    }

    public function findBy(string $resource, string $field, $value, ?callable $normalizer = null): ?array
    {
        if (!isset($this->cache[$resource])) {
            return null;
        }

        foreach ($this->cache[$resource] as $item) {
            if (!isset($item[$field])) {
                continue;
            }

            $cacheVal = $normalizer === null ? $item[$field] : $normalizer($item[$field]);
            $searchVal = $normalizer === null ? $value : $normalizer($value);
            if ($cacheVal === $searchVal) {
                return $item;
            }
        }

        return null;
    }
}
