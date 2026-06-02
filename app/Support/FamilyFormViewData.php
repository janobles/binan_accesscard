<?php

namespace App\Support;

use App\Libraries\SectorIds;
use App\Libraries\ViewFormatter;

/**
 * Prepares family form variables before the view renders HTML.
 */
/**
 * Normalizes raw data into every variable the family form view needs. Handles both
 * create and edit modes (presence of familyRecord switches to edit), precomputes
 * the selected sectors/categories/services, and groups services by category.
 * Called from the family form view helper before the template renders.
 */
class FamilyFormViewData
{
    /**
     * Builds the full family-form view-data bundle from $data, filling defaults for
     * any missing option lists. Returns the variables the form template extracts.
     */
    public static function prepare(array $data): array
    {
        $formOptions = array_merge(self::defaultFormOptions(), self::arrayValue($data['formOptions'] ?? []));
        $sectorOptions = $data['sectorOptions'] ?? ($formOptions['sectors'] ?? []);
        $sectorCatalog = self::arrayValue($data['sectorCatalog'] ?? []);
        $sexOptions = $data['sexOptions'] ?? ($formOptions['sexes'] ?? []);
        $suffixOptions = $data['suffixOptions'] ?? ($formOptions['suffixes'] ?? []);
        $civilOptions = $data['civilOptions'] ?? ($formOptions['civil_statuses'] ?? []);
        $relationshipOptions = $data['relationshipOptions'] ?? ($formOptions['relationships'] ?? []);
        $educationOptions = $data['educationOptions'] ?? ($formOptions['education_levels'] ?? []);
        $jobOptions = $data['jobOptions'] ?? ($formOptions['job_options'] ?? []);
        $incomeOptions = $data['incomeOptions'] ?? ($formOptions['income_ranges'] ?? []);
        $servicesByCategory = self::arrayValue($data['servicesByCategory'] ?? []);
        $serviceOptions = self::arrayValue($data['serviceOptions'] ?? ($formOptions['services'] ?? []));
        if ($servicesByCategory === []) {
            $servicesByCategory = self::servicesByCategory($serviceOptions);
        }
        $familyHeads = $data['familyHeads'] ?? ($formOptions['family_heads'] ?? []);
        $formAction = $data['formAction'] ?? site_url('families');
        $submitButtonLabel = $data['submitButtonLabel'] ?? 'Save Record Data';
        $familyRecord = self::arrayValue($data['familyRecord'] ?? []);
        $existingMembers = self::arrayValue($data['existingMembers'] ?? []);
        $headServiceIds = self::integerList($data['headServiceIds'] ?? ($familyRecord['services'] ?? ($familyRecord['service_ids'] ?? [])));
        $isEditMode = $familyRecord !== [];
        $embeddedInModal = (bool) ($data['embeddedInModal'] ?? false);
        $selectedSectorIds = SectorIds::normalize($familyRecord['sectorID'] ?? null);
        $selectedSectorCategories = ViewFormatter::selectedSectorCategories($sectorCatalog, $selectedSectorIds);
        $initialFamilyData = [
            'selectedSectorIds' => $selectedSectorIds,
            'selectedSectorCategories' => $selectedSectorCategories,
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
            'sectorOptions',
            'servicesByCategory',
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
            'embeddedInModal',
            'jobOptions',
            'relationshipOptions',
            'sectorCatalog',
            'sectorOptions',
            'selectedSectorIds',
            'selectedSectorCategories',
            'servicesByCategory',
            'serviceOptions',
            'sexOptions',
            'submitButtonLabel',
            'suffixOptions'
        );
    }

    /** Empty option-list skeleton merged under the supplied options for safe defaults. */
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
            'services_by_category' => [],
            'family_heads' => [],
        ];
    }

    /** Groups a flat service list into [category => services[]] for the form. */
    private static function servicesByCategory(array $services): array
    {
        $grouped = [];

        foreach ($services as $service) {
            $category = trim((string) ($service['category'] ?? 'Other'));
            $grouped[$category !== '' ? $category : 'Other'][] = $service;
        }

        return $grouped;
    }

    /** Coerces a value into a list of ints (delegates to ViewFormatter). */
    private static function integerList(mixed $value): array
    {
        return ViewFormatter::integerList($value);
    }

    /** Returns the value if it's an array, else an empty array (safe-default guard). */
    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
