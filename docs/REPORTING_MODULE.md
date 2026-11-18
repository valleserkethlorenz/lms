# Organizer Reporting Module

## Overview

The reporting module lets an organizer turn their own active leagues into an
on-screen table and downloadable CSV, Excel, PDF or print-ready document.

Everything reads from one place:

- `config/reportQuery.php` — builds every report and owns ownership checks.
- `config/reportFilters.php` — parses/validates filters (no database access).
- `includes/standingsProvider.php` — canonical standings (also used by the
  organizer and public standings pages).
- `includes/reportDocument.php` — shared letterhead/table/signature document.
- `includes/reportExport.php` — CSV/XLSX safety, file names and error responses.

Endpoints:

| Endpoint | Output |
|---|---|
| `app/organizer/action/reportAction.php` | JSON for the on-screen table (5,000-row cap) |
| `app/organizer/action/exportCsv.php` | UTF-8 CSV |
| `app/organizer/action/exportExcel.php` | XLSX |
| `app/organizer/action/exportPdf.php` | PDF |
| `app/organizer/printReport.php` | Printable HTML / browser print dialog |

## Report types

| Type | Contents | Date filter |
|---|---|---|
| `registrations` | Every team registration and its status | Submitted |
| `approved_teams` | Approved teams only | Approved |
| `rosters` | Players, jersey/role, eligibility and document count | Added |
| `schedules` | Published fixtures (byes excluded) | Match date |
| `results` | Completed results with official winner and result type | Played date |
| `standings` | Canonical standings with points/tiebreaks | Not supported |
| `participation` | Roster size, eligible players, matches played | Not supported |
| `summary` | Per-league registration/roster/match totals | Start date |

Standings and participation reject date filters with HTTP 400 instead of
silently ignoring them.

## Filter rules

- `report_type` must be one of the eight types above.
- `league_id` must be `0` (all owned leagues) or a positive owned league ID.
- `league_status` is one of `Draft`, `Upcoming`, `Open`, `Ongoing`, `Completed`,
  or empty for all.
- `from_date` / `to_date` must be real `YYYY-MM-DD` dates and `from <= to`.
- Array-valued query parameters (for example `?report_type[]=x`) are rejected.
- Archived leagues are never included. League ownership is checked before any
  report query runs.

League statuses are computed with `getLeagueStatus()` (the same helper used by
the public directory and registration gate), not a duplicated SQL expression.
`Draft` is handled from `tbl_league.publication_status` before the date logic.

## Output guarantees

- **Standings** come from `getLeagueStandingsWithGroups()`, so points, forfeits,
  penalties, set-based rules, group rankings and the configured tiebreak order
  match `app/organizer/standing.php` and `standing.php` exactly.
- **Results** use `tbl_schedule.winner` and `win_type`; a forfeit/walkover is
  not inferred from the score and a double forfeit is not shown as a draw.
- **Byes** (`Completed` one-team rows) are excluded from schedules, results and
  match counts.
- **Rosters** show a document count (legacy `tbl_player.document` plus typed
  `tbl_submitted_document` rows), not the stored file name.
- **Participation** separates total roster size from eligible players and adds a
  roster check against the league's min/max player rules.
- The filter summary shows the real league name and its sport/division/category/
  status when a single league is selected.

## Exports and safety

- XLSX cells containing user text are written explicitly as strings, so a team
  or player name beginning with `=` can never become a formula.
- CSV values beginning with `=`, `+`, `-`, `@`, tab or carriage return are
  prefixed with `'` for Excel safety; genuine numbers pass through unchanged.
- PDF generation uses Dompdf with remote assets disabled and the local logo as
  a data URI.
- Exports use the exact filter snapshot that produced the visible table. If the
  organizer changes a filter, exports are disabled with a “generate again”
  notice.
- Download responses use `Cache-Control: no-store` and safe file names.
- Database errors are logged privately and returned as a generic 500; raw SQL
  messages are never sent to the browser.
- Report generation is audited (`Generate League Report`, `Export CSV Report`,
  `Export Excel Report`, `Export PDF Report`, `Print Report`).

## Testing

Unit tests (no database required):

- `tests/Unit/ReportFiltersTest.php`
- `tests/Unit/ReportExportTest.php`
- `tests/Unit/ReportDocumentTest.php`

Live smoke checks used during development:

```powershell
php -r "require 'config/dbconnect.php'; require 'config/reportQuery.php';
        foreach (array_keys(reportTypeTitles()) as $t) {
            $r = buildReportQuery($conn, 4, ['report_type' => $t]);
            echo $t, ': ', $r['row_count'], PHP_EOL;
        }"
```

The standings report should return the same team order as
`getLeagueStandings($conn, $leagueId)` and include the `Points` column.

## Performance notes

Migration `043_report_indexes.sql` adds composite indexes for owner/league
filters, team status/created, schedule status/published and player/created.
Report dates are compared without wrapping indexed columns in `DATE()`, and the
on-screen JSON is capped at 5,000 rows; exports always include the complete
result set.

## Future improvements

Suggested, not implemented:

- Saved report presets and scheduled/emailed reports.
- New reports: eligibility/document compliance, match officials, registration
  fees/revenue, qualification.
- Stage/group filter and charts on the report page.
- Pagination/virtual scrolling instead of the on-screen row cap.
- PDF page numbers/watermarks and a true PDF for the admin archive export.
- An `INT` migration for `tbl_team.league_id` for even better index efficiency.
