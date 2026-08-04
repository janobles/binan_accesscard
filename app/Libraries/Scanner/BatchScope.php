<?php

namespace App\Libraries\Scanner;

/**
 * The single rule for deciding which batch a page is showing. The dashboard,
 * the reports endpoint, and the scanner performance page all resolve the same
 * way: an explicit ?batch= wins, otherwise the open batch, otherwise the newest.
 * An unknown id resolves to zero rather than silently showing another batch.
 */
final class BatchScope
{
    /**
     * @param list<array{batch_id:int|string}> $batches newest first
     * @return array{0:int,1:?array}
     */
    public static function resolve(array $batches, ?array $active, int $requested): array
    {
        $batchId = $requested;
        if ($batchId <= 0) {
            $batchId = $active !== null
                ? (int) $active['batch_id']
                : (int) ($batches[0]['batch_id'] ?? 0);
        }

        foreach ($batches as $b) {
            if ((int) $b['batch_id'] === $batchId) {
                return [$batchId, $b];
            }
        }

        return [0, null];
    }
}
