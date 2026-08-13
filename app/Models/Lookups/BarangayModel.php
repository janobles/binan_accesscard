<?php

namespace App\Models\Lookups;

use App\Support\MemberFieldNormalizer;
use CodeIgniter\Model;

/**
 * Barangay reference data for the family form, the Excel importer, and the
 * dashboard's barangay rollup. Replaces parsing the tail of member.address:
 * grouping and filtering now run on an indexed integer key. Returns safe empty
 * shapes on any DB error, matching the other lookup models.
 */
class BarangayModel extends Model
{
    protected $table         = 'barangay';
    protected $primaryKey    = 'barangayID';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['name', 'dt_deleted'];

    /** Live barangays, name order, for dropdowns and multi-selects. */
    public function activeList(): array
    {
        try {
            return $this->select('barangayID, name')
                ->where('dt_deleted', null)
                ->orderBy('name', 'ASC')
                ->findAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Live barangay names in name order - the one list every dropdown, the Excel
     * template's Barangay validation list, and the importer's spelling check
     * read. A second hardcoded copy used to live in FamilyProfilingFormV2 and had
     * drifted from the table ("Soro-Soro" against "SORO-SORO", "Santo Tomas
     * (Calabuso)" against "SANTO TOMAS"), so a form value could resolve to no
     * barangay at all.
     *
     * @return list<string>
     */
    public function activeNames(): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            $this->activeList()
        ));
    }

    /** barangayID to name, for labelling rows the rollup returns by ID. */
    public function nameMap(): array
    {
        $map = [];
        foreach ($this->activeList() as $row) {
            $map[(int) $row['barangayID']] = (string) $row['name'];
        }

        return $map;
    }

    /**
     * Folded barangay name (MemberFieldNormalizer::barangayKey()) to barangayID, so
     * the family form and the Excel importer can resolve a free-text barangay
     * value to its id without a per-row query. Not exposed by activeList()/
     * nameMap(), which are name-out lookups; this is the name-in direction those
     * callers need instead.
     */
    public function idByNormalizedName(): array
    {
        $map = [];
        foreach ($this->activeList() as $row) {
            $map[MemberFieldNormalizer::barangayKey((string) $row['name'])] = (int) $row['barangayID'];
        }

        return $map;
    }
}
