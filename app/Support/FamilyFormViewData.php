<?php

namespace App\Support;

use App\Libraries\SectorIds;

/**
 * Prepares family form variables before the view renders HTML.
 */
class FamilyFormViewData
{
    public static function prepare(array $data): array
    {
        $formOptions = array_merge(self::defaultFormOptions(), self::arrayValue($data['formOptions'] ?? []));
        $sectorOptions = $data['sectorOptions'] ?? ($formOptions['sectors'] ?? []);
        $sectorGroups = self::arrayValue($data['sectorGroups'] ?? []);
        $sexOptions = $data['sexOptions'] ?? ($formOptions['sexes'] ?? []);
        $suffixOptions = $data['suffixOptions'] ?? ($formOptions['suffixes'] ?? []);
        $civilOptions = $data['civilOptions'] ?? ($formOptions['civil_statuses'] ?? []);
        $relationshipOptions = $data['relationshipOptions'] ?? ($formOptions['relationships'] ?? []);
        $educationOptions = $data['educationOptions'] ?? ($formOptions['education_levels'] ?? []);
        $jobOptions = $data['jobOptions'] ?? ($formOptions['job_options'] ?? []);
        $incomeOptions = $data['incomeOptions'] ?? ($formOptions['income_ranges'] ?? []);
        $serviceGroups = self::arrayValue($data['serviceGroups'] ?? []);
        $serviceOptions = self::arrayValue($data['serviceOptions'] ?? ($formOptions['services'] ?? []));
        $familyHeads = $data['familyHeads'] ?? ($formOptions['family_heads'] ?? []);
        $formAction = $data['formAction'] ?? site_url('families');
        $submitButtonLabel = $data['submitButtonLabel'] ?? 'Save Record Data';
        $familyRecord = self::arrayValue($data['familyRecord'] ?? []);
        $existingMembers = self::arrayValue($data['existingMembers'] ?? []);
        $headServiceIds = self::integerList($data['headServiceIds'] ?? ($familyRecord['services'] ?? ($familyRecord['service_ids'] ?? [])));
        $isEditMode = $familyRecord !== [];
        $selectedSectorIds = SectorIds::normalize($familyRecord['sectorID'] ?? null);
        $initialFamilyData = [
            'selectedSectorIds' => $selectedSectorIds,
            'headServiceIds' => $headServiceIds,
            'existingMembers' => $existingMembers,
        ];
        $fieldViewData = compact(
            'civilOptions',
            'educationOptions',
            'familyRecord',
            'incomeOptions',
            'jobOptions',
            'relationshipOptions',
            'sectorGroups',
            'sectorOptions',
            'serviceGroups',
            'serviceOptions',
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
            'jobOptions',
            'relationshipOptions',
            'sectorGroups',
            'sectorOptions',
            'selectedSectorIds',
            'serviceGroups',
            'serviceOptions',
            'sexOptions',
            'submitButtonLabel',
            'suffixOptions'
        );
    }

    private static function defaultFormOptions(): array
    {
        return [
            'sectors' => [],
            'sexes' => [],
            'suffixes' => [],
            'civil_statuses' => [],
            'relationships' => [],
            'education_levels' => [],
            'job_options' => [],
            'income_ranges' => [],
            'services' => [],
            'family_heads' => [],
        ];
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
