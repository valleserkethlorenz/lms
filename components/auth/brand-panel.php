<?php
/**
 * Shared left-hand branded panel for the authentication pages.
 *
 * Pages may override any of the $auth* variables before including this
 * file. The defaults below describe the League Management System itself,
 * not another product, so the auth pages stay on-brand by default.
 */


$authHeadline       = $authHeadline       ?? 'Your league season,';
$authHeadlineAccent = $authHeadlineAccent ?? 'all in one place.';
$authLead           = $authLead           ?? 'Register your team, manage rosters, follow schedules, and keep every league activity organized in one account.';
$authPoints         = $authPoints         ?? [
    ['icon' => 'bi bi-compass',            'title' => 'Browse leagues',      'text' => 'Find open leagues and registration windows near you.'],
    ['icon' => 'bi bi-people-fill',        'title' => 'Team registration',   'text' => 'Submit your roster and player requirements online.'],
    ['icon' => 'bi bi-calendar-event',     'title' => 'Schedules & results', 'text' => 'Follow your fixtures, scores, and live standings.'],
    ['icon' => 'bi bi-folder2-open',       'title' => 'Team records',        'text' => 'Keep your team documents and activity history organized.'],
];

$authBase    = isset($lmsBase) ? rtrim((string) $lmsBase, '/') : '';
$authHomeUrl = htmlspecialchars(($authBase !== '' ? $authBase : '') . '/index.php', ENT_QUOTES, 'UTF-8');
?>
<aside class="auth-brand-panel" aria-label="City Sports Development Program">
    <a class="auth-brand-home" href="<?= $authHomeUrl ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>Back to home
    </a>

    <div class="auth-brand-body">
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
       
    </div>
</aside>
