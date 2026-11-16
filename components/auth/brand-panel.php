<?php
/**
 * Shared left-hand branded panel for the authentication pages.
 *
 * Pages may override any of the $auth* variables before including this
 * file. The defaults below describe the League Management System itself,
 * not another product, so the auth pages stay on-brand by default.
 */

$authEyebrow        = $authEyebrow        ?? 'City Sports Development Program';
$authHeadline       = $authHeadline       ?? 'Your league season,';
$authHeadlineAccent = $authHeadlineAccent ?? 'all in one place.';
$authLead           = $authLead           ?? 'Register teams, manage rosters, follow schedules, and keep every league activity organized in one account.';
$authPoints         = $authPoints         ?? [
    ['icon' => 'bi bi-trophy',             'title' => 'League management',   'text' => 'Publish leagues and track registration windows.'],
    ['icon' => 'bi bi-people-fill',        'title' => 'Team registration',   'text' => 'Submit rosters and player requirements online.'],
    ['icon' => 'bi bi-calendar-event',     'title' => 'Schedules & results', 'text' => 'Follow fixtures, scores, and live standings.'],
    ['icon' => 'bi bi-file-earmark-text',  'title' => 'Records & reports',   'text' => 'Keep documents and activity history organized.'],
];

$authBase    = isset($lmsBase) ? rtrim((string) $lmsBase, '/') : '';
$authHomeUrl = htmlspecialchars(($authBase !== '' ? $authBase : '') . '/index.php', ENT_QUOTES, 'UTF-8');
$authLogoUrl = htmlspecialchars(($authBase !== '' ? $authBase : '') . '/assets/images/logo.jpg', ENT_QUOTES, 'UTF-8');
?>
<aside class="auth-brand-panel" aria-label="City Sports Development Program">
    <a class="auth-brand-pill" href="<?= $authHomeUrl ?>">
        <span class="auth-brand-logo">
            <img src="<?= $authLogoUrl ?>" alt="City Sports Development Program logo">
        </span>
        <span class="auth-brand-text">
            <span class="auth-brand-name">CITY SPORTS DEVELOPMENT PROGRAM</span>
            <span class="auth-brand-sub">League Management System</span>
        </span>
    </a>

    <div class="auth-brand-body">
        <span class="auth-brand-eyebrow"><?= htmlspecialchars($authEyebrow, ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="auth-brand-title">
            <?= htmlspecialchars($authHeadline, ENT_QUOTES, 'UTF-8') ?><br>
            <span><?= htmlspecialchars($authHeadlineAccent, ENT_QUOTES, 'UTF-8') ?></span>
        </h1>
        <p class="auth-brand-lead"><?= htmlspecialchars($authLead, ENT_QUOTES, 'UTF-8') ?></p>
        <ul class="auth-brand-points">
            <?php foreach ($authPoints as $authPoint): ?>
                <li class="auth-brand-point">
                    <i class="<?= htmlspecialchars((string) ($authPoint['icon'] ?? 'bi bi-check-circle'), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                    <span>
                        <strong><?= htmlspecialchars((string) ($authPoint['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        <?= htmlspecialchars((string) ($authPoint['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="auth-brand-foot">
        <span>Panabo City &middot; Davao del Norte</span>
        <a href="<?= $authHomeUrl ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to home</a>
    </div>
</aside>
