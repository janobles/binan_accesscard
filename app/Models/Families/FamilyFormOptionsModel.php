<?php

namespace App\Models\Families;

use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Support\FamilyProfilingFormV2;
use CodeIgniter\Model;

/**
 * Builds select options used by the family registration UI.
 */
class FamilyFormOptionsModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';

    /**
     * Returns the raw option lists for the family form: DB-backed sectors and
     * services plus static enumerations (sexes, suffixes, civil statuses,
     * relationships, education, jobs, income ranges) from FamilyProfilingFormV2.
     */
    public function getOptions(): array
    {
        $sectorModel = new SectorModel();
        $servicesModel = new ServiceModel();

        return [
            'sectors' => $sectorModel->getActive(),
            'sexes' => ['Male', 'Female'],
            'suffixes' => FamilyProfilingFormV2::suffixes(),
            'civil_statuses' => FamilyProfilingFormV2::civilStatuses(),
            'barangays' => FamilyProfilingFormV2::barangays(),
            'relationships' => [
                'Spouse',
                'Children',
                'Parent',
                'Sibling',
                'Grandparent',
                'Grandchild',
                'In-law',
                'Relative',
                'Other',
            ],
            'education_levels' => FamilyProfilingFormV2::educationLevels(),
            'job_options' => FamilyProfilingFormV2::jobOptions(),
            'religions' => FamilyProfilingFormV2::religions(),
            'income_ranges' => [
                ['value' => '', 'label' => 'Select'],
                ['value' => '0', 'label' => 'No regular income'],
                ['value' => '8000', 'label' => 'Below PHP 8,000'],
                ['value' => '13000', 'label' => 'PHP 8,000 - 13,000'],
                ['value' => '18000', 'label' => 'PHP 13,001 - 18,000'],
                ['value' => '25000', 'label' => 'PHP 18,001 - 25,000'],
                ['value' => '40000', 'label' => 'PHP 25,001 - 40,000'],
                ['value' => '65000', 'label' => 'PHP 40,001 - 65,000'],
                ['value' => '100000', 'label' => 'PHP 65,001 - 100,000'],
                ['value' => '150000', 'label' => 'PHP 100,001 - 150,000'],
                ['value' => '250000', 'label' => 'PHP 150,001 - 250,000'],
                ['value' => '250001', 'label' => 'Above PHP 250,000'],
            ],
            'services' => $servicesModel->getActive(),
            'family_heads' => [],
        ];
    }

    /**
     * Shapes getOptions() into the exact view variables the family form template
     * expects (sectorOptions, sexOptions, servicesByCategory, etc.). Frontend:
     * consumed directly by the `Family/form` view. Add-Family path: only active
     * sectors/services appear (archived items are never offered to new records).
     */
    public function getViewData(): array
    {
        return $this->buildViewData($this->getOptions());
    }

    /**
     * Edit-Family variant of getViewData(): on top of the active sectors/services, it
     * merges in any sectors/services a family already has that have since been archived,
     * tagging each merged row/catalog entry with is_archived => true. This keeps
     * grandfathered benefits visible (and re-postable) on the edit form so saving the
     * record never silently drops an archived-but-assigned item.
     *
     * @param list<int> $assignedSectorIds  Sector IDs currently assigned across the family.
     * @param list<int> $assignedServiceIds Service IDs currently assigned across the family.
     */
    public function getViewDataForEdit(array $assignedSectorIds, array $assignedServiceIds): array
    {
        $sectorModel = new SectorModel();
        $serviceModel = new ServiceModel();

        $options = $this->getOptions();

        $activeSectorIds = array_map(static fn (array $s): int => (int) ($s['sectorID'] ?? 0), $options['sectors'] ?? []);
        $activeServiceIds = array_map(static fn (array $s): int => (int) ($s['serviceID'] ?? 0), $options['services'] ?? []);

        $missingSectorIds = array_diff(array_map('intval', $assignedSectorIds), $activeSectorIds);
        $missingServiceIds = array_diff(array_map('intval', $assignedServiceIds), $activeServiceIds);

        $archivedSectorIds = [];

        foreach ($sectorModel->getByIdsIncludingArchived($missingSectorIds) as $sector) {
            $sector['is_archived'] = true;
            $options['sectors'][] = $sector;
            $archivedSectorIds[(int) ($sector['sectorID'] ?? 0)] = true;
        }

        foreach ($serviceModel->getByIdsIncludingArchived($missingServiceIds) as $service) {
            $service['is_archived'] = true;
            $options['services'][] = $service;
        }

        $viewData = $this->buildViewData($options);
        $viewData['sectorCatalog'] = $this->flagArchivedSectors($viewData['sectorCatalog'], $archivedSectorIds);

        return $viewData;
    }

    /**
     * Builds the family-form view variables from a (possibly augmented) options array.
     * Shared by getViewData() and getViewDataForEdit().
     */
    private function buildViewData(array $options): array
    {
        $sectorOptions = $options['sectors'] ?? [];
        $serviceOptions = $options['services'] ?? [];
        $sectorModel = new SectorModel();
        $servicesByCategory = $this->groupServicesByCategory($serviceOptions);

        return [
            'formOptions' => $options,
            'sectorOptions' => $sectorOptions,
            'sectorCatalog' => $sectorModel->getSectorCatalog($sectorOptions),
            // Sectors are flat classifications now, so there are no per-category
            // headings; kept as an empty map for the member-fields partial's signature.
            'sectorCategoryLabels' => [],
            // Text dropdowns are alphabetized for the form ("Other/Others" pinned last).
            // Suffix (Jr, Sr, I-V) and income brackets (numeric low->high) keep their order.
            'sexOptions' => $this->sortLabelOptions($options['sexes'] ?? []),
            'suffixOptions' => $options['suffixes'] ?? [],
            'civilOptions' => $this->sortLabelOptions($options['civil_statuses'] ?? []),
            'barangayOptions' => $this->sortLabelOptions($options['barangays'] ?? []),
            'relationshipOptions' => $this->sortLabelOptions($options['relationships'] ?? []),
            'educationOptions' => $this->sortLabelOptions($options['education_levels'] ?? []),
            'jobOptions' => $this->sortLabelOptions($options['job_options'] ?? []),
            'religionOptions' => $this->sortLabelOptions($options['religions'] ?? []),
            'incomeOptions' => $options['income_ranges'] ?? [],
            'serviceOptions' => $serviceOptions,
            'servicesByCategory' => $servicesByCategory,
            'familyHeads' => $options['family_heads'] ?? [],
        ];
    }

    /**
     * Marks the sector-catalog entries whose sectorID is in the archived set with
     * is_archived => true, so the picker can badge them. Leaves the catalog otherwise
     * untouched.
     *
     * @param array<string, list<array<string, mixed>>> $catalog
     * @param array<int, true>                          $archivedSectorIds
     */
    private function flagArchivedSectors(array $catalog, array $archivedSectorIds): array
    {
        if ($archivedSectorIds === []) {
            return $catalog;
        }

        foreach ($catalog as $group => $entries) {
            foreach ($entries as $index => $entry) {
                if (isset($archivedSectorIds[(int) ($entry['sectorID'] ?? 0)])) {
                    $catalog[$group][$index]['is_archived'] = true;
                }
            }
        }

        return $catalog;
    }

    /**
     * Sorts a flat list of dropdown labels alphabetically (case-insensitive), keeping
     * any "Other"/"Others" entry pinned at the end. Used for the family form's text
     * selects so options appear in a predictable order on both the head and member forms.
     *
     * @param list<string> $values
     * @return list<string>
     */
    private function sortLabelOptions(array $values): array
    {
        $values = array_values($values);

        usort($values, static function ($a, $b): int {
            $aIsOther = (bool) preg_match('/^others?$/i', trim((string) $a));
            $bIsOther = (bool) preg_match('/^others?$/i', trim((string) $b));

            if ($aIsOther !== $bIsOther) {
                return $aIsOther ? 1 : -1;
            }

            return strcasecmp((string) $a, (string) $b);
        });

        return $values;
    }

    /**
     * Groups the flat service list into [category => services[]] so the form can
     * render services under category headings.
     */
    private function groupServicesByCategory(array $services): array
    {
        $grouped = [];

        foreach ($services as $service) {
            $category = (string) ($service['category'] ?? 'Other');
            $grouped[$category][] = $service;
        }

        return $grouped;
    }

}