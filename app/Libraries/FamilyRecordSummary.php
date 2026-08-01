<?php

namespace App\Libraries;

/**
 * Shapes a family record into the flat label/value pairs the read-only Family
 * Profile page prints, the way a printed ticket states a field name above its
 * value. Built from the same head/member arrays the editable form receives
 * (FamilyModalDataBuilder::shapeMembers()), so the two pages never disagree
 * about what a record holds.
 *
 * Pure formatting: it resolves sector and service ids to names through the
 * option lists handed to the constructor and never queries anything itself.
 * Blank values come back as a dash so a printed profile has no empty gaps.
 */
class FamilyRecordSummary
{
    private const DASH = '-';

    /** Field key => printed label, in the order the profile prints them. */
    private const PERSON_FIELDS = [
        'birthday'      => 'Date of Birth',
        'sex'           => 'Sex',
        'civilstatus'   => 'Civil Status',
        'contactnumber' => 'Contact Number',
        'religion'      => 'Religion',
        'education'     => 'Education',
        'job'           => 'Job',
        'salary'        => 'Monthly Income',
    ];

    /** @var array<string, string> sectorID => "CODE - Name" */
    private array $sectorLabels;

    /** @var array<string, string> serviceID => "CODE - Name" */
    private array $serviceLabels;

    /**
     * @param list<array<string, mixed>> $sectorOptions  active sector rows (sectorID, shortcode, name)
     * @param list<array<string, mixed>> $serviceOptions active service rows (serviceID, code, name)
     * @param array<string, string>      $incomeLabels   bracket value => label, from FamilyModalDataBuilder::incomeLabelMap()
     */
    public function __construct(array $sectorOptions, array $serviceOptions, private array $incomeLabels = [])
    {
        $this->sectorLabels = $this->labelMap($sectorOptions, 'sectorID', ['shortcode', 'code'], ['name', 'sector_name']);
        $this->serviceLabels = $this->labelMap($serviceOptions, 'serviceID', ['code', 'shortcode'], ['name', 'service_name']);
    }

    /**
     * The head block: full name, address, and the personal fields, with the
     * control number printed first because it is how staff identify the record.
     *
     * @return array{name: string, fields: list<array{label: string, value: string}>, sectors: list<string>, services: list<string>}
     */
    public function head(array $head, int $controlNumber): array
    {
        $address = trim((string) ($head['address'] ?? ''));
        $barangay = trim((string) ($head['barangay'] ?? ''));

        $fields = [
            ['label' => 'Control Number', 'value' => $controlNumber > 0
                ? Qr\ControlNumber::format($controlNumber)
                : self::DASH],
        ];

        foreach ($this->personFields($head) as $field) {
            $fields[] = $field;
        }

        $fields[] = ['label' => 'Address', 'value' => $this->text($address)];
        $fields[] = ['label' => 'Barangay', 'value' => $this->text($barangay)];

        return [
            'name'     => $this->fullName($head),
            'fields'   => $fields,
            'sectors'  => $this->labels($head['sector_ids'] ?? [], $this->sectorLabels),
            'services' => $this->labels($head['service_ids'] ?? [], $this->serviceLabels),
        ];
    }

    /**
     * One block per household member, in the order they are stored.
     *
     * @param list<array<string, mixed>> $members
     * @return list<array{name: string, relationship: string, fields: list<array{label: string, value: string}>, sectors: list<string>, services: list<string>}>
     */
    public function members(array $members): array
    {
        return array_map(fn (array $member): array => [
            'name'         => $this->fullName($member),
            'relationship' => $this->text((string) ($member['relationship'] ?? '')),
            'fields'       => $this->personFields($member),
            'sectors'      => $this->labels($member['sector_ids'] ?? [], $this->sectorLabels),
            'services'     => $this->labels($member['service_ids'] ?? [], $this->serviceLabels),
        ], array_values($members));
    }

    /** "FIRSTNAME MIDDLENAME LASTNAME SUFFIX" as stored, blanks collapsed. */
    private function fullName(array $person): string
    {
        $parts = array_filter([
            trim((string) ($person['firstname'] ?? '')),
            trim((string) ($person['middlename'] ?? '')),
            trim((string) ($person['lastname'] ?? '')),
            trim((string) ($person['suffix'] ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return $parts === [] ? self::DASH : implode(' ', $parts);
    }

    /**
     * The shared personal fields for a head or a member. The date of birth prints
     * with the age beside it, which is what staff read the field for, and the
     * income prints its bracket label rather than the bare bracket value.
     *
     * @return list<array{label: string, value: string}>
     */
    private function personFields(array $person): array
    {
        $fields = [];

        foreach (self::PERSON_FIELDS as $key => $label) {
            $value = trim((string) ($person[$key] ?? ''));

            $fields[] = [
                'label' => $label,
                'value' => match ($key) {
                    'birthday' => $this->birthday($value),
                    'salary'   => $this->text((string) ($this->incomeLabels[$value] ?? $value)),
                    default    => $this->text($value),
                },
            ];
        }

        return $fields;
    }

    /** "12 March 1958 (67 years old)", or a dash when unparseable. */
    private function birthday(string $value): string
    {
        $timestamp = $value === '' ? false : strtotime($value);

        if ($timestamp === false) {
            return self::DASH;
        }

        $age = (new \DateTimeImmutable('@' . $timestamp))->diff(new \DateTimeImmutable('today'))->y;

        return date('j F Y', $timestamp) . ' (' . $age . ' years old)';
    }

    /**
     * Resolves selected ids to their printed labels, dropping ids the option list
     * no longer knows (an archived lookup row that has since been deleted).
     *
     * @param array<string, string> $labels
     * @return list<string>
     */
    private function labels(mixed $ids, array $labels): array
    {
        $resolved = [];

        foreach ((array) $ids as $id) {
            $key = (string) $id;

            if (isset($labels[$key])) {
                $resolved[] = $labels[$key];
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Builds an [id => "CODE - Name"] map from a lookup option list. $codeKeys and
     * $nameKeys are tried in order because sector and service rows spell those
     * columns differently.
     *
     * @param list<array<string, mixed>> $options
     * @param list<string>               $codeKeys
     * @param list<string>               $nameKeys
     * @return array<string, string>
     */
    private function labelMap(array $options, string $idKey, array $codeKeys, array $nameKeys): array
    {
        $map = [];

        foreach ($options as $option) {
            $option = (array) $option;
            $id = (string) ($option[$idKey] ?? $option['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $code = $this->firstValue($option, $codeKeys);
            $name = $this->firstValue($option, $nameKeys);

            if ($code === '' && $name === '') {
                continue;
            }

            $map[$id] = $code !== '' && $name !== ''
                ? mb_strtoupper($code) . ' - ' . $name
                : ($code !== '' ? mb_strtoupper($code) : $name);
        }

        return $map;
    }

    /** First non-empty value among $keys, trimmed. */
    private function firstValue(array $option, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($option[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** A blank value prints as a dash rather than an empty line. */
    private function text(string $value): string
    {
        return $value === '' ? self::DASH : $value;
    }
}
