<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Builds select options used by the family registration UI.
 */
class FamilyFormOptionsModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';

    public function getOptions(): array
    {
        $sectorModel = new SectorModel();
        $servicesModel = new ServicesModel();

        return [
            'sectors' => $sectorModel->getAll(),
            'sexes' => ['Male', 'Female'],
            'suffixes' => ['I', 'II', 'III', 'IV'],
            'civil_statuses' => [
                'Single',
                'Married',
                'Widowed',
                'Others',
            ],
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
            'education_levels' => [
                'No Grade Completed',
                'Early Childhood Education / Day Care',
                'Kindergarten',
                'Elementary Undergraduate',
                'Elementary Graduate',
                'Junior High School Undergraduate',
                'Junior High School Graduate',
                'Senior High School Undergraduate',
                'Senior High School Graduate',
                'Alternative Learning System (ALS) Completer',
                'Technical-Vocational Undergraduate',
                'Technical-Vocational Graduate',
                'College Undergraduate',
                'Associate Degree Graduate',
                'Bachelor\'s Degree Graduate',
                'Post-Baccalaureate',
                'Master\'s Degree',
                'Doctorate Degree',
            ],
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

    public function getViewData(): array
    {
        $options = $this->getOptions();
        $sectorOptions = $options['sectors'] ?? [];
        $serviceOptions = $options['services'] ?? [];

        return [
            'formOptions' => $options,
            'sectorOptions' => $sectorOptions,
            'sexOptions' => $options['sexes'] ?? [],
            'suffixOptions' => $options['suffixes'] ?? [],
            'civilOptions' => $options['civil_statuses'] ?? [],
            'relationshipOptions' => $options['relationships'] ?? [],
            'educationOptions' => $options['education_levels'] ?? [],
            'incomeOptions' => $options['income_ranges'] ?? [],
            'serviceOptions' => $serviceOptions,
            'familyHeads' => $options['family_heads'] ?? [],
        ];
    }
}
