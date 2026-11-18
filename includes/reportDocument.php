<?php
/**
 * Report Document Renderer
 * =========================
 * Builds the shared, print-ready report document - letterhead, filter summary,
 * data table, signature block and footer - used by:
 *   - app/organizer/printReport.php       (browser "Print / Save as PDF")
 *   - app/organizer/action/exportPdf.php  (server-side Dompdf PDF)
 *
 * Both callers feed it the rows from buildReportQuery(), so the printed sheet,
 * the PDF and the on-screen report table always show identical numbers.
 *
 * The markup deliberately avoids flexbox/grid so Dompdf renders it faithfully.
 */

/** Read a local image file and return it as a data URI, or '' when unavailable. */
function reportDocumentLogoDataUri(?string $path): string
{
    if (!$path || !is_file($path) || !is_readable($path)) return '';
    $bytes = @file_get_contents($path);
    if ($bytes === false || $bytes === '') return '';
    $mime = 'image/png';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $path) ?: 'image/png';
            finfo_close($finfo);
        }
    }
    if (strpos($mime, 'image/') !== 0) return '';
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}

/** Turn a database column key into a human label (Registration_Status -> Registration Status). */
function reportDocumentColumnLabel(string $column): string
{
    return ucwords(str_replace('_', ' ', $column));
}

/** Shared stylesheet for both the browser print view and the Dompdf PDF. */
function reportDocumentStyles(string $accent = '#09173c'): string
{
    return '@page{size:A4 landscape;margin:9mm}'
        . 'body{font-family:"DejaVu Sans","Segoe UI",Arial,sans-serif;font-size:10px;color:#1f2937;margin:0;padding:14px}'
        . '.doc{width:100%}'
        . '.print-toolbar{margin:0 0 12px;text-align:right}'
        . '.print-toolbar button{background:' . $accent . ';color:#fff;border:0;border-radius:6px;padding:9px 18px;font-size:11px;cursor:pointer}'
        . '.letterhead{width:100%;border-collapse:collapse;border-bottom:2px solid ' . $accent . ';margin-bottom:10px}'
        . '.letterhead td{vertical-align:middle;padding:0 0 8px}'
        . '.logo-cell{width:78px}.logo-cell img{width:66px;height:66px;object-fit:contain}'
        . '.org-name{font-size:13px;font-weight:bold;letter-spacing:1.6px;color:' . $accent . ';text-transform:uppercase}'
        . '.doc-title{font-size:19px;font-weight:bold;color:' . $accent . ';margin-top:2px}'
        . '.doc-sub{font-size:11px;color:#4b5563;margin-top:2px}'
        . '.meta-cell{width:210px;text-align:right;font-size:9px;color:#6b7280;line-height:1.6}'
        . '.meta-cell .meta-strong{color:#111827;font-weight:bold}'
        . '.filters{font-size:9px;color:#4b5563;margin:0 0 10px}'
        . '.filters .filters-label{font-weight:bold;color:' . $accent . '}'
        . '.data{width:100%;border-collapse:collapse}'
        . '.data thead{display:table-header-group}'
        . '.data th{background:' . $accent . ';color:#fff;font-size:9px;text-transform:uppercase;text-align:left;padding:6px 5px;border:1px solid ' . $accent . '}'
        . '.data td{font-size:9px;padding:5px;border:1px solid #d1d5db;vertical-align:top;word-wrap:break-word}'
        . '.data tbody tr:nth-child(even){background:#f3f4f6}'
        . '.data tr{page-break-inside:avoid}'
        . '.no-records{text-align:center;font-style:italic;color:#6b7280;padding:14px}'
        . '.signatures{width:100%;border-collapse:collapse;margin-top:26px;page-break-inside:avoid}'
        . '.signatures td{width:50%;padding:0 12px;vertical-align:bottom;font-size:9px;color:#374151}'
        . '.sig-role{font-weight:bold;color:' . $accent . ';margin-bottom:26px}'
        . '.sig-line{border-top:1px solid #6b7280;padding-top:3px}'
        . '.sig-name{font-weight:bold}'
        . '.doc-footer{margin-top:14px;border-top:1px solid #d1d5db;padding-top:6px;text-align:center;font-size:8px;color:#9ca3af}'
        . '@media print{.print-toolbar{display:none!important}}';
}

