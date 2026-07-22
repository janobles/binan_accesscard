<?php

namespace App\Support;

use DateTimeImmutable;

/** Applies date-of-birth eligibility to age-specific sectors and services. */
class FamilyAgeEligibility
{
    private const CHILD_SECTOR_CODE = 'B';
    private const SENIOR_SECTOR_CODE = 'SC';
    private const CHILD_SERVICE_CATEGORY = 'Bata (Children)';
    private const SENIOR_SERVICE_CATEGORY = 'Senior Citizen';

    /**
     * Returns the first eligibility error for one person, or null when valid.
     * Invalid birthdays are left to the existing field validation rules.
     */
    public static function selectionError(
        mixed $birthday,
        array $sectorIds,
        array $serviceIds,
        array $sectorRows,
        array $serviceRows,
        ?DateTimeImmutable $today = null,
    ): ?string {
        $birthdayValue = (string) $birthday;
        $birthday = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdayValue);
        $today ??= new DateTimeImmutable('today');

        if ($birthday === false || $birthday->format('Y-m-d') !== $birthdayValue || $birthday > $today) {
            return null;
        }

        $selectedSectorIds = array_fill_keys(array_map('intval', $sectorIds), true);
        $selectedServiceIds = array_fill_keys(array_map('intval', $serviceIds), true);
        $sectorCodes = [];
        $serviceCategories = [];

        foreach ($sectorRows as $sector) {
            if (isset($selectedSectorIds[(int) ($sector['sectorID'] ?? 0)])) {
                $sectorCodes[] = strtoupper(trim((string) ($sector['shortcode'] ?? '')));
            }
        }

        foreach ($serviceRows as $service) {
            if (isset($selectedServiceIds[(int) ($service['serviceID'] ?? 0)])) {
                $serviceCategories[] = strtolower(trim((string) ($service['category'] ?? '')));
            }
        }

        $age = $birthday->diff($today)->y;
        $hasChildSelection = in_array(self::CHILD_SECTOR_CODE, $sectorCodes, true)
            || in_array(strtolower(self::CHILD_SERVICE_CATEGORY), $serviceCategories, true);
        $hasSeniorSelection = in_array(self::SENIOR_SECTOR_CODE, $sectorCodes, true)
            || in_array(strtolower(self::SENIOR_SERVICE_CATEGORY), $serviceCategories, true);

        if ($age >= 18 && $hasChildSelection) {
            return 'B - Bata (Children) sector and Bata (Children) services are only available to persons below 18 years old.';
        }

        if ($age < 60 && $hasSeniorSelection) {
            return 'SC - Senior Citizen sector and Senior Citizen programs are only available to persons 60 years old and above.';
        }

        return null;
    }
}
