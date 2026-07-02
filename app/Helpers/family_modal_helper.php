<?php

if (! function_exists('family_modal_prepare')) {
    /**
     * Prepares option lists, selected values, and formatter callbacks for the
     * Bootstrap Family Add modal view.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    function family_modal_prepare(array $data): array
    {
        $optionValue = static function (mixed $option): string {
            if (is_array($option)) {
                return (string) ($option['value'] ?? $option['id'] ?? $option['sectorID'] ?? $option['serviceID'] ?? $option['label'] ?? $option['name'] ?? '');
            }

            return (string) $option;
        };

        $optionLabel = static function (mixed $option): string {
            if (is_array($option)) {
                return (string) ($option['label'] ?? $option['name'] ?? $option['sector_name'] ?? $option['service_name'] ?? $option['value'] ?? '');
            }

            return (string) $option;
        };

        $formValues = (array) ($data['formValues'] ?? []);
        $defaultSectorIds = array_map('strval', (array) ($data['selectedSectorIds'] ?? []));
        $defaultServiceIds = array_map('strval', (array) ($data['selectedServiceIds'] ?? []));

        $oldArray = static function (string $key, array $default = []): array {
            $value = old($key, null);

            return is_array($value) ? array_map('strval', $value) : $default;
        };

        $selectOptions = static function (array $options, string $selected = '', string $placeholder = 'Select') use ($optionValue, $optionLabel): string {
            $html = '<option value="">' . esc($placeholder) . '</option>';

            foreach ($options as $option) {
                $value = $optionValue($option);
                $label = $optionLabel($option);
                $hasExplicitValue = is_array($option) && array_key_exists('value', $option);

                if ($value === '' && $label === '') {
                    continue;
                }

                if ($hasExplicitValue && $value === '' && strcasecmp($label, $placeholder) === 0) {
                    continue;
                }

                $value = $value !== '' || $hasExplicitValue ? $value : $label;
                $label = $label !== '' ? $label : $value;
                $html .= '<option value="' . esc($value, 'attr') . '"' . ($selected === $value ? ' selected' : '') . '>' . esc($label) . '</option>';
            }

            return $html;
        };

        $personFields = [
            ['name' => 'lastname', 'label' => 'Last Name', 'type' => 'text', 'idSuffix' => 'Lastname', 'summary' => 'name-last', 'required' => true],
            ['name' => 'firstname', 'label' => 'First Name', 'type' => 'text', 'idSuffix' => 'Firstname', 'summary' => 'name-first', 'required' => true],
            ['name' => 'middlename', 'label' => 'Middle Name', 'type' => 'text', 'idSuffix' => 'Middlename', 'summary' => 'name-middle'],
            ['name' => 'suffix', 'label' => 'Suffix', 'type' => 'select', 'options' => 'suffixOptions', 'idSuffix' => 'Suffix', 'summary' => 'name-suffix'],
            ['name' => 'birthday', 'label' => 'Date of birth', 'type' => 'date', 'idSuffix' => 'Birthday', 'summary' => 'birthday', 'required' => true],
            ['name' => 'sex', 'label' => 'Sex', 'type' => 'select', 'options' => 'sexOptions', 'idSuffix' => 'Sex', 'summary' => 'sex', 'required' => true],
            ['name' => 'civilstatus', 'label' => 'Civil status', 'type' => 'select', 'options' => 'civilOptions', 'other' => true, 'idSuffix' => 'CivilStatus', 'summary' => 'civil', 'required' => true],
            ['name' => 'contactnumber', 'label' => 'Contact number', 'type' => 'tel', 'maxlength' => '30', 'idSuffix' => 'Contact', 'summary' => 'contact'],
            ['name' => 'religion', 'label' => 'Religion', 'type' => 'select', 'options' => 'religionOptions', 'other' => true, 'idSuffix' => 'Religion', 'summary' => 'religion'],
            ['name' => 'education', 'label' => 'Education', 'type' => 'select', 'options' => 'educationOptions', 'other' => true, 'idSuffix' => 'Education', 'summary' => 'education', 'required' => true],
            ['name' => 'job', 'label' => 'Job', 'type' => 'select', 'options' => 'jobOptions', 'other' => true, 'idSuffix' => 'Job', 'summary' => 'job', 'required' => true],
            ['name' => 'salary', 'label' => 'Monthly income', 'type' => 'select', 'options' => 'incomeOptions', 'idSuffix' => 'Salary', 'summary' => 'income', 'required' => true],
        ];

        return [
            'action' => (string) ($data['action'] ?? site_url('families')),
            'fieldPrefix' => (string) ($data['fieldPrefix'] ?? 'family-add'),
            'modalTitle' => (string) ($data['modalTitle'] ?? 'New Family Record'),
            'modalMode' => (string) ($data['modalMode'] ?? 'create'),
            'submitLabel' => (string) ($data['submitLabel'] ?? 'Save Family Record'),
            'headId' => (int) ($data['headId'] ?? 0),
            'sectorOptions' => (array) ($data['sectorOptions'] ?? []),
            // Sectors grouped by category (code => rows[]) for the grouped checkbox
            // headings, mirroring servicesByCategory. Already includes archived-but-
            // assigned sectors (flagged) in update mode, so it is safe to render from.
            'sectorCatalog' => (array) ($data['sectorCatalog'] ?? []),
            'suffixOptions' => (array) ($data['suffixOptions'] ?? []),
            'sexOptions' => (array) ($data['sexOptions'] ?? ['Male', 'Female']),
            'civilOptions' => (array) ($data['civilOptions'] ?? []),
            'barangayOptions' => (array) ($data['barangayOptions'] ?? []),
            'relationshipOptions' => (array) ($data['relationshipOptions'] ?? []),
            'educationOptions' => (array) ($data['educationOptions'] ?? []),
            'jobOptions' => (array) ($data['jobOptions'] ?? []),
            'religionOptions' => (array) ($data['religionOptions'] ?? []),
            'incomeOptions' => (array) ($data['incomeOptions'] ?? []),
            'servicesByCategory' => (array) ($data['servicesByCategory'] ?? []),
            'saveDisabled' => (bool) ($data['saveDisabled'] ?? false),
            'selectedSectorIds' => $oldArray('sector_ids', $defaultSectorIds),
            'selectedServiceIds' => $oldArray('service_ids', $defaultServiceIds),
            'personFields' => $personFields,
            'oldValue' => static fn (string $key, string $default = ''): string => (string) old($key, (string) ($formValues[$key] ?? $default)),
            'selectOptions' => $selectOptions,
            'sectorLabel' => static function (array $sector): string {
                $shortcode = trim((string) ($sector['shortcode'] ?? $sector['code'] ?? ''));
                $name = trim((string) ($sector['sector_name'] ?? $sector['name'] ?? $sector['label'] ?? ''));

                return $shortcode !== '' && $name !== '' ? mb_strtoupper($shortcode, 'UTF-8') . ' - ' . $name : ($shortcode !== '' ? mb_strtoupper($shortcode, 'UTF-8') : $name);
            },
            'serviceLabel' => static function (array $service): string {
                $code = trim((string) ($service['code'] ?? $service['shortcode'] ?? ''));
                $name = trim((string) ($service['service_name'] ?? $service['name'] ?? $service['label'] ?? ''));
                $description = trim((string) ($service['description'] ?? ''));
                $label = $code !== '' && $name !== '' ? mb_strtoupper($code, 'UTF-8') . ' - ' . $name : ($code !== '' ? mb_strtoupper($code, 'UTF-8') : $name);

                return $description !== '' && $label !== '' ? $label . ' - ' . $description : $label;
            },
        ];
    }
}
