<?php

namespace App\Support;

/**
 * Canonical labels from CSWD Family Profiling Form v2.
 */
class FamilyProfilingFormV2
{
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
        'SBS' => 'Small Business Sector',
        'OTHER' => 'Other Sectors',
    ];

    public const SERVICE_CATEGORIES = [
        'Financial Assistance Programs',
        'Social Welfare Programs and Services',
        'Emergency / Disaster Assistance Programs',
    ];

    // The methods below return fixed option lists/seed data straight from the CSWD
    // Family Profiling Form v2. They have no DB/session dependency and feed the
    // family form dropdowns (via FamilyFormOptionsModel) and lookup seeding.

    /** Name-suffix options (Jr, Sr, I–V) for the family form. */
    public static function suffixes(): array
    {
        return ['Jr', 'Sr', 'I', 'II', 'III', 'IV', 'V'];
    }

    /** Biñan barangay options for the family form address. */
    public static function barangays(): array
    {
        return [
            'Binan (Poblacion)', 'Bungahan', 'Santo Tomas (Calabuso)', 'Canlalay',
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

    /** Canonical seed rows for the `sector` lookup table (used to seed/reference defaults). */
    public static function sectorRows(): array
    {
        return [
            ['sectorID' => 1, 'shortcode' => 'PWD1', 'name' => 'Registered PWD in Binan', 'description' => 'Official city registration for PWD'],
            ['sectorID' => 2, 'shortcode' => 'PWD2', 'name' => 'Binan City Development Center', 'description' => 'Member of the local development center'],
            ['sectorID' => 3, 'shortcode' => 'PWD3', 'name' => 'Birthday Cash Gift', 'description' => 'Annual cash gift benefit'],
            ['sectorID' => 4, 'shortcode' => 'PWD4', 'name' => 'Project Aruga', 'description' => 'Local social welfare project'],
            ['sectorID' => 5, 'shortcode' => 'PWD5', 'name' => 'Subsidy for Unemployable PWD', 'description' => 'Financial aid for those unable to work'],
            ['sectorID' => 6, 'shortcode' => 'SP1', 'name' => 'Registered Solo Parent in Binan', 'description' => 'Official city registration for solo parents'],
            ['sectorID' => 7, 'shortcode' => 'SP2', 'name' => 'Monthly Subsidy for Solo Parent', 'description' => 'Regular monthly financial support'],
            ['sectorID' => 8, 'shortcode' => 'SC1', 'name' => 'Registered OSCA Binan', 'description' => 'Official city registration for senior citizens'],
            ['sectorID' => 9, 'shortcode' => 'SC2', 'name' => 'Local Pensioner', 'description' => 'Receiving local government pension'],
            ['sectorID' => 10, 'shortcode' => 'SC3', 'name' => 'National Pensioner', 'description' => 'Receiving national government pension'],
            ['sectorID' => 11, 'shortcode' => 'SC4', 'name' => 'Centenarian Local Awardee', 'description' => 'Local recognition for reaching 100'],
            ['sectorID' => 12, 'shortcode' => 'SC5', 'name' => 'Centenarian National Awardee', 'description' => 'National recognition for reaching 100'],
            ['sectorID' => 13, 'shortcode' => 'SC6', 'name' => 'Centenarian Province Awardee', 'description' => 'Provincial recognition for reaching 100'],
            ['sectorID' => 14, 'shortcode' => 'SC7', 'name' => 'Eyeglasses Assistance', 'description' => 'Vision assistance for senior citizens'],
            ['sectorID' => 15, 'shortcode' => 'SC8', 'name' => 'One Time Cash Incentive (85yrs old)', 'description' => 'Special 85th birthday incentive'],
            ['sectorID' => 16, 'shortcode' => 'SC9', 'name' => 'Wheelchair / Crutches', 'description' => 'Provision of mobility aids'],
            ['sectorID' => 17, 'shortcode' => 'B1', 'name' => 'Bahay Pag-Asa', 'description' => 'Bahay Pag-Asa program'],
            ['sectorID' => 18, 'shortcode' => 'B2', 'name' => 'ECCD', 'description' => 'Early Childhood Care and Development'],
            ['sectorID' => 19, 'shortcode' => 'B3', 'name' => 'Supplementary Feeding Program', 'description' => 'Nutritional support for children'],
            ['sectorID' => 20, 'shortcode' => 'LGBT', 'name' => 'LGBTQIA+', 'description' => 'LGBTQIA+ sector'],
            ['sectorID' => 21, 'shortcode' => 'OFW', 'name' => 'Overseas Filipino Worker', 'description' => 'Overseas Filipino Worker sector'],
            ['sectorID' => 22, 'shortcode' => 'IP', 'name' => 'Indigenous People', 'description' => 'Indigenous People sector'],
            ['sectorID' => 23, 'shortcode' => 'IDP', 'name' => 'Internally Displaced Person', 'description' => 'Internally Displaced Person sector'],
            ['sectorID' => 24, 'shortcode' => 'PDL', 'name' => 'Persons Deprived of Liberty', 'description' => 'Persons Deprived of Liberty sector'],
        ];
    }

    /** Canonical seed rows for the `services` lookup table (assistance programs). */
    public static function serviceRows(): array
    {
        return [
            ['category' => 'Financial Assistance Programs', 'code' => 'FA1', 'name' => 'Balik Probinsya', 'description' => 'Balik Probinsya'],
            ['category' => 'Financial Assistance Programs', 'code' => 'FA2', 'name' => 'Burial Assistance', 'description' => 'Burial Assistance'],
            ['category' => 'Financial Assistance Programs', 'code' => 'FA3', 'name' => 'Dental Assistance', 'description' => 'Dental Assistance'],
            ['category' => 'Financial Assistance Programs', 'code' => 'FA4', 'name' => 'Eyeglasses Assistance', 'description' => 'Eyeglasses Assistance'],
            ['category' => 'Financial Assistance Programs', 'code' => 'FA5', 'name' => 'Lingap sa Mahirap', 'description' => 'Lingap sa Mahirap'],
            ['category' => 'Financial Assistance Programs', 'code' => 'FA6', 'name' => 'Medical Assistance', 'description' => 'Medical Assistance'],
            ['category' => 'Social Welfare Programs and Services', 'code' => '4PS', 'name' => '4Ps (Pantawid Pamilyang Pilipino Programs)', 'description' => '4Ps beneficiary'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS1', 'name' => 'Balay Silangan', 'description' => 'Balay Silangan program beneficiary'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS2', 'name' => 'Business Skills Management Training', 'description' => 'Business skills management training'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS3', 'name' => 'Counseling / Dialogue', 'description' => 'Counseling or dialogue service'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS4', 'name' => 'Family Development Session', 'description' => 'Family Development Session'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS5', 'name' => 'Gender Sensitivity Training', 'description' => 'Gender sensitivity training'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS6', 'name' => 'Legal Assistance / Free Notary', 'description' => 'Legal assistance or free notary service'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS7', 'name' => 'Licensed Foster Parent', 'description' => 'Licensed foster parent service'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS8', 'name' => 'Pamaskong Handog', 'description' => 'Pamaskong Handog beneficiary'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS9', 'name' => 'Parent Effectiveness Service', 'description' => 'Parent effectiveness service'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS10', 'name' => 'PMOC (Pre-Marriage Orientation / Counseling)', 'description' => 'Pre-Marriage Orientation / Counseling'],
            ['category' => 'Social Welfare Programs and Services', 'code' => 'SWPS11', 'name' => 'Referral', 'description' => 'Referral service'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA1', 'name' => 'Cash Assistance', 'description' => 'Cash Assistance'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA2', 'name' => 'Cash for Work', 'description' => 'Cash for Work'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA3', 'name' => 'Emergency Shelter (Local)', 'description' => 'Emergency Shelter Local'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA4', 'name' => 'Emergency Shelter (National / NHA)', 'description' => 'Emergency Shelter National / NHA'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA5', 'name' => 'Emergency Shelter (Province)', 'description' => 'Emergency Shelter Province'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA6', 'name' => 'Food for Work', 'description' => 'Food for Work'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA7', 'name' => 'Non-Food Assistance', 'description' => 'Non-Food Assistance'],
            ['category' => 'Emergency / Disaster Assistance Programs', 'code' => 'EDA8', 'name' => 'Temporary Shelter', 'description' => 'Temporary Shelter'],
        ];
    }
}
