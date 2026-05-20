<?php

namespace App\Models;

use CodeIgniter\Model;

class FamilyFormOptionsModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';

    public function getOptions(): array
    {
        $sectorModel = new SectorModel();

        return [
            'sectors' => $sectorModel->getSectorOptions(),
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
                'No Formal Education',
                'Day Care / Early Childhood',
                'Kindergarten',
                'Elementary Level',
                'Elementary Graduate',
                'Junior High School Level',
                'Junior High School Graduate',
                'Senior High School Level',
                'Senior High School Graduate',
                'Alternative Learning System (ALS)',
                'Technical / Vocational Level',
                'Technical / Vocational Graduate',
                'College Level',
                'College Graduate',
                'Postgraduate / Masteral',
                'Doctorate',
            ],
            'income_ranges' => [
                '' => 'Select',
                '0' => 'No regular income',
                '8000' => 'Below PHP 8,000',
                '13000' => 'PHP 8,000 - 13,000',
                '18000' => 'PHP 13,001 - 18,000',
                '25000' => 'PHP 18,001 - 25,000',
                '40000' => 'PHP 25,001 - 40,000',
                '65000' => 'PHP 40,001 - 65,000',
                '100000' => 'PHP 65,001 - 100,000',
                '150000' => 'PHP 100,001 - 150,000',
                '250000' => 'PHP 150,001 - 250,000',
                '250001' => 'Above PHP 250,000',
            ],
            'services_by_category' => $this->getServicesByCategory(),
        ];
    }

    public function getViewData(): array
    {
        $options = $this->getOptions();
        $sectorOptions = $options['sectors'] ?? [];
        $sectorModel = new SectorModel();

        return [
            'formOptions' => $options,
            'sectorOptions' => $sectorOptions,
            'sectorCatalog' => $sectorModel->getSectorCatalog($sectorOptions),
            'sexOptions' => $options['sexes'] ?? [],
            'suffixOptions' => $options['suffixes'] ?? [],
            'civilOptions' => $options['civil_statuses'] ?? [],
            'relationshipOptions' => $options['relationships'] ?? [],
            'educationOptions' => $options['education_levels'] ?? [],
            'incomeOptions' => $options['income_ranges'] ?? [],
            'servicesByCategory' => $options['services_by_category'] ?? [],
        ];
    }

    private function getServicesByCategory(): array
    {
        if (! $this->db->tableExists('services')) {
            return [];
        }

        $services = $this->db->table('services')
            ->select('serviceID, category, name, description')
            ->orderBy('category', 'ASC')
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($services as $service) {
            $category = (string) ($service['category'] ?? 'Other');
            $grouped[$category][] = $service;
        }

        return $grouped;
    }
}
