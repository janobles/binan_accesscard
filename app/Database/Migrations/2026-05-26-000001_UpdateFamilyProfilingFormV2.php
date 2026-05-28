<?php

namespace App\Database\Migrations;

use App\Support\FamilyProfilingFormV2;
use CodeIgniter\Database\Migration;

class UpdateFamilyProfilingFormV2 extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('member')) {
            $this->forge->modifyColumn('member', [
                'suffix' => ['name' => 'suffix', 'type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'civilstatus' => ['name' => 'civilstatus', 'type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'contactnumber' => ['name' => 'contactnumber', 'type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            ]);

            $memberFields = [];

            if (! $this->db->fieldExists('religion', 'member')) {
                $memberFields['religion'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'contactnumber'];
            }

            if (! $this->db->fieldExists('address', 'member')) {
                $memberFields['address'] = ['type' => 'TEXT', 'null' => true, 'after' => 'religion'];
            }

            if (! $this->db->fieldExists('barangay', 'member')) {
                $memberFields['barangay'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'address'];
            }

            if ($memberFields !== []) {
                $this->forge->addColumn('member', $memberFields);
            }

            $this->remapLegacySectorSelections();
        }

        if ($this->db->tableExists('sector')) {
            $this->forge->modifyColumn('sector', [
                'shortcode' => ['name' => 'shortcode', 'type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
            ]);

            foreach (FamilyProfilingFormV2::sectorRows() as $row) {
                $this->upsertById('sector', 'sectorID', (int) $row['sectorID'], $row);
            }

            $this->db->table('sector')
                ->where('sectorID >', 24)
                ->where("shortcode REGEXP '^(SC|PWD|SP|B|OSCA)[0-9]+'", null, false)
                ->delete();
        }

        if ($this->db->tableExists('services')) {
            foreach (FamilyProfilingFormV2::serviceRows() as $index => $row) {
                $this->upsertById('services', 'serviceID', $index, [
                    'serviceID' => $index,
                    'category' => $row['category'],
                    'name' => $row['code'] . ' - ' . $row['name'],
                    'description' => $row['description'],
                ]);
            }
        }
    }

    public function down(): void
    {
        // The prior schema used narrow enums and incomplete option data.
        // Rolling that back would discard valid v2 form entries.
    }

    private function upsertById(string $table, string $primaryKey, int $id, array $data): void
    {
        $exists = $this->db->table($table)
            ->where($primaryKey, $id)
            ->countAllResults() > 0;

        if ($exists) {
            $this->db->table($table)
                ->where($primaryKey, $id)
                ->update($data);

            return;
        }

        $this->db->table($table)->insert($data);
    }

    private function remapLegacySectorSelections(): void
    {
        if (! $this->db->tableExists('sector') || $this->db->table('sector')
            ->where("shortcode REGEXP '^(PWD|SP|OSCA)[0-9]+'", null, false)
            ->countAllResults() === 0) {
            return;
        }

        $this->db->query("
            UPDATE member
            SET sectorID = CONCAT(
                '[',
                TRIM(BOTH ',' FROM CONCAT(
                    IF(JSON_CONTAINS(sectorID, '8') OR JSON_CONTAINS(sectorID, '9') OR JSON_CONTAINS(sectorID, '10') OR JSON_CONTAINS(sectorID, '11') OR JSON_CONTAINS(sectorID, '12') OR JSON_CONTAINS(sectorID, '13') OR JSON_CONTAINS(sectorID, '14'), '1,', ''),
                    IF(JSON_CONTAINS(sectorID, '1') OR JSON_CONTAINS(sectorID, '2') OR JSON_CONTAINS(sectorID, '3') OR JSON_CONTAINS(sectorID, '4') OR JSON_CONTAINS(sectorID, '5'), '2,', ''),
                    IF(JSON_CONTAINS(sectorID, '6') OR JSON_CONTAINS(sectorID, '7'), '3,', '')
                )),
                ']'
            )
            WHERE JSON_VALID(sectorID)
              AND (
                JSON_CONTAINS(sectorID, '1') OR JSON_CONTAINS(sectorID, '2') OR JSON_CONTAINS(sectorID, '3') OR JSON_CONTAINS(sectorID, '4') OR JSON_CONTAINS(sectorID, '5') OR
                JSON_CONTAINS(sectorID, '6') OR JSON_CONTAINS(sectorID, '7') OR JSON_CONTAINS(sectorID, '8') OR JSON_CONTAINS(sectorID, '9') OR JSON_CONTAINS(sectorID, '10') OR
                JSON_CONTAINS(sectorID, '11') OR JSON_CONTAINS(sectorID, '12') OR JSON_CONTAINS(sectorID, '13') OR JSON_CONTAINS(sectorID, '14')
              )
        ");
    }
}
