<?php

/**
 * View helpers for the family records list template: date/time formatting plus
 * builders for the list, deep-search, and AJAX-partial URLs that the filter and
 * pagination links point at.
 */

if (! function_exists('family_list_format_date')) {
    /** Formats a value as Y-m-d for the list table ('' if unparseable). */
    function family_list_format_date(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '' : date('Y-m-d', $timestamp);
    }
}

if (! function_exists('family_list_format_time')) {
    /** Formats a value as 12-hour time for the list table ('' if unparseable). */
    function family_list_format_time(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? '' : date('h:i A', $timestamp);
    }
}

if (! function_exists('family_list_url')) {
    /**
     * Builds a records-list URL carrying the current keyword/sector/date/status
     * and page. Frontend: the href for filter and pagination links.
     */
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
    /**
     * Builds a deep ("search the whole database") URL using deep_q/deep_page plus
     * the active filters. Frontend: the href for deep-search pagination links.
     */
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
    /**
     * Appends `partial=1` to a list URL so the dashboard JS fetches just the list
     * fragment instead of a full page. Frontend: used by AJAX list refresh links.
     */
    function family_list_partial_url(string $url): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'partial=1';
    }
}
