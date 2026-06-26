<?php

namespace App\Models\Concerns;

/**
 * Small ID / filter-list normalizers shared across the model layer. These are
 * pure helpers (no DB access).
 */
trait NormalizesIds
{
    /** Normalizes an ID list to unique positive ints for batched IN() lookups. */
    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * Normalizes a filter control's value(s) to a unique list, dropping blanks,
     * the "__all" sentinel and zero. $integers casts each entry to an int string.
     */
    private function normalizeFilterList(mixed $value, bool $integers = true): array
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            $item = trim((string) $item);

            if ($item === '' || $item === '__all') {
                continue;
            }

            $normalized[] = $integers ? (string) (int) $item : $item;
        }

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $item): bool => $item !== '' && $item !== '0'
        )));
    }
}