/**
 * Build the complete printable report document.
 *
 * @param array $report  Row set produced by buildReportQuery().
 * @param array $options title, subtitle, organization, generated_by, generated_at,
 *                       signatories (label => name), logo_path, footer_note, accent.
 */
function buildReportDocumentHtml(array $report, array $options = []): string
{
    $rows        = $report['rows'] ?? [];
    $filters     = $report['filters'] ?? [];
    $accent      = (string)($options['accent'] ?? '#09173c');
    $title       = (string)($options['title'] ?? ($report['title'] ?? 'Report'));
    $subtitle    = (string)($options['subtitle'] ?? '');
    $org         = (string)($options['organization'] ?? 'League Management System');
    $generatedBy = (string)($options['generated_by'] ?? '');
    $generatedAt = (string)($options['generated_at'] ?? ($report['generated_at'] ?? date('Y-m-d H:i:s')));
    $footerNote  = (string)($options['footer_note'] ?? 'Confidential - for official league use only.');
    $signatories = $options['signatories'] ?? [];
    $logo        = reportDocumentLogoDataUri($options['logo_path'] ?? null);

    $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

    $html  = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    $html .= '<title>' . $e($title) . '</title><style>' . reportDocumentStyles($accent) . '</style></head><body>';
    $html .= '<div class="doc">';

    // ---- Letterhead ----------------------------------------------------
    $html .= '<table class="letterhead"><tr>';
    $html .= '<td class="logo-cell">' . ($logo !== '' ? '<img src="' . $logo . '" alt="">' : '') . '</td>';
    $html .= '<td><div class="org-name">' . $e($org) . '</div>';
    $html .= '<div class="doc-title">' . $e($title) . '</div>';
    if ($subtitle !== '') $html .= '<div class="doc-sub">' . $e($subtitle) . '</div>';
    $html .= '</td>';
    $html .= '<td class="meta-cell"><div>Generated: <span class="meta-strong">' . $e($generatedAt) . '</span></div>';
    if ($generatedBy !== '') $html .= '<div>Generated by: <span class="meta-strong">' . $e($generatedBy) . '</span></div>';
    $html .= '<div>Total records: <span class="meta-strong">' . count($rows) . '</span></div></td>';
    $html .= '</tr></table>';

    // ---- Applied filters ----------------------------------------------
    $filterParts = [];
    foreach ($filters as $key => $value) $filterParts[] = $key . ': ' . $value;
    $html .= '<div class="filters"><span class="filters-label">Filters:</span> ';
    $html .= $filterParts ? $e(implode('   |   ', $filterParts)) : 'None applied';
    $html .= '</div>';

    // ---- Data table ---------------------------------------------------
    $headers = $rows ? array_keys($rows[0]) : [];
    $html .= '<table class="data"><thead><tr>';
    foreach ($headers as $header) $html .= '<th>' . $e(reportDocumentColumnLabel($header)) . '</th>';
    $html .= '</tr></thead><tbody>';
    if (!$rows) {
        $html .= '<tr><td class="no-records" colspan="' . max(1, count($headers)) . '">No matching records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) $html .= '<td>' . $e($row[$header] ?? '') . '</td>';
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table>';

    // ---- Signature block ----------------------------------------------
    if (!empty($signatories)) {
        $cells = '';
        foreach ($signatories as $role => $name) {
            $cells .= '<td><div class="sig-role">' . $e((string)$role) . ':</div><div class="sig-line">';
            if ((string)$name !== '') $cells .= '<div class="sig-name">' . $e((string)$name) . '</div>';
            $cells .= '<div>Date: ______________________</div></div></td>';
        }
        $html .= '<table class="signatures"><tr>' . $cells . '</tr></table>';
    }

    $html .= '<div class="doc-footer">' . $e($org) . ' &mdash; ' . $e($footerNote) . '</div>';
    $html .= '</div></body></html>';

    return $html;
}
