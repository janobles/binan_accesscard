<?php

namespace App\Support;

use App\Libraries\ViewFormatter;

/**
 * Shapes a raw `member` row (from MemberModel) into the read-only structure the
 * family detail view (`Family/view`) renders: a display name,
 * a label/value detail grid, the comma-joined sector names, and resolved service
 * names. Pure presentation — no DB or session access; callers pass in the already
 * resolved service names and the income value→label map.
 */
class FamilyRecordPresenter
{
    /**
     * Builds the head-of-family card data: full name, created date/time, the profile
     * detail grid (including address), the head's sectors, and availed services.
     */
    public static function head(array $row, array $serviceNames, array $incomeLabels): array
    {
        return [
            'fullName'    => self::fullName($row),
            'createdDate' => ViewFormatter::formatDate($row['dt_created'] ?? ''),
            'createdTime' => ViewFormatter::formatTime($row['dt_created'] ?? ''),
            'details'     => self::details($row, $incomeLabels),
            'sectorName'  => (string) ($row['sector_name'] ?? ''),
            'services'    => array_values($serviceNames),
        ];
    }

    /**
     * Builds one family-member card: full name, relationship, the detail grid, the
     * member's sectors, and availed services.
     */
    public static function member(array $row, array $serviceNames, array $incomeLabels): array
    {
        return [
            'fullName'     => self::fullName($row),
            'relationship' => (string) ($row['relationship'] ?? 'Member'),
            'details'      => self::details($row, $incomeLabels),
            'sectorName'   => (string) ($row['sector_name'] ?? ''),
            'services'     => array_values($serviceNames),
        ];
    }

    /** Joins first/middle/last/suffix into a single display name, or '-' when blank. */
    private static function fullName(array $row): string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) ($row['firstname'] ?? '')),
            trim((string) ($row['middlename'] ?? '')),
            trim((string) ($row['lastname'] ?? '')),
            trim((string) ($row['suffix'] ?? '')),
        ], static fn (string $part): bool => $part !== '')));

        return $name === '' ? '-' : $name;
    }

    /**
     * Builds the [{label, value}] profile grid shared by the head and member cards.
     * Maps the income bracket value to its human label. Address already contains the
     * barangay (combined on save), so it renders as a single Address row.
     */
    private static function details(array $row, array $incomeLabels): array
    {
        $salary = (string) ($row['Salary'] ?? '');
        $income = $salary === '' ? '' : (string) ($incomeLabels[$salary] ?? $salary);
        $address = trim((string) ($row['address'] ?? ''));

        $details = [
            ['label' => 'First name', 'value' => (string) ($row['firstname'] ?? '')],
            ['label' => 'Middle name', 'value' => (string) ($row['middlename'] ?? '')],
            ['label' => 'Last name', 'value' => (string) ($row['lastname'] ?? '')],
            ['label' => 'Birthday', 'value' => ViewFormatter::formatDate($row['birthday'] ?? '')],
            ['label' => 'Sex', 'value' => (string) ($row['sex'] ?? '')],
            ['label' => 'Civil status', 'value' => (string) ($row['civilstatus'] ?? '')],
            ['label' => 'Contact number', 'value' => (string) ($row['contactnumber'] ?? '')],
            ['label' => 'Religion', 'value' => (string) ($row['religion'] ?? '')],
            ['label' => 'Education', 'value' => (string) ($row['education'] ?? '')],
            ['label' => 'Job', 'value' => (string) ($row['job'] ?? '')],
            ['label' => 'Monthly income', 'value' => $income],
            ['label' => 'Address', 'value' => $address],
        ];

        // Blank values render as '-' in the view; normalize empties so that holds.
        return array_map(static function (array $detail): array {
            $detail['value'] = trim((string) $detail['value']) === '' ? '-' : $detail['value'];

            return $detail;
        }, $details);
    }
}
