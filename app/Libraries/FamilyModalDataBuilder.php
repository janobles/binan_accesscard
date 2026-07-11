<?php

namespace App\Libraries;

use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Lookups\ServiceModel;
use App\Support\MemberFieldNormalizer;

/**
 * Assembles view data for the family Add/Update modal and detail fragment:
 * head prefill values, existing-member rows, and the service/income label
 * maps. Pure assembly from rows the controller fetched — decisions (guards,
 * which record, which view) stay in FamilyController.
 */
class FamilyModalDataBuilder
{
    /**
     * Builds the head prefill block (formValues + selected sector/service IDs) for
     * the Update modal. Splits the stored "address, barangay" back into the two
     * separate inputs via MemberFieldNormalizer::splitAddressBarangay().
     *
     * @param list<int> $headServiceIds
     */
    public function updateData(array $head, array $headServiceIds): array
    {
        $headId = (int) ($head['memberID'] ?? 0);
        $addressParts = MemberFieldNormalizer::splitAddressBarangay($head['address'] ?? '');

        return [
            'headId' => $headId,
            'formValues' => [
                'head_lastname' => (string) ($head['lastname'] ?? ''),
                'head_firstname' => (string) ($head['firstname'] ?? ''),
                'head_middlename' => (string) ($head['middlename'] ?? ''),
                'head_suffix' => (string) ($head['suffix'] ?? ''),
                'head_birthday' => (string) ($head['birthday'] ?? ''),
                'head_sex' => (string) ($head['sex'] ?? ''),
                'head_civilstatus' => (string) ($head['civilstatus'] ?? ''),
                'head_contactnumber' => (string) ($head['contactnumber'] ?? ''),
                'head_religion' => (string) ($head['religion'] ?? ''),
                'head_education' => (string) ($head['education'] ?? ''),
                'head_job' => (string) ($head['job'] ?? ''),
                'head_salary' => (string) ($head['Salary'] ?? ''),
                'head_address' => $addressParts['address'],
                'head_barangay' => $addressParts['barangay'],
                'qr_control_no' => (string) (model(\App\Models\Scanner\QrControlModel::class)->controlForHead($headId) ?? ''),
            ],
            'selectedSectorIds' => array_map('strval', SectorIds::normalize($head['sectorID'] ?? null)),
            'selectedServiceIds' => array_map('strval', $headServiceIds),
        ];
    }

    /**
     * Shapes existing family-member rows for the Update modal so they pre-render
     * (and re-post) — otherwise update()'s member replace would drop them.
     *
     * @param array<int, list<int>> $serviceIdsByMember
     * @return list<array<string, mixed>>
     */
    public function shapeMembers(array $members, array $serviceIdsByMember): array
    {
        return array_map(function (array $member) use ($serviceIdsByMember): array {
            $memberId = (int) ($member['memberID'] ?? 0);

            return [
                'lastname' => (string) ($member['lastname'] ?? ''),
                'firstname' => (string) ($member['firstname'] ?? ''),
                'middlename' => (string) ($member['middlename'] ?? ''),
                'suffix' => (string) ($member['suffix'] ?? ''),
                'birthday' => (string) ($member['birthday'] ?? ''),
                'sex' => (string) ($member['sex'] ?? ''),
                'civilstatus' => (string) ($member['civilstatus'] ?? ''),
                'contactnumber' => (string) ($member['contactnumber'] ?? ''),
                'religion' => (string) ($member['religion'] ?? ''),
                'education' => (string) ($member['education'] ?? ''),
                'job' => (string) ($member['job'] ?? ''),
                'salary' => (string) ($member['Salary'] ?? ''),
                'relationship' => (string) ($member['relationship'] ?? ''),
                'sector_ids' => array_map('strval', SectorIds::normalize($member['sectorID'] ?? null)),
                'service_ids' => array_map('strval', $serviceIdsByMember[$memberId] ?? []),
            ];
        }, $members);
    }

    /** Resolves [serviceID => name] across every service assigned to the family. */
    public function serviceNameMap(array $serviceIdsByMember): array
    {
        $allServiceIds = [];

        foreach ($serviceIdsByMember as $ids) {
            foreach ($ids as $id) {
                $allServiceIds[] = (int) $id;
            }
        }

        if ($allServiceIds === []) {
            return [];
        }

        return (new ServiceModel())->getNameMapByIds(array_values(array_unique($allServiceIds)));
    }

    /** Builds an [income bracket value => label] map for the family detail view. */
    public function incomeLabelMap(): array
    {
        $map = [];

        foreach ((new FamilyFormOptionsModel())->getOptions()['income_ranges'] ?? [] as $range) {
            $value = (string) ($range['value'] ?? '');

            if ($value === '') {
                continue;
            }

            $map[$value] = (string) ($range['label'] ?? $value);
        }

        return $map;
    }
}
