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
        $normalized = [];

        foreach ($ids as $id) {
            if (is_array($id)) {
                continue;
            }

            $id = (int) $id;

            if ($id > 0) {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Normalizes an ID list to unique natural ints (zero allowed), or null when
     * any value is malformed. Use this when bad input must be rejected.
     */
    private function naturalUniqueIds(array $ids): ?array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (is_array($id)) {
                return null;
            }

            $id = trim((string) $id);

            if ($id === '' || ! ctype_digit($id)) {
                return null;
            }

            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
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
