<?php
/**
 * Shared summary strip styles.
 *
 * Mirrors the summary cards on the account directory (app/admin/users.php):
 * a joined white panel, tinted cells with a 3px accent bar, an uppercase label,
 * a 30px accent value and a muted note.
 *
 * Used with includes/summaryStrip.php:
 *   <?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>
 *
 * The role dashboards keep their own metric strips and are not affected.
 */
?>
<style>
/* ---------- Shared summary strip ---------------------------------------- */
.summary-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 28px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e4e9f2;
    border-radius: 4px;
}
.summary-strip > .summary-item { position: relative; padding: 20px 24px; background: var(--metric-tint, #f1f4fa); }
.summary-strip > .summary-item + .summary-item { border-left: 1px solid #e4e9f2; }
.summary-strip > .summary-item::before {
    content: "";
    position: absolute;
    inset: 0 0 auto;
    height: 3px;
    background: var(--metric-accent, #17356f);
}
.summary-strip .summary-label {
    display: block;
    color: #53617a;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.summary-strip .summary-value {
    display: block;
    margin: 10px 0 7px;
    color: var(--metric-accent, #17356f);
    font-size: 30px;
    font-weight: 600;
    line-height: 1;
}
.summary-strip .summary-note { display: block; color: #6f7a8d; font-size: 11px; }
.summary-strip .summary-note + .summary-note { margin-top: 2px; }
.summary-strip .summary-link {
    color: var(--metric-accent, #17356f);
    font-weight: 600;
    text-decoration: none !important;
}
.summary-strip .summary-link:hover { text-decoration: underline !important; }
.summary-strip.is-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.summary-strip.is-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }

@media (max-width: 991.98px) {
    .summary-strip.is-5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .summary-strip.is-5 > .summary-item:nth-child(4) { border-left: 0; }
    .summary-strip.is-5 > .summary-item:nth-child(n+4) { border-top: 1px solid #e4e9f2; }
}
@media (max-width: 600px) {
    .summary-strip,
    .summary-strip.is-3,
    .summary-strip.is-5 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .summary-strip > .summary-item { padding: 18px; }
    .summary-strip > .summary-item:nth-child(odd) { border-left: 0; }
    .summary-strip > .summary-item:nth-child(even) { border-left: 1px solid #e4e9f2; }
    .summary-strip > .summary-item:nth-child(n+3) { border-top: 1px solid #e4e9f2; }
    .summary-strip .summary-label { font-size: 9px; }
}
</style>
