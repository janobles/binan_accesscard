<?php

if (! function_exists('familyPersonValue')) {
    function familyPersonValue(array $values, string $field): string
    {
        $fieldMap = [
            'last_name' => 'lastname',
            'first_name' => 'firstname',
            'middle_name' => 'middlename',
            'date_of_birth' => 'birthday',
            'civil_status' => 'civilstatus',
            'contact_number' => 'contactnumber',
            'monthly_income' => 'Salary',
        ];
        $rawValue = $values[$field] ?? $values[$fieldMap[$field] ?? $field] ?? '';

        if ($field === 'date_of_birth' && trim((string) $rawValue) !== '') {
            $timestamp = strtotime((string) $rawValue);

            return $timestamp === false ? '' : date('Y-m-d', $timestamp);
        }

        return trim((string) $rawValue);
    }
}

if (! function_exists('familySelected')) {
    function familySelected(array $values, string $field, string $optionValue): string
    {
        return familyPersonValue($values, $field) === $optionValue ? ' selected' : '';
    }
}

if (! function_exists('familyChecked')) {
    function familyChecked(array $selectedIds, string $id): string
    {
        return in_array((int) $id, array_map('intval', $selectedIds), true) ? ' checked' : '';
    }
}

if (! function_exists('familySelectOptions')) {
    function familySelectOptions(string $listName): array
    {
        $options = [
            'suffix' => ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'],
            'sex' => ['Male', 'Female'],
            'civil_status' => ['Single', 'Married', 'Widowed', 'Separated', 'Live-in', 'Others'],
            'religion' => [
                'Roman Catholic',
                'Iglesia ni Cristo',
                'Islam',
                'Born Again Christian',
                'Protestant',
                'Seventh-day Adventist',
                'Iglesia Filipina Independiente',
                'Bible Baptist',
                "Jehovah's Witness",
                'Church of Christ',
                'Indigenous Beliefs',
                'No Religion',
                'Others',
            ],
            'education' => [
                'Elementary',
                'High School',
                'Undergraduate',
                'Vocational',
                'College Graduate',
                'Post Graduate',
                'Others',
            ],
            'job' => [
                'Unemployed',
                'Student',
                'Homemaker',
                'Self-employed',
                'Vendor',
                'Driver',
                'Construction Worker',
                'Factory Worker',
                'Office Staff',
                'Teacher',
                'Healthcare Worker',
                'Government Employee',
                'Private Employee',
                'OFW',
                'Retired',
                'Others',
            ],
            'monthly_income' => [
                'No regular income',
                'Below PHP 8,000',
                'PHP 8,000 - 13,000',
                'PHP 13,001 - 18,000',
                'PHP 18,001 - 25,000',
                'PHP 25,001 - 40,000',
                'PHP 40,001 - 65,000',
                'Above PHP 100,000',
            ],
            'relationship' => [
                'Spouse',
                'Child',
                'Parent',
                'Sibling',
                'Grandparent',
                'Grandchild',
                'Relative',
                'Other',
            ],
            'barangay' => [
                'Binan (Poblacion)',
                'Bungahan',
                'Santo Tomas (Calabuso)',
                'Canlalay',
                'Casile',
                'De La Paz',
                'Ganado',
                'San Francisco (Halang)',
                'Langkiwa',
                'Loma',
                'Malaban',
                'Malamig',
                'Mampalasan (Mamplasan)',
                'Platero',
                'Poblacion',
                'Santo Nino',
                'San Antonio',
                'San Jose',
                'San Vicente',
                'Soro-Soro',
                'Santo Domingo',
                'Timbao',
                'Tubigan',
                'Zapote',
            ],
        ];

        return $options[$listName] ?? [];
    }
}

if (! function_exists('familyBarangayOptions')) {
    function familyBarangayOptions(): array
    {
        return familySelectOptions('barangay');
    }
}
