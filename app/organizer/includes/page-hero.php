<?php
/**
 * Shared organizer hero deck.
 *
 * Single source of truth for the banner at the top of every organizer page so
 * the dashboard, league desk, archive, gallery, reports and settings screens
 * always share the same layout, typography and action styling.
 *
 * Set these variables before including this file:
 *   $heroEyebrow  string  Small uppercase label above the title.
 *   $heroTitle    string  Main heading. Must be pre-escaped (HTML allowed).
 *   $heroSubtitle string  Optional supporting sentence (HTML allowed).
 *   $heroIcon     string  Optional Bootstrap icon class, e.g. 'bi-trophy-fill'.
 *   $heroActions  string  Optional pre-escaped HTML for the right-hand actions.
 *   $heroSide     string  Optional pre-escaped HTML for side widgets (date, clock).
 *   $heroLabel    string  Optional aria-label; defaults to the title text.
 *
 * Usage:  include 'includes/page-hero.php';
 */
$heroEyebrow  = (string)($heroEyebrow ?? '');
$heroTitle    = (string)($heroTitle ?? '');
$heroSubtitle = (string)($heroSubtitle ?? '');
$heroIcon     = (string)($heroIcon ?? '');
$heroActions  = (string)($heroActions ?? '');
$heroSide     = (string)($heroSide ?? '');
$heroLabel    = (string)($heroLabel ?? trim(strip_tags($heroTitle)));
?>
<section class="page-header"<?= $heroLabel !== '' ? ' aria-label="' . htmlspecialchars($heroLabel, ENT_QUOTES) . '"' : '' ?>>
    <span class="page-header-court" aria-hidden="true"></span>
    <div class="page-header-copy">
        <?php if ($heroIcon !== ''): ?>
        <span class="page-header-icon" aria-hidden="true"><i class="bi <?= htmlspecialchars($heroIcon, ENT_QUOTES) ?>"></i></span>
        <?php endif; ?>
        <div class="page-header-text">
            <?php if ($heroEyebrow !== ''): ?>
            <span class="section-eyebrow"><?= $heroEyebrow ?></span>
            <?php endif; ?>
            <h1 class="page-header-title"><?= $heroTitle ?></h1>
            <?php if ($heroSubtitle !== ''): ?>
            <p class="page-header-sub"><?= $heroSubtitle ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($heroSide !== ''): ?>
    <div class="page-header-side"><?= $heroSide ?></div>
    <?php endif; ?>
    <?php if ($heroActions !== ''): ?>
    <div class="page-header-actions"><?= $heroActions ?></div>
    <?php endif; ?>
</section>
<?php
unset($heroEyebrow, $heroTitle, $heroSubtitle, $heroIcon, $heroActions, $heroSide, $heroLabel);
