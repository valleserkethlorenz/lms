<?php
/**
 * Report filter helpers
 * =====================
 * Parsing, validation and display helpers for the organizer reporting module.
 *
 * This file is intentionally free of database access so every rule can be
 * unit-tested. buildReportQuery() calls these helpers before touching the DB.
 */

if (!function_exists('reportTypeTitles')) {
    /**
     * Report type keys mapped to the human title used in tables and exports.
     *
     * @return array<string,string>
     */
    function reportTypeTitles(): array
    {
        return [
            'registrations'  => 'Team Registrations',
            'approved_teams' => 'Approved Teams',
            'rosters'        => 'Team Rosters',
            'schedules'      => 'Published Match Schedules',
            'results'        => 'Official Match Results',
            'standings'      => 'League Standings',
            'participation'  => 'Participation',
            'summary'        => 'League Summary',
        ];
    }
}

if (!function_exists('reportDateFilterTypes')) {
    /**
     * Report types that genuinely apply a From/To date range.
     *
     * Standings and participation do not have a single unambiguous date column,
     * so they reject date filters instead of silently ignoring them.
     *
     * @return string[]
     */
    function reportDateFilterTypes(): array
    {
        return ['registrations', 'approved_teams', 'rosters', 'schedules', 'results', 'summary'];
    }
}

if (!function_exists('reportAllowedLeagueStatuses')) {
    /**
     * Values accepted by the league-status filter. The empty value means
     * "all statuses".
     *
     * @return string[]
     */
    function reportAllowedLeagueStatuses(): array
    {
        return ['', 'Draft', 'Upcoming', 'Open', 'Ongoing', 'Completed'];
    }
}

if (!function_exists('reportScalarFilter')) {
    /**
     * Read one scalar filter value from a request array. Array/object values
     * (for example ?report_type[]=x) are rejected instead of being cast with a
     * PHP warning.
     */
    function reportScalarFilter(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? $default;
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException('Invalid ' . str_replace('_', ' ', $key) . ' value.');
        }
        return trim((string) $value);
    }
}

if (!function_exists('reportFiltersFromRequest')) {
    /**
     * Normalise the report filters carried by $_GET (or an explicit array).
     *
     * @return array{report_type:string,league_id:int,sport:string,division:string,category:string,league_status:string,from_date:string,to_date:string}
     */
    function reportFiltersFromRequest(?array $source = null): array
    {
        $source = $source ?? $_GET;

        $leagueRaw = $source['league_id'] ?? '';
        if (is_array($leagueRaw) || is_object($leagueRaw)) {
            throw new InvalidArgumentException('Invalid league selected.');
        }
        $leagueRaw = trim((string) $leagueRaw);
        $leagueId  = 0;
        if ($leagueRaw !== '') {
            $validated = filter_var($leagueRaw, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);
            if ($validated === false) {
                throw new InvalidArgumentException('Invalid league selected.');
            }
            $leagueId = (int) $validated;
        }

        return [
            'report_type'   => strtolower(reportScalarFilter($source, 'report_type', 'registrations')),
            'league_id'     => $leagueId,
            'sport'         => reportScalarFilter($source, 'sport'),
            'division'      => reportScalarFilter($source, 'division'),
            'category'      => reportScalarFilter($source, 'category'),
            'league_status' => reportScalarFilter($source, 'league_status'),
            'from_date'     => reportScalarFilter($source, 'from_date'),
            'to_date'       => reportScalarFilter($source, 'to_date'),
        ];
    }
}

if (!function_exists('reportValidateDate')) {
    /** Reject anything that is not a real ISO date (YYYY-MM-DD). */
    function reportValidateDate(string $value, string $label): void
    {
        if ($value === '') {
            return;
        }
        $parsed = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }
    }
}

if (!function_exists('validateReportFilters')) {
    /**
     * Validate the normalised filter set. Pure: no DB access.
     *
     * @throws InvalidArgumentException
     */
    function validateReportFilters(array $filters): void
    {
        $type = (string) ($filters['report_type'] ?? '');
        if (!array_key_exists($type, reportTypeTitles())) {
            throw new InvalidArgumentException('Invalid report type.');
        }

        $leagueStatus = (string) ($filters['league_status'] ?? '');
        if (!in_array($leagueStatus, reportAllowedLeagueStatuses(), true)) {
            throw new InvalidArgumentException('Invalid league status.');
        }

        if ((int) ($filters['league_id'] ?? 0) < 0) {
            throw new InvalidArgumentException('Invalid league selected.');
        }

        foreach (['sport' => 'Sport', 'division' => 'Division', 'category' => 'Category'] as $key => $label) {
            if (strlen((string) ($filters[$key] ?? '')) > 160) {
                throw new InvalidArgumentException($label . ' filter is too long.');
            }
        }

        $from = (string) ($filters['from_date'] ?? '');
        $to   = (string) ($filters['to_date'] ?? '');
        reportValidateDate($from, 'From date');
        reportValidateDate($to, 'To date');

        if ($from !== '' && $to !== '' && $from > $to) {
            throw new InvalidArgumentException('From date cannot be later than To date.');
        }

        if (($from !== '' || $to !== '') && !in_array($type, reportDateFilterTypes(), true)) {
            throw new InvalidArgumentException('Date filters are not available for this report type.');
        }
    }
}

if (!function_exists('reportFiltersForDisplay')) {
    /**
     * Human-readable filter summary for the table header, print sheet and PDF.
     *
     * @param array      $filters Normalised filters.
     * @param array|null $league  The selected league row (when a single league is used).
     * @return array<string,string>
     */
    function reportFiltersForDisplay(array $filters, ?array $league = null): array
    {
        $display = [
            'League' => (string) ($league['league_name'] ?? '') !== ''
                ? (string) $league['league_name']
                : 'All leagues',
        ];

        $sport    = (string) ($league['sport'] ?? ($filters['sport'] ?? ''));
        $division = (string) ($league['division'] ?? ($filters['division'] ?? ''));
        $category = (string) ($league['category'] ?? ($filters['category'] ?? ''));
        $status   = (string) ($filters['league_status'] ?? '');
        if ($status === '' && $league !== null) {
            $status = (string) ($league['report_status'] ?? '');
        }

        if ($sport !== '')    $display['Sport']         = $sport;
        if ($division !== '') $display['Division']      = $division;
        if ($category !== '') $display['Category']      = $category;
        if ($status !== '')   $display['League status'] = $status;
        if (($filters['from_date'] ?? '') !== '') $display['From'] = (string) $filters['from_date'];
        if (($filters['to_date'] ?? '') !== '')   $display['To']   = (string) $filters['to_date'];

        return $display;
    }
}

