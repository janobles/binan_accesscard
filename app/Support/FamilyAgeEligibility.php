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
    private const CHILD_BOUNDS = ['min' => null, 'max' => 17];
    private const SENIOR_BOUNDS = ['min' => 60, 'max' => null];

    /**
     * Age bounds for one sector, in completed years, or null when it is open to any
     * age. The view stamps these on the checkbox so the client stops carrying its own
     * copy of the thresholds.
     *
     * @return array{min: int|null, max: int|null}|null
     */
    public static function sectorAgeBounds(string $shortcode): ?array
    {
        $code = strtoupper(trim($shortcode));

        if ($code === self::CHILD_SECTOR_CODE) {
            return self::CHILD_BOUNDS;
        }

        return $code === self::SENIOR_SECTOR_CODE ? self::SENIOR_BOUNDS : null;
    }

    /**
     * Age bounds for one service category, matched the same way selectionError()
     * matches it (trimmed, case-insensitive).
     *
     * @return array{min: int|null, max: int|null}|null
     */
    public static function serviceCategoryAgeBounds(string $category): ?array
    {
        $name = strtolower(trim($category));

        if ($name === strtolower(self::CHILD_SERVICE_CATEGORY)) {
            return self::CHILD_BOUNDS;
        }

        return $name === strtolower(self::SENIOR_SERVICE_CATEGORY) ? self::SENIOR_BOUNDS : null;
    }

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

        if (self::CHILD_BOUNDS['max'] !== null && $age > self::CHILD_BOUNDS['max'] && $hasChildSelection) {
            return 'B - Bata (Children) sector and Bata (Children) services are only available to persons below 18 years old.';
        }

        if (self::SENIOR_BOUNDS['min'] !== null && $age < self::SENIOR_BOUNDS['min'] && $hasSeniorSelection) {
            return 'SC - Senior Citizen sector and Senior Citizen programs are only available to persons 60 years old and above.';
        }

        return null;
    }
}
