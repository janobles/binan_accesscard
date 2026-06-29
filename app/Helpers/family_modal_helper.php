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

        return [
            'action' => (string) ($data['action'] ?? site_url('families')),
            'fieldPrefix' => (string) ($data['fieldPrefix'] ?? 'family-add'),
            'modalTitle' => (string) ($data['modalTitle'] ?? 'New Family Record'),
            'modalMode' => (string) ($data['modalMode'] ?? 'create'),
            'submitLabel' => (string) ($data['submitLabel'] ?? 'Save Family Record'),
            'headId' => (int) ($data['headId'] ?? 0),
            'sectorOptions' => (array) ($data['sectorOptions'] ?? []),
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

if (! function_exists('family_modal_route_base')) {
    /**
     * Returns the role-aware Family modal route base used by reusable modal
     * triggers. Pass a role/context when rendering outside a family route.
     */
    function family_modal_route_base(?string $context = null): string
    {
        $context = strtolower(trim((string) ($context ?? uri_string())));

        if (str_starts_with($context, 'employee') || str_contains($context, '/employee/')) {
            return 'employee/manage-family';
        }

        if (str_starts_with($context, 'viewer') || str_contains($context, '/viewer/')) {
            return 'viewer/manage-family';
        }

        return 'admin/manage-family';
    }
}

if (! function_exists('family_modal_url')) {
    /**
     * Builds the reusable Family modal URL.
     *
     * @param array<string, mixed> $params Extra query params, e.g. ['mode' => 'update', 'id' => 12].
     */
    function family_modal_url(array $params = [], ?string $context = null): string
    {
        $params = array_merge(['partial' => '1'], $params);

        return site_url(family_modal_route_base($context) . '/create?' . http_build_query($params));
    }
}

if (! function_exists('family_modal_trigger_attrs')) {
    /**
     * Builds escaped HTML attributes for any button/link that should open the
     * reusable Family modal through manage-family-modal.js.
     *
     * @param array<string, mixed> $params Extra URL query params.
     */
    function family_modal_trigger_attrs(string $title = 'New Family Record', array $params = [], ?string $context = null): string
    {
        return 'data-modal-url="' . esc(family_modal_url($params, $context), 'attr') . '" data-modal-title="' . esc($title, 'attr') . '"';
    }
}

if (! function_exists('family_modal_button')) {
    /**
     * Renders a reusable button that opens family-modal.php.
     *
     * @param array<string, mixed> $params Extra URL query params.
     */
    function family_modal_button(string $label = 'Add', string $title = 'New Family Record', array $params = [], string $class = 'btn btn-primary', ?string $context = null): string
    {
        return '<button class="' . esc(trim($class . ' js-open-family-add-modal'), 'attr') . '" type="button" '
            . family_modal_trigger_attrs($title, $params, $context) . '>' . esc($label) . '</button>';
    }
}
