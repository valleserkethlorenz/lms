<?php
/**
 * Compact setup header (setup.php)
 * --------------------------------
 * Three-step wizard: Format -> Teams -> Matches.
 *
 * Expected: $setupState (from includes/leagueSetupState.php) or the individual
 *           $setupActiveStep, $setupStepKeys, $setupStepDone,
 *           $setupActiveStepIndex, $setupSimplePercent and $id variables.
 */
$setupState   = isset($setupState) && is_array($setupState) ? $setupState : [];
$wizardActive = (string) ($setupState['active_step'] ?? $setupActiveStep ?? 'format');
$wizardKeys   = is_array($setupState['step_keys'] ?? null)
    ? $setupState['step_keys']
    : (isset($setupStepKeys) && is_array($setupStepKeys) ? $setupStepKeys : ['format', 'teams', 'matches']);
$wizardDone   = is_array($setupState['done'] ?? null)
    ? $setupState['done']
    : (isset($setupStepDone) && is_array($setupStepDone) ? $setupStepDone : []);
$wizardLabels = ['format' => 'Format', 'teams' => 'Teams', 'matches' => 'Matches'];
$wizardIndex  = array_search($wizardActive, $wizardKeys, true);
if ($wizardIndex === false) $wizardIndex = 0;
$wizardCount  = max(1, count($wizardKeys));
$wizardPercent = max(0, min(100, (int) ($setupState['percent'] ?? $setupSimplePercent ?? 0)));
$wizardLeagueId = (int) ($id ?? 0);
$wizardTitles = [
    'format'  => 'Choose the competition format',
    'teams'   => 'Add the teams',
    'matches' => 'Play the matches',
];
$wizardHints = [
    'format'  => 'Pick one format. You can change it until the first match exists.',
    'teams'   => 'The approved teams go into the opening group.',
    'matches' => 'Generate the matches and follow the results on Schedule.',
];
$wizardTitle = $wizardTitles[$wizardActive] ?? 'Set up the league';
$wizardHint  = $wizardHints[$wizardActive] ?? '';
?>
<section class="lsp-wizard-head dash-animate dash-animate-d1" id="setup-progress" aria-labelledby="setupWizardTitle">
    <div class="lsp-wizard-copy">
        <span class="lsp-wizard-eyebrow">League setup</span>
        <h2 class="lsp-wizard-title" id="setupWizardTitle" data-lsp-wizard-title>
            Step <?= $wizardIndex + 1 ?> of <?= $wizardCount ?> · <?= htmlspecialchars($wizardLabels[$wizardActive] ?? 'Setup') ?>
        </h2>
        <p class="lsp-wizard-hint" data-lsp-wizard-hint><?= htmlspecialchars($wizardTitle) ?>. <?= htmlspecialchars($wizardHint) ?></p>
    </div>

    <ol class="lsp-wizard-steps" aria-label="Setup steps">
        <?php foreach ($wizardKeys as $wizardStepIndex => $wizardKey):
            $wizardIsActive = $wizardKey === $wizardActive;
            $wizardIsDone   = !empty($wizardDone[$wizardKey]);
            $wizardUrl = '?id=' . $wizardLeagueId . '&step=' . rawurlencode($wizardKey) . '#step-' . rawurlencode($wizardKey);
        ?>
            <li class="lsp-wizard-step<?= $wizardIsDone ? ' is-done' : '' ?><?= $wizardIsActive ? ' is-active' : '' ?>"
                data-step-milestone="<?= htmlspecialchars($wizardKey, ENT_QUOTES) ?>"
                data-step-title="Step <?= $wizardStepIndex + 1 ?> of <?= $wizardCount ?> · <?= htmlspecialchars($wizardLabels[$wizardKey] ?? ucfirst($wizardKey), ENT_QUOTES) ?>"
                data-step-hint="<?= htmlspecialchars(($wizardTitles[$wizardKey] ?? '') . '. ' . ($wizardHints[$wizardKey] ?? ''), ENT_QUOTES) ?>">
                <a href="<?= htmlspecialchars($wizardUrl, ENT_QUOTES) ?>"
                   data-lsp-step-go="<?= htmlspecialchars($wizardKey, ENT_QUOTES) ?>"
                   <?= $wizardIsActive ? 'aria-current="step"' : '' ?>>
                    <span class="lsp-wizard-dot">
                        <?php if ($wizardIsDone): ?><i class="bi bi-check-lg" aria-hidden="true"></i><?php else: ?><?= $wizardStepIndex + 1 ?><?php endif; ?>
                    </span>
                    <span><?= htmlspecialchars($wizardLabels[$wizardKey] ?? ucfirst($wizardKey)) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="lsp-wizard-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $wizardPercent ?>" aria-label="Setup completion">
        <span style="width:<?= $wizardPercent ?>%"></span>
    </div>
</section>
