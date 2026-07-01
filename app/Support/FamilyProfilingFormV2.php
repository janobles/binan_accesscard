<?php

namespace App\Support;

/**
 * Canonical labels from CSWD Family Profiling Form v2.
 */
class FamilyProfilingFormV2
{
    /** The sectors (a person's classification) — code => display name. */
    public const SECTOR_CATEGORIES = [
        'SC' => 'Senior Citizen',
        'PWD' => 'Person with Disability',
        'SP' => 'Solo Parent',
        'B' => 'Bata (Children)',
        'LGBT' => 'LGBTQIA+',
        'OFW' => 'Overseas Filipino Worker',
        'IP' => 'Indigenous People',
        'IDP' => 'Internally Displaced Person',
        'PDL' => 'Persons Deprived of Liberty',
        'OTHER' => 'Other Sectors',
    ];

    /** The service categories (programs a person received are grouped by these). */
    public const SERVICE_CATEGORIES = [
        'Senior Citizen',
        'Person with Disability',
        'Solo Parent',
        'Bata (Children)',
        'Financial Assistance Programs',
        'Social Welfare Programs and Services',
        'Emergency / Disaster Assistance Programs',
    ];

    // The methods below return fixed option lists straight from the CSWD Family
    // Profiling Form v2. They have no DB/session dependency and feed the family
    // form dropdowns (via FamilyFormOptionsModel).

    /** Name-suffix options (Jr, Sr, I–V) for the family form. */
    public static function suffixes(): array
    {
        return ['Jr', 'Sr', 'I', 'II', 'III', 'IV', 'V'];
    }

    /** Biñan barangay options for the family form address. */
    public static function barangays(): array
    {
        return [
            'Binan', 'Bungahan', 'Santo Tomas (Calabuso)', 'Canlalay',
            'Casile', 'De La Paz', 'Ganado', 'San Francisco (Halang)', 'Langkiwa',
            'Loma', 'Malaban', 'Malamig', 'Mamplasan', 'Platero',
            'Poblacion', 'Santo Nino', 'San Antonio', 'San Jose', 'San Vicente',
            'Soro-Soro', 'Santo Domingo', 'Timbao', 'Tubigan', 'Zapote',
        ];
    }

    /** Civil-status options for the family form. */
    public static function civilStatuses(): array
    {
        return [
            'Single',
            'Married',
            'Widow / Widower',
            'Separated',
            'Live-in / Not Married',
            'Others',
        ];
    }

    /** Educational-attainment options for the family form. */
    public static function educationLevels(): array
    {
        return [
            'Elementary',
            'High School',
            'Undergraduate',
            'Vocational',
            'College Graduate',
            'Post Graduate',
            'Others',
        ];
    }

    /** Occupation options for the family form. */
    public static function jobOptions(): array
    {
        return [
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
        ];
    }

    /** Religion options for the family form. */
    public static function religions(): array
    {
        return [
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
        ];
    }
}
