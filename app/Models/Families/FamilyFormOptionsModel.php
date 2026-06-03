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
            'sectors' => $sectorModel->getAll(),
            'sexes' => ['Male', 'Female'],
            'suffixes' => FamilyProfilingFormV2::suffixes(),
            'civil_statuses' => FamilyProfilingFormV2::civilStatuses(),
            'relationships' => [
                'Spouse',
                'Son',
                'Daughter',
                'Parent',
                'Sibling',
                'Grandparent',
                'Grandchild',
                'In-law',
                'Relative',
                'Household Helper',
                'Other',
            ],
            'education_levels' => FamilyProfilingFormV2::educationLevels(),
            'job_options' => FamilyProfilingFormV2::jobOptions(),
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
            'services' => $servicesModel->getAll(),
            'family_heads' => [],
        ];
    }

    /**
     * Shapes getOptions() into the exact view variables the family form template
     * expects (sectorOptions, sexOptions, servicesByCategory, etc.). Frontend:
     * consumed directly by the `Dashboard/familyform/familyform` view.
     */
    public function getViewData(): array
    {
        $options = $this->getOptions();
        $sectorOptions = $options['sectors'] ?? [];
        $serviceOptions = $options['services'] ?? [];
        $sectorModel = new SectorModel();
        $servicesByCategory = $this->groupServicesByCategory($serviceOptions);

        return [
            'formOptions' => $options,
            'sectorOptions' => $sectorOptions,
            'sectorCatalog' => $sectorModel->getSectorCatalog($sectorOptions),
            'sexOptions' => $options['sexes'] ?? [],
            'suffixOptions' => $options['suffixes'] ?? [],
            'civilOptions' => $options['civil_statuses'] ?? [],
            'relationshipOptions' => $options['relationships'] ?? [],
            'educationOptions' => $options['education_levels'] ?? [],
            'jobOptions' => $options['job_options'] ?? [],
            'incomeOptions' => $options['income_ranges'] ?? [],
            'serviceOptions' => $serviceOptions,
            'servicesByCategory' => $servicesByCategory,
            'familyHeads' => $options['family_heads'] ?? [],
        ];
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