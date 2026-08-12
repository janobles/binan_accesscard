<?php

namespace App\Support;

/**
 * Canonical labels from CSWD Family Profiling Form v2.
 */
class FamilyProfilingFormV2
{
    /** The sectors (a person's classification) - code => display name. */
    public const SECTOR_CATEGORIES = [
        'SC' => 'SENIOR CITIZEN',
        'PWD' => 'PERSON WITH DISABILITY',
        'SP' => 'SOLO PARENT',
        'B' => 'BATA (CHILDREN)',
        'LGBT' => 'LGBTQIA+',
        'OFW' => 'OVERSEAS FILIPINO WORKER',
        'IP' => 'INDIGENOUS PEOPLE',
        'IDP' => 'INTERNALLY DISPLACED PERSON',
        'PDL' => 'PERSONS DEPRIVED OF LIBERTY',
        'OTHER' => 'OTHER SECTORS',
    ];

    /** The service categories (programs a person received are grouped by these). */
    public const SERVICE_CATEGORIES = [
        'SENIOR CITIZEN',
        'PERSON WITH DISABILITY',
        'SOLO PARENT',
        'BATA (CHILDREN)',
        'FINANCIAL ASSISTANCE PROGRAMS',
        'SOCIAL WELFARE PROGRAMS AND SERVICES',
        'EMERGENCY / DISASTER ASSISTANCE PROGRAMS',
    ];

    // The methods below return fixed option lists straight from the CSWD Family
    // Profiling Form v2. They have no DB/session dependency and feed the family
    // form dropdowns (via FamilyFormOptionsModel).

    /** Name-suffix options (Jr, Sr, I-V) for the family form. */
    public static function suffixes(): array
    {
        return ['JR', 'SR', 'I', 'II', 'III', 'IV', 'V'];
    }

    /** Civil-status options for the family form. */
    public static function civilStatuses(): array
    {
        return [
            'SINGLE',
            'MARRIED',
            'WIDOW / WIDOWER',
            'SEPARATED',
            'LIVE-IN / NOT MARRIED',
            'OTHERS',
        ];
    }

    /** Educational-attainment options for the family form. */
    public static function educationLevels(): array
    {
        return [
            'ELEMENTARY',
            'HIGH SCHOOL',
            'UNDERGRADUATE',
            'VOCATIONAL',
            'COLLEGE GRADUATE',
            'POST GRADUATE',
            'OTHERS',
        ];
    }

    /** Occupation options for the family form. */
    public static function jobOptions(): array
    {
        return [
            'UNEMPLOYED',
            'STUDENT',
            'HOMEMAKER',
            'SELF-EMPLOYED',
            'VENDOR',
            'DRIVER',
            'CONSTRUCTION WORKER',
            'FACTORY WORKER',
            'OFFICE STAFF',
            'TEACHER',
            'HEALTHCARE WORKER',
            'GOVERNMENT EMPLOYEE',
            'PRIVATE EMPLOYEE',
            'OFW',
            'RETIRED',
            'OTHERS',
        ];
    }

    /** Religion options for the family form. */
    public static function religions(): array
    {
        return [
            'ROMAN CATHOLIC',
            'IGLESIA NI CRISTO',
            'ISLAM',
            'BORN AGAIN CHRISTIAN',
            'PROTESTANT',
            'SEVENTH-DAY ADVENTIST',
            'IGLESIA FILIPINA INDEPENDIENTE',
            'BIBLE BAPTIST',
            "JEHOVAH'S WITNESS",
            'CHURCH OF CHRIST',
            'INDIGENOUS BELIEFS',
            'NO RELIGION',
            'OTHERS',
        ];
    }
}
