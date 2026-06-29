<?php

namespace App\Models\Concerns;

/**
 * Canonical record-status filter values shared by the model layer. These are the
 * exact strings the lookup/member queries compare against and the UI sends, named
 * here so they are not retyped (and mistyped) across models. The values must not
 * change — DB filters, view output and JS contracts depend on them.
 */
final class RecordStatus
{
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';
    public const ALL = 'all';

    private function __construct()
    {
    }
}
