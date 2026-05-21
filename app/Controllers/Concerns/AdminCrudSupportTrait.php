<?php

namespace App\Controllers\Concerns;

use CodeIgniter\HTTP\RedirectResponse;

trait AdminCrudSupportTrait
{
    private function ensureAdminAccess(): ?RedirectResponse
    {
        return $this->requireRole(['Developer', 'Admin']);
    }

    private function redirectAdmin(string $path, string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url($path))->with($type, $message);
    }

    private function archiveRecord(string $table, string $primaryKey, int $id): bool
    {
        $db = db_connect();

        if (! $db->tableExists($table)) {
            return false;
        }

        $builder = $db->table($table)->where($primaryKey, $id);

        if ($db->fieldExists('isactive', $table)) {
            return (bool) $builder->update(['isactive' => 0]);
        }

        if ($db->fieldExists('status', $table)) {
            return (bool) $builder->update(['status' => 'Archived']);
        }

        if ($db->fieldExists('archived_at', $table)) {
            return (bool) $builder->update(['archived_at' => date('Y-m-d H:i:s')]);
        }

        if ($db->fieldExists('deleted_at', $table)) {
            return (bool) $builder->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        return false;
    }
}
