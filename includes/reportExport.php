<?php
/**
 * Report export helpers
 * =====================
 * Shared rules for CSV/XLSX downloads:
 *   - spreadsheet formula-injection protection
 *   - numeric vs. text cell typing
 *   - safe file names and download headers
 *   - consistent JSON/HTML error responses
 */

if (!function_exists('reportStripControlChars')) {
    /** Remove control characters that are never valid in a report cell. */
    function reportStripControlChars(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }
}

if (!function_exists('reportIsNumericCell')) {
    /**
     * True only for genuine plain numbers. Leading-zero strings ("007") and
     * formula-shaped values ("=1+1", "+1") intentionally stay text.
     */
    function reportIsNumericCell($value): bool
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value);
        }
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        return $value !== '' && (bool) preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value);
    }
}

if (!function_exists('reportStartsWithFormulaChar')) {
    /** Excel/Sheets interpret a leading =, +, -, @, tab or CR as a formula. */
    function reportStartsWithFormulaChar(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        return strpos("=+-@\t\r", $value[0]) !== false;
    }
}

if (!function_exists('reportCsvValue')) {
    /**
     * Make one value safe for a CSV opened in Excel. Genuine numbers pass
     * through untouched; formula-shaped text is prefixed with an apostrophe.
     */
    function reportCsvValue($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (reportIsNumericCell($value)) {
            return trim((string) $value);
        }

        $value = reportStripControlChars((string) $value);
        if (reportStartsWithFormulaChar($value)) {
            return "'" . $value;
        }
        // Preserve leading-zero codes (jersey numbers, phone-like values) when
        // the CSV is opened in Excel.
        if (preg_match('/^0\d+$/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}

if (!function_exists('reportSpreadsheetCellType')) {
    /**
     * Cell typing for PhpSpreadsheet: numeric values stay numbers, everything
     * else is written explicitly as a string so it can never become a formula.
     *
     * @return string 'numeric'|'string'
     */
    function reportSpreadsheetCellType($value): string
    {
        return reportIsNumericCell($value) ? 'numeric' : 'string';
    }
}

if (!function_exists('reportSlug')) {
    /** URL/file-name-safe slug. */
    function reportSlug(string $value, string $fallback = 'report'): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('reportDownloadFilename')) {
    /** Build a safe attachment name such as "team-rosters-mayors-cup-20261119-143501.xlsx". */
    function reportDownloadFilename(string $title, string $extension, string $leagueName = ''): string
    {
        $extension = strtolower($extension);
        if (!in_array($extension, ['csv', 'xlsx', 'pdf'], true)) {
            $extension = 'dat';
        }
        $parts = [reportSlug($title, 'league-report')];
        if ($leagueName !== '') {
            $parts[] = reportSlug($leagueName, 'league');
        }
        $parts[] = date('Ymd-His');
        return implode('-', $parts) . '.' . $extension;
    }
}

if (!function_exists('reportSendDownloadHeaders')) {
    /** Send download headers without allowing header injection. */
    function reportSendDownloadHeaders(string $filename, string $mimeType): void
    {
        $filename = str_replace(["\r", "\n", '"'], '', $filename);
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
    }
}

if (!function_exists('reportWantsJson')) {
    /** Detect fetch/XHR callers so export errors can stay machine-readable. */
    function reportWantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr    = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return stripos($accept, 'application/json') !== false || $xhr === 'XMLHttpRequest';
    }
}

if (!function_exists('reportErrorDetails')) {
    /**
     * Map an exception to a safe HTTP status/message pair.
     *
     * @return array{status:int,msg:string}
     */
    function reportErrorDetails(Throwable $e): array
    {
        if ($e instanceof InvalidArgumentException) {
            return ['status' => 400, 'msg' => $e->getMessage()];
        }
        if (class_exists('ReportAccessDeniedException') && $e instanceof ReportAccessDeniedException) {
            return ['status' => 403, 'msg' => $e->getMessage()];
        }
        if (class_exists('ReportExecutionException') && $e instanceof ReportExecutionException) {
            return ['status' => 500, 'msg' => 'Unable to generate the selected report. Please try again later.'];
        }
        return ['status' => 500, 'msg' => 'An unexpected error occurred. Please try again later.'];
    }
}

if (!function_exists('reportSendErrorResponse')) {
    /**
     * Emit a JSON or HTML error and exit. Execution errors are logged with
     * their real details, never echoed to the browser.
     */
    function reportSendErrorResponse(Throwable $e, bool $json, string $backUrl = 'reports.php'): void
    {
        if (!($e instanceof InvalidArgumentException)
            && !(class_exists('ReportAccessDeniedException') && $e instanceof ReportAccessDeniedException)) {
            error_log('[REPORT] ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        $details = reportErrorDetails($e);
        http_response_code($details['status']);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => $details['status'], 'msg' => $details['msg']]);
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $message = htmlspecialchars($details['msg'], ENT_QUOTES, 'UTF-8');
        $back    = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>Report error</title>'
            . '<style>body{font-family:Segoe UI,Arial,sans-serif;padding:40px;color:#1f2937;background:#f7f9fc}'
            . '.card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #dde4ee;border-radius:8px;padding:28px}'
            . 'h1{font-size:20px;color:#09173c;margin:0 0 10px}p{font-size:14px;line-height:1.7}'
            . 'a{display:inline-block;margin-top:14px;background:#09173c;color:#fff;text-decoration:none;'
            . 'border-radius:4px;padding:9px 16px;font-size:13px}</style></head><body><div class="card">'
            . '<h1>Unable to build the report</h1><p>' . $message . '</p>'
            . '<a href="' . $back . '">Back to Reports</a></div></body></html>';
        exit;
    }
}

if (!function_exists('reportSendNoDataResponse')) {
    /** 404 response for exports whose current filters produced no rows. */
    function reportSendNoDataResponse(bool $json, string $backUrl = 'reports.php'): void
    {
        http_response_code(404);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $message = 'No data matches the selected filters.';
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 404, 'msg' => $message]);
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $back        = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>No report data</title>'
            . '<style>body{font-family:Segoe UI,Arial,sans-serif;padding:40px;color:#1f2937;background:#f7f9fc}'
            . '.card{max-width:640px;margin:0 auto;background:#fff;border:1px solid #dde4ee;border-radius:8px;padding:28px}'
            . 'h1{font-size:20px;color:#09173c;margin:0 0 10px}p{font-size:14px;line-height:1.7}'
            . 'a{display:inline-block;margin-top:14px;background:#09173c;color:#fff;text-decoration:none;'
            . 'border-radius:4px;padding:9px 16px;font-size:13px}</style></head><body><div class="card">'
            . '<h1>No report data</h1><p>' . $safeMessage . '</p>'
            . '<a href="' . $back . '">Back to Reports</a></div></body></html>';
        exit;
    }
}

if (!function_exists('reportAuditSafely')) {
    /** Audit logging must never break a report download. */
    function reportAuditSafely(mysqli $conn, string $action, string $details): void
    {
        if (!function_exists('auditLog')) {
            return;
        }
        try {
            auditLog($conn, $action, $details);
        } catch (Throwable $e) {
            error_log('[REPORT] Audit log failed: ' . $e->getMessage());
        }
    }
}

