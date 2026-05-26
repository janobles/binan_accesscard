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
        'B' => 'Children',
        'LGBT' => 'LGBTQIA+',
        'OFW' => 'Overseas Filipino Worker',
        'IP' => 'Indigenous People',
        'IDP' => 'Internally Displaced Person',
        'PDL' => 'Persons Deprived of Liberty',
        'OTHER' => 'Other Sectors',
    ];

    public const SERVICE_CATEGORIES = [
        'Senior Citizen',
        'Person with Disability',
        'Solo Parent',
        'Children',
        'Financial Assistance',
        'Social Welfare Programs and Services',
        'Emergency and Disaster Assistance',
    ];

    public static function suffixes(): array
    {
        return ['Jr', 'Sr', 'I', 'II', 'III', 'IV', 'V'];
    }

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

    public static function sectorRows(): array
    {
        return [
            ['sectorID' => 1, 'shortcode' => 'SC', 'name' => 'Senior Citizen', 'description' => 'Older persons and senior citizen beneficiaries'],
            ['sectorID' => 2, 'shortcode' => 'PWD', 'name' => 'Person with Disability', 'description' => 'Persons with Disability'],
            ['sectorID' => 3, 'shortcode' => 'SP', 'name' => 'Solo Parent', 'description' => 'Registered solo parent households'],
            ['sectorID' => 4, 'shortcode' => 'B', 'name' => 'Children', 'description' => 'Children and youth beneficiaries'],
            ['sectorID' => 5, 'shortcode' => 'LGBT', 'name' => 'LGBTQIA+', 'description' => 'LGBTQIA+ community members'],
            ['sectorID' => 6, 'shortcode' => 'OFW', 'name' => 'Overseas Filipino Worker', 'description' => 'OFW household members'],
            ['sectorID' => 7, 'shortcode' => 'IP', 'name' => 'Indigenous People', 'description' => 'Indigenous people or cultural community members'],
            ['sectorID' => 8, 'shortcode' => 'IDP', 'name' => 'Internally Displaced Person', 'description' => 'Internally displaced persons'],
            ['sectorID' => 9, 'shortcode' => 'PDL', 'name' => 'Persons Deprived of Liberty', 'description' => 'Persons deprived of liberty'],
        ];
    }

    public static function serviceRows(): array
    {
        return [
            ['category' => 'Senior Citizen', 'code' => 'SC1', 'name' => 'Registered OSCA Binan', 'description' => 'Official senior citizen registration in Binan'],
            ['category' => 'Senior Citizen', 'code' => 'SC2', 'name' => 'Local Pensioner', 'description' => 'Local senior citizen pension beneficiary'],
            ['category' => 'Senior Citizen', 'code' => 'SC3', 'name' => 'National Pensioner', 'description' => 'National senior citizen pension beneficiary'],
            ['category' => 'Senior Citizen', 'code' => 'SC4', 'name' => 'Centenarian Local Awardee', 'description' => 'Local centenarian award beneficiary'],
            ['category' => 'Senior Citizen', 'code' => 'SC5', 'name' => 'Centenarian National Awardee', 'description' => 'National centenarian award beneficiary'],
            ['category' => 'Senior Citizen', 'code' => 'SC6', 'name' => 'Centenarian Province Awardee', 'description' => 'Provincial centenarian award beneficiary'],
            ['category' => 'Senior Citizen', 'code' => 'SC7', 'name' => 'Eyeglasses Assistance', 'description' => 'Vision assistance for senior citizens'],
            ['category' => 'Senior Citizen', 'code' => 'SC8', 'name' => 'One Time Cash Incentive (85 years old)', 'description' => 'Special cash incentive for 85-year-old beneficiaries'],
            ['category' => 'Senior Citizen', 'code' => 'SC9', 'name' => 'Wheelchair / Crutches', 'description' => 'Mobility aid assistance'],
            ['category' => 'Person with Disability', 'code' => 'PWD1', 'name' => 'Registered PWD in Binan', 'description' => 'Official PWD registration in Binan'],
            ['category' => 'Person with Disability', 'code' => 'PWD2', 'name' => 'Binan City Development Center', 'description' => 'Beneficiary of Binan City Development Center services'],
            ['category' => 'Person with Disability', 'code' => 'PWD3', 'name' => 'Birthday Cash Gift', 'description' => 'Birthday cash gift assistance'],
            ['category' => 'Person with Disability', 'code' => 'PWD4', 'name' => 'Project Aruga', 'description' => 'Project Aruga beneficiary'],
            ['category' => 'Person with Disability', 'code' => 'PWD5', 'name' => 'Subsidy for Unemployable PWD', 'description' => 'Subsidy assistance for unemployable PWD beneficiaries'],
            ['category' => 'Solo Parent', 'code' => 'SP1', 'name' => 'Registered Solo Parent in Binan', 'description' => 'Official solo parent registration in Binan'],
            ['category' => 'Solo Parent', 'code' => 'SP2', 'name' => 'Monthly Subsidy for Solo Parent', 'description' => 'Monthly subsidy assistance for solo parents'],
            ['category' => 'Children', 'code' => 'B1', 'name' => 'Bahay Pag-Asa', 'description' => 'Bahay Pag-Asa program beneficiary'],
            ['category' => 'Children', 'code' => 'B2', 'name' => 'ECCD', 'description' => 'Early Childhood Care and Development'],
            ['category' => 'Children', 'code' => 'B3', 'name' => 'Supplementary Feeding Program', 'description' => 'Supplementary feeding program beneficiary'],
            ['category' => 'Financial Assistance', 'code' => 'FA1', 'name' => 'Balik Probinsya', 'description' => 'Balik Probinsya assistance'],
            ['category' => 'Financial Assistance', 'code' => 'FA2', 'name' => 'Burial Assistance', 'description' => 'Burial assistance'],
            ['category' => 'Financial Assistance', 'code' => 'FA3', 'name' => 'Dental Assistance', 'description' => 'Dental assistance'],
            ['category' => 'Financial Assistance', 'code' => 'FA4', 'name' => 'Eyeglasses Assistance', 'description' => 'Eyeglasses assistance'],
            ['category' => 'Financial Assistance', 'code' => 'FA5', 'name' => 'Lingap sa Mahirap', 'description' => 'Lingap sa Mahirap assistance'],
            ['category' => 'Financial Assistance', 'code' => 'FA6', 'name' => 'Medical Assistance', 'description' => 'Medical assistance'],
            ['category' => 'Social Welfare Programs and Services', 'code' => '4PS', 'name' => 'Pantawid Pamilyang Pilipino Program', 'description' => '4Ps beneficiary'],
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
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA1', 'name' => 'Cash Assistance', 'description' => 'Emergency or disaster cash assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA2', 'name' => 'Cash for Work', 'description' => 'Cash for Work assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA3', 'name' => 'Emergency Shelter (Local)', 'description' => 'Local emergency shelter assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA4', 'name' => 'Emergency Shelter (National / NHA)', 'description' => 'National or NHA emergency shelter assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA5', 'name' => 'Emergency Shelter (Province)', 'description' => 'Provincial emergency shelter assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA6', 'name' => 'Food for Work', 'description' => 'Food for Work assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA7', 'name' => 'Non-Food Assistance', 'description' => 'Non-food emergency or disaster assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA8', 'name' => 'Relief Food Pack', 'description' => 'Relief food pack assistance'],
            ['category' => 'Emergency and Disaster Assistance', 'code' => 'EDA9', 'name' => 'Temporary Shelter', 'description' => 'Temporary shelter assistance'],
        ];
    }
}
