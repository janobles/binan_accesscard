<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class MakeAuditTrailMemberIdNullable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('audit_trails') || ! $this->db->fieldExists('memberID', 'audit_trails')) {
            return;
        }

        if ($this->hasMemberForeignKey()) {
            $this->forge->dropForeignKey('audit_trails', 'fk_audit_member');
        }

        $this->forge->modifyColumn('audit_trails', [
            'memberID' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
        ]);

        $this->addMemberForeignKey();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('audit_trails') || ! $this->db->fieldExists('memberID', 'audit_trails')) {
            return;
        }

        if ($this->db->table('audit_trails')->where('memberID', null)->countAllResults() > 0) {
            throw new RuntimeException('Cannot require audit_trails.memberID while staff-only audit rows exist.');
        }

        if ($this->hasMemberForeignKey()) {
            $this->forge->dropForeignKey('audit_trails', 'fk_audit_member');
        }

        $this->forge->modifyColumn('audit_trails', [
            'memberID' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
        ]);

        $this->addMemberForeignKey();
    }

    private function addMemberForeignKey(): void
    {
        $this->forge
            ->addForeignKey('memberID', 'member', 'memberID', '', '', 'fk_audit_member')
            ->processIndexes('audit_trails');
    }

    private function hasMemberForeignKey(): bool
    {
        foreach ($this->db->getForeignKeyData('audit_trails') as $foreignKey) {
            if (($foreignKey->constraint_name ?? '') === 'fk_audit_member') {
                return true;
            }
        }

        return false;
    }
}
