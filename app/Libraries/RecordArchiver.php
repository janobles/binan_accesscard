<?php

namespace App\Libraries;

class RecordArchiver
{
    public static function archive(string $table, string $primaryKey, int $id): bool
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
