<?php

if (! function_exists('family_list_format_date')) {
    function family_list_format_date(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '' : date('Y-m-d', $timestamp);
    }
}

if (! function_exists('family_list_format_time')) {
    function family_list_format_time(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '' : date('h:i A', $timestamp);
    }
}

if (! function_exists('family_list_url')) {
    function family_list_url(string $listRoute, string $keyword, string $sectorId, string $date, string $status, int $page = 1): string
    {
        $params = ['page' => $page];

        if ($status === 'archived') {
            $params['status'] = 'archived';
        }

        if (trim($keyword) !== '') {
            $params['q'] = $keyword;
        }

        if (trim($sectorId) !== '') {
            $params['sectorID'] = $sectorId;
        }

        if (trim($date) !== '') {
            $params['date'] = $date;
        }

        return site_url($listRoute . '?' . http_build_query($params));
    }
}

if (! function_exists('family_list_deep_url')) {
    function family_list_deep_url(string $listRoute, string $keyword, string $sectorId, string $date, string $status, int $page): string
    {
        $params = ['deep_q' => $keyword, 'deep_page' => $page];

        if ($status === 'archived') {
            $params['status'] = 'archived';
        }

        if (trim($sectorId) !== '') {
            $params['sectorID'] = $sectorId;
        }

        if (trim($date) !== '') {
            $params['date'] = $date;
        }

        return site_url($listRoute . '?' . http_build_query($params));
    }
}

if (! function_exists('family_list_partial_url')) {
    function family_list_partial_url(string $url): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'partial=1';
    }
}
