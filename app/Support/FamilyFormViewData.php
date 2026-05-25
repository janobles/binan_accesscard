<?php

namespace App\Support;

/**
 * Prepares family form variables before the view renders HTML.
 */
class FamilyFormViewData
{
    public static function prepare(array $data): array
    {
        // Keep default form data here so the family form view only renders fields.
        $formOptions          = array_merge(self::defaultFormOptions(), self::arrayValue($data['formOptions'] ?? []));
        $sectorOptions        = $data['sectorOptions'] ?? ($formOptions['sectors'] ?? []);
        $sectorCatalog        = self::arrayValue($data['sectorCatalog'] ?? []);
        $sexOptions           = $data['sexOptions'] ?? ($formOptions['sexes'] ?? []);
        $suffixOptions        = $data['suffixOptions'] ?? ($formOptions['suffixes'] ?? []);
        $civilOptions         = $data['civilOptions'] ?? ($formOptions['civil_statuses'] ?? []);
        $relationshipOptions  = $data['relationshipOptions'] ?? ($formOptions['relationships'] ?? []);
        $educationOptions     = $data['educationOptions'] ?? ($formOptions['education_levels'] ?? []);
        $incomeOptions        = $data['incomeOptions'] ?? ($formOptions['income_ranges'] ?? []);
        $servicesByCategory   = $data['servicesByCategory'] ?? ($formOptions['services_by_category'] ?? []);
        $familyHeads          = $data['familyHeads'] ?? ($formOptions['family_heads'] ?? []);
        $formAction           = $data['formAction'] ?? site_url('families');
        $submitButtonLabel    = $data['submitButtonLabel'] ?? 'Save Family Data';
        $familyRecord         = self::arrayValue($data['familyRecord'] ?? []);
        $existingMembers      = self::arrayValue($data['existingMembers'] ?? []);
        $headServiceIds       = self::integerList($data['headServiceIds'] ?? ($familyRecord['service_ids'] ?? []));
        $isEditMode           = $familyRecord !== [];
        $selectedSectorIds    = SectorIds::normalize($familyRecord['sectorID'] ?? null);

        // The frontend wizard uses this payload to restore edit-mode selections.
        $selectedSectorCategories = self::selectedSectorCategories($sectorCatalog, $selectedSectorIds);
        $initialFamilyData         = [
            'selectedSectorIds'        => $selectedSectorIds,
            'selectedSectorCategories' => $selectedSectorCategories,
            'headServiceIds'           => $headServiceIds,
            'existingMembers'          => $existingMembers,
        ];
        $fieldViewData             = compact(
            'civilOptions',
            'educationOptions',
            'familyRecord',
            'incomeOptions',
            'relationshipOptions',
            'sectorOptions',
            'servicesByCategory',
            'sexOptions',
            'suffixOptions'
        );

        return compact(
            'civilOptions',
            'educationOptions',
            'existingMembers',
            'familyHeads',
            'familyRecord',
            'fieldViewData',
            'formAction',
            'formOptions',
            'headServiceIds',
            'incomeOptions',
            'initialFamilyData',
            'isEditMode',
            'relationshipOptions',
            'sectorCatalog',
            'sectorOptions',
            'selectedSectorCategories',
            'selectedSectorIds',
            'servicesByCategory',
            'sexOptions',
            'submitButtonLabel',
            'suffixOptions'
        );
    }

    private static function defaultFormOptions(): array
    {
        return [
            'sectors'              => [],
            'sexes'                => [],
            'suffixes'             => [],
            'civil_statuses'       => [],
            'relationships'        => [],
            'education_levels'     => [],
            'income_ranges'        => [],
            'services_by_category' => [],
            'family_heads'         => [],
        ];
    }

    private static function selectedSectorCategories(array $sectorCatalog, array $selectedSectorIds): array
    {
        $selectedCategories = [];

        // Match sector IDs back to their category keys for the sector/category controls.
        foreach ($sectorCatalog as $categoryKey => $sectorRows) {
            foreach ((array) $sectorRows as $sectorRow) {
                if (in_array((int) ($sectorRow['sectorID'] ?? 0), $selectedSectorIds, true)) {
                    $selectedCategories[] = (string) $categoryKey;
                    break;
                }
            }
        }

        return array_values(array_unique($selectedCategories));
    }

    private static function integerList(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $value
        ));
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
