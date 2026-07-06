<?php

namespace App\Models\Concerns;

/**
 * Aggregates the smaller model-layer query-helper traits (ID normalization,
 * sector/user/member name resolution) behind a single `use` for consumers that
 * need all of them. Hosting classes must expose $this->db (CodeIgniter Model or
 * BaseConnection). No unique logic lives here — see the individual traits for
 * implementation and per-trait consumers.
 */
trait ModelQueryHelpers
{
    use NormalizesIds;
    use ResolvesSectorNames;
    use ResolvesUserNames;
    use ResolvesMemberNames;
}
