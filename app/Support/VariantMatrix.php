<?php

namespace App\Support;

class VariantMatrix
{
    /**
     * Cartesian product of attribute-value ids, grouped by attribute.
     *
     *   [[أحمر, أزرق], [M, XL]]  ->  [[أحمر,M], [أحمر,XL], [أزرق,M], [أزرق,XL]]
     *
     * Empty groups are dropped, so an attribute the admin left unticked simply
     * doesn't take part instead of collapsing the whole result to nothing.
     *
     * @param  array<int, array<int, int>>  $groups
     * @return array<int, array<int, int>>
     */
    public static function combinations(array $groups): array
    {
        $groups = array_values(array_filter($groups, static fn (array $g): bool => $g !== []));

        if ($groups === []) {
            return [];
        }

        $combinations = [[]];

        foreach ($groups as $group) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($group as $valueId) {
                    $next[] = [...$combination, (int) $valueId];
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }

    /** Deterministic SKU for a combination, so re-generating is idempotent. */
    public static function sku(string $prefix, array $valueIds): string
    {
        sort($valueIds);

        return strtoupper(trim($prefix, '-')).'-'.implode('-', $valueIds);
    }
}
