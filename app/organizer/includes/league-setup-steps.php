<?php
/**
 * Simple setup wizard (setup.php)
 * ------------------------------
 * Three steps only: Format -> Teams -> Matches.
 *
 * The goal is a normal league workflow:
 *   1. pick a format,
 *   2. add the approved teams,
 *   3. generate matches, play them, then create a seeded playoff bracket.
 *
 * The preset, its cut-off settings (top N per group/pool, Swiss rounds) and
 * every stage action live on this page. The server actions used here are
 * applyStructure, seedStageTeams, promoteQualifiers, generateSwissRound,
 * generateSeededBracket and createPlayoffs; fixture draws use the
 * previewStageMatches / generateStageMatches endpoints.
 *
 * Expected: $id, $csrf, $stages, $stageStats, $stageTypes, $stageTeamNames,
 * $approvedTeamCount, $matchCount, $setupStructureKey, $setupStructureLabel,
 * $setupStructureIsPreset, $setupStructureCards, $setupActiveStep,
 * $setupStepDone, $setupStepKeys.
 */
$id                = isset($id) ? (int) $id : 0;
$csrf              = isset($csrf) ? (string) $csrf : '';
$stages            = isset($stages) && is_array($stages) ? $stages : [];
$stageStats        = isset($stageStats) && is_array($stageStats) ? $stageStats : [];
$stageTypes        = isset($stageTypes) && is_array($stageTypes) ? $stageTypes : [];
$stageTeamNames    = isset($stageTeamNames) && is_array($stageTeamNames) ? $stageTeamNames : [];
$approvedTeamCount = isset($approvedTeamCount) ? (int) $approvedTeamCount : count($stageTeamNames);
$matchCount        = isset($matchCount) ? (int) $matchCount : 0;
$setupStructureKey = isset($setupStructureKey) ? $setupStructureKey : null;
$setupStructureLabel = isset($setupStructureLabel) ? (string) $setupStructureLabel : '';
$setupState = isset($setupState) && is_array($setupState) ? $setupState : [];
$setupCapabilities = is_array($setupState['capabilities'] ?? null) ? $setupState['capabilities'] : [];
$setupStructureCards = (isset($setupStructureCards) && is_array($setupStructureCards) && $setupStructureCards)
    ? $setupStructureCards
    : (function_exists('leagueSetupStructureCards') ? leagueSetupStructureCards() : []);
$setupActiveStep   = isset($setupState['active_step']) ? (string) $setupState['active_step'] : (isset($setupActiveStep) ? (string) $setupActiveStep : 'format');
$setupStepDone     = isset($setupState['done']) && is_array($setupState['done'])
    ? $setupState['done']
    : (isset($setupStepDone) && is_array($setupStepDone) ? $setupStepDone : []);
$setupStepKeys     = isset($setupState['step_keys']) && is_array($setupState['step_keys'])
    ? $setupState['step_keys']
    : (isset($setupStepKeys) && is_array($setupStepKeys) ? $setupStepKeys : ['format', 'teams', 'matches']);
$setupMatchesDone  = isset($setupState['finish_done']) ? (bool) $setupState['finish_done'] : (isset($setupMatchesDone) ? (bool) $setupMatchesDone : false);

/* Step navigation follows the state's step keys, so a structure without a
   team draw (single elimination) simply has no Teams step to skip through. */
$wizardStepLabels = ['format' => 'Format', 'teams' => 'Teams', 'matches' => 'Matches'];
$wizardStepLabel = static function (string $key) use ($wizardStepLabels): string {
    return $wizardStepLabels[$key] ?? ucfirst($key);
};
$wizardStepNumber = static function (string $key) use ($setupStepKeys): int {
    $index = array_search($key, $setupStepKeys, true);
    return $index === false ? 0 : (int) $index + 1;
};
$wizardStepAfter = static function (string $key) use ($setupStepKeys): string {
    $index = array_search($key, $setupStepKeys, true);
    if ($index !== false && isset($setupStepKeys[$index + 1])) return (string) $setupStepKeys[$index + 1];
    return (string) $setupStepKeys[count($setupStepKeys) - 1];
};
$wizardStepBefore = static function (string $key) use ($setupStepKeys): string {
    $index = array_search($key, $setupStepKeys, true);
    if ($index !== false && $index > 0) return (string) $setupStepKeys[$index - 1];
    return (string) $setupStepKeys[0];
};
$wizardCan = static function (string $capability) use ($setupCapabilities): bool {
    return array_key_exists($capability, $setupCapabilities) ? (bool) $setupCapabilities[$capability] : true;
};

$wizardStageStats = static function (int $stageId) use ($stageStats): array {
    $row = $stageStats[$stageId] ?? [];
    $matches = (int) ($row['matches'] ?? 0);
    $completed = (int) ($row['completed'] ?? 0);
    return [
        'matches'   => $matches,
        'completed' => $completed,
        'complete'  => (bool) ($row['complete'] ?? ($matches > 0 && $completed >= $matches)),
        'percent'   => $matches > 0 ? (int) round(min(1, $completed / $matches) * 100) : 0,
    ];
};
$wizardStageTeamIds = static function (array $stage): array {
    $ids = [];
    foreach (($stage['groups'] ?? []) as $stageGroup) {
        foreach (($stageGroup['teams'] ?? []) as $stageMember) {
            $teamId = (int) ($stageMember['team_id'] ?? 0);
            if ($teamId > 0) $ids[$teamId] = true;
        }
    }
    return $ids;
};
$wizardState = static function (string $key) use ($setupStepDone, $setupActiveStep): array {
    if (!empty($setupStepDone[$key])) return ['is-done', 'Done'];
    if ($key === $setupActiveStep) return ['is-current', 'Current'];
    return ['is-upcoming', 'Later'];
};
$wizardStatus = static function (array $stats): array {
    if ($stats['matches'] === 0) return ['is-upcoming', 'Not generated'];
    if ($stats['complete']) return ['is-done', 'Complete'];
    return ['is-current', $stats['completed'] . ' / ' . $stats['matches']];
};

$wizardRoundRobin = null;
$wizardKnockout   = null;
foreach ($stages as $wizardStageRow) {
    $wizardType = (string) ($wizardStageRow['stage_type'] ?? '');
    if ($wizardRoundRobin === null && in_array($wizardType, ['group', 'crossover'], true)) {
        $wizardRoundRobin = $wizardStageRow;
    }
    if ($wizardKnockout === null && $wizardType === 'knockout') {
        $wizardKnockout = $wizardStageRow;
    }
}
$wizardPrimary = $wizardRoundRobin ?? ($stages[0] ?? null);
$wizardPrimaryId = $wizardPrimary ? (int) $wizardPrimary['id'] : 0;
$wizardPrimaryStats = $wizardPrimaryId > 0 ? $wizardStageStats($wizardPrimaryId) : ['matches' => 0, 'completed' => 0, 'complete' => false, 'percent' => 0];
$wizardKnockoutStats = $wizardKnockout ? $wizardStageStats((int) $wizardKnockout['id']) : ['matches' => 0, 'completed' => 0, 'complete' => false, 'percent' => 0];
$wizardKnockoutTeams = $wizardKnockout ? count($wizardStageTeamIds($wizardKnockout)) : 0;
$wizardPlacedIds = $wizardRoundRobin ? $wizardStageTeamIds($wizardRoundRobin) : [];
$wizardPlacedCount = count($wizardPlacedIds);
$wizardTeamPercent = $approvedTeamCount > 0 ? (int) round(min(1, $wizardPlacedCount / $approvedTeamCount) * 100) : 0;
$wizardRankedCount = $wizardRoundRobin ? count($wizardRoundRobin['groups'][0]['teams'] ?? []) : 0;
$wizardPlayoffDefault = max(2, min(8, $wizardRankedCount ?: ($wizardPlacedCount ?: $approvedTeamCount) ?: 2));
$wizardRoundRobinGroups = $wizardRoundRobin ? count($wizardRoundRobin['groups'] ?? []) : 0;
$wizardRoundRobinNext = null;
if ($wizardRoundRobin) {
    $wizardRoundRobinOrder = (int) ($wizardRoundRobin['display_order'] ?? 0);
    foreach ($stages as $wizardStageRow) {
        if ((int) ($wizardStageRow['display_order'] ?? 0) > $wizardRoundRobinOrder) {
            $wizardRoundRobinNext = $wizardStageRow;
            break;
        }
    }
}
$wizardFormatTitle = $stages
    ? (string) ($setupState['structure']['title'] ?? 'Custom format')
    : 'No format yet';
/* Every preset comes from leagueStructure.php. The page used to hardcode three
   of the presets, which left "Groups → knockout" and "Pools → playoffs"
   unreachable even though the engine supports them. */
$wizardFormatIcons = [
    'round_robin'          => 'bi-arrow-repeat',
    'swiss_system'         => 'bi-shuffle',
    'single_elimination'   => 'bi-diagram-2',
    'double_elimination'   => 'bi-diagram-2-fill',
    'round_robin_playoffs' => 'bi-trophy',
    'groups_knockout'      => 'bi-diagram-3',
    'pools_playoffs'       => 'bi-bounding-box-circles',
];
$wizardPresetDefinitions = function_exists('leagueStructurePresets') ? leagueStructurePresets() : [];
$wizardFormatCards = [];
foreach ($setupStructureCards as $wizardFormatKey => $wizardFormatCard) {
    /* A tiny stage-flow diagram derived from the preset: Table → Bracket,
       Groups → Bracket, WB + LB + GF, etc. It gives the Format step a visual
       shape without duplicating the preset definitions. */
    $wizardFormatFlow = [];
    foreach (($wizardPresetDefinitions[$wizardFormatKey]['stages'] ?? []) as $wizardFormatStage) {
        $wizardStageType   = (string) ($wizardFormatStage['type'] ?? '');
        $wizardStageFormat = (string) ($wizardFormatStage['format'] ?? '');
        $wizardStageGroups = count($wizardFormatStage['groups'] ?? []);
        if ($wizardStageType === 'placement') {
            $wizardFormatFlow[] = '3rd place';
        } elseif ($wizardStageType === 'crossover') {
            $wizardFormatFlow[] = 'Pools';
        } elseif ($wizardStageType === 'knockout') {
            $wizardFormatFlow[] = $wizardStageFormat === 'double_elimination' ? 'WB + LB + GF' : 'Bracket';
        } elseif ($wizardStageFormat === 'swiss') {
            $wizardFormatFlow[] = 'Swiss';
        } else {
            $wizardFormatFlow[] = $wizardStageGroups > 1 ? 'Groups ×' . $wizardStageGroups : 'Table';
        }
    }

    $wizardFormatCards[] = [
        'key'         => (string) $wizardFormatKey,
        'title'       => (string) ($wizardFormatCard['title'] ?? $wizardFormatKey),
        'text'        => (string) ($wizardFormatCard['text'] ?? ''),
        'icon'        => $wizardFormatIcons[$wizardFormatKey] ?? 'bi-diagram-3',
        'flow'        => $wizardFormatFlow,
        'recommended' => $wizardFormatKey === 'round_robin_playoffs',
        'current'     => $setupStructureKey === $wizardFormatKey,
    ];
}

/* The Format step only selects a card; the Next button applies it. Keep the
   card that is already applied selected. With no stages yet, preselect the
   recommended preset so Next always carries a valid choice. */
$wizardFormatCheckedKey = '';
foreach ($wizardFormatCards as $wizardFormatCard) {
    if (!empty($wizardFormatCard['current'])) {
        $wizardFormatCheckedKey = (string) $wizardFormatCard['key'];
        break;
    }
}
if ($wizardFormatCheckedKey === '' && !$stages) {
    foreach ($wizardFormatCards as $wizardFormatCard) {
        if (!empty($wizardFormatCard['recommended'])) {
            $wizardFormatCheckedKey = (string) $wizardFormatCard['key'];
            break;
        }
    }
}

/* Editable preset settings (group count / top N / Swiss rounds). The values are
   prefilled from the current stage row when this preset is applied, otherwise
   from the preset defaults or the smart group recommendation. */
$wizardEditableSettings = function_exists('leagueStructureEditableSettings') ? leagueStructureEditableSettings() : [];
$wizardRecommendationTeams = (int) ($approvedTeamCount > 0
    ? $approvedTeamCount
    : (isset($league['max_team']) ? (int) $league['max_team'] : 0));
$wizardGroupRecommendation = ($wizardRecommendationTeams > 0 && function_exists('recommendedGroupSetup'))
    ? recommendedGroupSetup($wizardRecommendationTeams)
    : null;
$wizardSettingValues = static function (string $structureKey) use ($wizardEditableSettings, $wizardGroupRecommendation, $stages, $setupStructureKey): array {
    $values = function_exists('leagueStructureDefaultSettings') ? leagueStructureDefaultSettings($structureKey) : [];
    if ($setupStructureKey === $structureKey) {
        $stageRows = array_values($stages);
        foreach ($wizardEditableSettings[$structureKey] ?? [] as $field) {
            $stageRow = $stageRows[(int)$field['stage']] ?? null;
            if (!$stageRow) continue;
            if ($field['field'] === 'groups') {
                $groupCount = count($stageRow['groups'] ?? []);
                if ($groupCount > 0) $values[(int)$field['stage']][$field['field']] = $groupCount;
                continue;
            }
            $column = $field['field'] === 'qualifiers' ? 'qualifiers_per_group' : 'rounds_planned';
            if (isset($stageRow[$column]) && (int)$stageRow[$column] > 0) {
                $values[(int)$field['stage']][$field['field']] = (int)$stageRow[$column];
            }
        }
    } elseif ($structureKey === 'groups_knockout'
        && $wizardGroupRecommendation
        && empty($wizardGroupRecommendation['warning'])) {
        $values[0]['groups'] = (int) $wizardGroupRecommendation['groups'];
        $values[0]['qualifiers'] = (int) $wizardGroupRecommendation['qualifiers'];
    }
    return $values;
};

/* The Matches step follows the engine's own "what is next" task. */
$wizardNextTask = is_array($setupState['task'] ?? null) ? $setupState['task'] : setupNextTask($stages, $stageStats);
$wizardTaskStage = is_array($wizardNextTask['stage'] ?? null) ? $wizardNextTask['stage'] : null;
$wizardTaskStageId = (int) ($wizardNextTask['stage_id'] ?? 0);
$wizardTaskStats = $wizardTaskStageId > 0 ? $wizardStageStats($wizardTaskStageId) : ['matches' => 0, 'completed' => 0, 'complete' => false, 'percent' => 0];


/* Fixture generation lives ONLY on Setup. The form below posts to the same
   preview/generate endpoints the Schedule page used to duplicate. The draw
   pattern is not a question: the stage's default (first) pattern is posted as
   a hidden value, so a format without a bracket never shows a bracket picker. */
$wizardConnection = $conn ?? null;

$wizardGenerationForm = static function (array $stage) use ($wizardConnection, $id, $stages): string {
    if (!$wizardConnection) return '';
    if (!function_exists('stageGenerationPatternOptions')) return '';
    $stageId = (int)($stage['id'] ?? 0);
    if ($stageId <= 0) return '';

    /* The option list is in preference order (first = default) and also decides
       whether this stage may be generated at all. */
    $options = stageGenerationPatternOptions($wizardConnection, (int)$id, $stage, count($stages));
    if (!$options) return '';
    $defaultPattern = (string) array_key_first($options);
    $defaultLabel   = (string) ($options[$defaultPattern] ?? '');

    /* Single elimination previews use the legacy round list; a double
       elimination stage is previewed from its pure winners/losers/final plan. */
    $isDoubleElimination = function_exists('stageFormat') && stageFormat($stage) === 'double_elimination';
    $resetChecked = $isDoubleElimination
        && function_exists('stageSetting')
        && !empty(stageSetting($stage, 'bracket_reset', 0));
    if ($isDoubleElimination) {
        $defaultLabel = 'Double elimination — winners bracket, losers bracket and grand final';
    }
    $previewButton = '<button type="button" class="btn btn-outline-primary js-wizard-preview"><i class="bi bi-eye me-1" aria-hidden="true"></i>Preview</button>';
    $resetField = $isDoubleElimination
        ? '<label class="lsp-check lsp-generate-reset"><input type="checkbox" name="bracket_reset" value="1"'
            . ($resetChecked ? ' checked' : '') . '> Bracket reset grand final'
            . '<small>If the losers-bracket team wins the first final, play a second one.</small></label>'
        : '';

    return '<div class="lsp-generate js-wizard-generate" data-stage-id="' . $stageId . '">'
        . '<div class="lsp-generate-field">'
        . '<div class="form-label">Draw</div>'
        . '<div class="lsp-generate-value">' . htmlspecialchars($defaultLabel, ENT_QUOTES) . '</div>'
        . '<input type="hidden" name="pattern" value="' . htmlspecialchars($defaultPattern, ENT_QUOTES) . '">'
        . $resetField
        . '</div>'
        . '<div class="lsp-generate-actions">'
        . $previewButton
        . '<button type="button" class="btn btn-primary js-wizard-generate-btn"><i class="bi bi-magic me-1" aria-hidden="true"></i>Generate matches</button>'
        . '</div>'
        . '</div>';
};
?>
<div class="lsp-steps-wrap" id="setup-steps">
<?php [$wizardFormatState, $wizardFormatStatus] = $wizardState('format'); ?>
<!-- ==================== STEP 1 · FORMAT ==================== -->
<section class="lsp-step-card <?= $wizardFormatState ?><?= $setupActiveStep === 'format' ? ' is-active' : '' ?>" id="step-format" data-step-panel="format" aria-labelledby="step-format-title">
    <div class="lsp-step-summary">
        <span class="lsp-step-index"><?= $wizardStepNumber('format') ?></span>
        <span class="lsp-step-heading">
            <span class="lsp-step-title" id="step-format-title">Format</span>
            <span class="lsp-step-detail"><?= htmlspecialchars($wizardFormatTitle) ?></span>
        </span>
        <span class="lsp-step-status <?= $wizardFormatState ?>"><?= $wizardFormatStatus ?></span>
    </div>
    <div class="lsp-step-body">
        <p class="lsp-lead">Select a format, adjust its settings, then press Next. This creates the stages for you.</p>

        <?php if ($stages): ?>
            <div class="lsp-simple-summary">
                <span class="lsp-simple-summary-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
                <span>
                    <b><?= htmlspecialchars($wizardFormatTitle) ?></b>
                    <small><?= count($stages) ?> stage<?= count($stages) === 1 ? '' : 's' ?><?= $matchCount > 0 ? ' · matches generated' : '' ?></small>
                </span>
            </div>
            <?php if (!$wizardCan('choose_structure')): ?>
                <p class="lsp-help">The format is locked because active matches exist.</p>
            <?php else: ?>
                <details class="lsp-disclosure">
                    <summary>Change format or stage settings</summary>
                    <div class="lsp-disclosure-body">
                        <p class="lsp-help">Changing the card replaces the current stages. Keeping the card and pressing <b>Save stage settings</b> updates the promotion counts; changing the number of groups rebuilds the group skeleton and re-draws the approved teams. Teams are kept; group placements are reset only when the group layout is rebuilt or the skeleton is replaced.</p>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$stages || $wizardCan('choose_structure')): ?>
            <?php $wizardCurrentGroupCount = 0; if ($stages) { $wizardGroupStageRows = array_values($stages); $wizardCurrentGroupCount = count($wizardGroupStageRows[0]['groups'] ?? []); } ?>
            <form method="post" class="lsp-format-form-setup" id="formatStructureForm"
                  data-current-key="<?= htmlspecialchars((string) ($setupStructureKey ?? ''), ENT_QUOTES) ?>"
                  data-has-stages="<?= $stages ? '1' : '0' ?>"
                  data-current-groups="<?= (int) $wizardCurrentGroupCount ?>"
                  data-replace-confirm="Replace the current stages? Teams are kept; group placements and team progression will be reset."
                  data-group-rebuild-confirm="Changing the number of groups rebuilds the group stage and re-draws the approved teams. Team progression will also be reset. Continue?"
                  data-extend-confirm="Add the Playoffs stage? Your League Stage, teams and team placements are kept. Team progression will be reset.">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                <input type="hidden" name="applyStructure" value="1">
                <div class="lsp-format-grid" role="radiogroup" aria-label="Competition format">
                    <?php foreach ($wizardFormatCards as $wizardFormatCard):
                        $wizardCardCurrent = !empty($wizardFormatCard['current']);
                        $wizardCardChecked = (string) $wizardFormatCard['key'] === $wizardFormatCheckedKey;
                    ?>
                        <label class="lsp-format-card<?= $wizardCardCurrent ? ' is-current' : '' ?><?= !empty($wizardFormatCard['recommended']) ? ' is-recommended' : '' ?><?= $wizardCardChecked ? ' is-selected' : '' ?>">
                            <input type="radio" name="structure_key"
                                   value="<?= htmlspecialchars((string) $wizardFormatCard['key'], ENT_QUOTES) ?>"
                                   class="lsp-format-radio"
                                   <?= $wizardCardChecked ? 'checked' : '' ?>>
                            <span class="lsp-format-icon"><i class="bi <?= htmlspecialchars($wizardFormatCard['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i></span>
                            <span class="lsp-format-copy">
                                <b><?= htmlspecialchars($wizardFormatCard['title']) ?></b>
                                <small><?= htmlspecialchars($wizardFormatCard['text']) ?></small>
                                <?php if (!empty($wizardFormatCard['flow'])): ?>
                                    <span class="lsp-format-flow" aria-hidden="true">
                                        <?php foreach ($wizardFormatCard['flow'] as $wizardFlowIndex => $wizardFlowStep): ?>
                                            <?php if ($wizardFlowIndex > 0): ?><i class="bi bi-arrow-right"></i><?php endif; ?>
                                            <em><?= htmlspecialchars((string) $wizardFlowStep, ENT_QUOTES) ?></em>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php if ($wizardCardCurrent): ?><span class="lsp-format-mark" title="Currently applied"><i class="bi bi-check-lg" aria-hidden="true"></i></span><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($wizardFormatCards as $wizardFormatCard):
                    $wizardSettingsKey = (string) $wizardFormatCard['key'];
                    $wizardSettingsFields = $wizardEditableSettings[$wizardSettingsKey] ?? [];
                    if (!$wizardSettingsFields) continue;
                    $wizardSettingsChecked = $wizardSettingsKey === $wizardFormatCheckedKey;
                    $wizardSettingsValues = $wizardSettingValues($wizardSettingsKey);
                    $wizardPresetStages = $wizardPresetDefinitions[$wizardSettingsKey]['stages'] ?? [];
                ?>
                    <div class="lsp-format-settings<?= $wizardSettingsChecked ? ' is-active' : '' ?>"
                         data-format-settings="<?= htmlspecialchars($wizardSettingsKey, ENT_QUOTES) ?>">
                        <div class="lsp-format-settings-head">
                            <b>Format settings</b>
                            <small>Stored on the stages; you can change them until active matches exist.</small>
                        </div>
                        <?php foreach ($wizardSettingsFields as $wizardSettingsField):
                            $wizardFieldStage = (int) $wizardSettingsField['stage'];
                            $wizardFieldName = (string) $wizardSettingsField['field'];
                            $wizardFieldLabel = (string) $wizardSettingsField['label'];
                            $wizardNextStageName = (string) ($wizardPresetStages[$wizardFieldStage + 1]['name'] ?? '');
                            if ($wizardFieldName === 'qualifiers' && $wizardNextStageName !== '') {
                                $wizardFieldLabel .= ' — into ' . $wizardNextStageName;
                            }
                            $wizardFieldId = 'lspSetting_' . $wizardSettingsKey . '_' . $wizardFieldStage . '_' . $wizardFieldName;
                            $wizardFieldValue = (int) ($wizardSettingsValues[$wizardFieldStage][$wizardFieldName] ?? $wizardSettingsField['default']);
                        ?>
                            <div class="lsp-setting-row">
                                <label for="<?= htmlspecialchars($wizardFieldId, ENT_QUOTES) ?>"><?= htmlspecialchars($wizardFieldLabel) ?></label>
                                <input type="number" class="form-control"
                                       id="<?= htmlspecialchars($wizardFieldId, ENT_QUOTES) ?>"
                                       name="settings[<?= $wizardFieldStage ?>][<?= htmlspecialchars($wizardFieldName, ENT_QUOTES) ?>]"
                                       min="<?= (int) $wizardSettingsField['min'] ?>"
                                       max="<?= (int) $wizardSettingsField['max'] ?>"
                                       value="<?= $wizardFieldValue ?>"
                                       data-format-setting
                                       <?= $wizardSettingsChecked ? '' : 'disabled' ?>>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($wizardSettingsKey === 'groups_knockout'): ?>
                            <?php if ($wizardGroupRecommendation && (int) $wizardGroupRecommendation['team_count'] > 0): ?>
                                <?php if (!empty($wizardGroupRecommendation['warning'])): ?>
                                    <div class="lsp-recommendation is-warning">
                                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                        <span><?= htmlspecialchars((string) $wizardGroupRecommendation['warning']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="lsp-recommendation"
                                         data-group-recommendation
                                         data-groups="<?= (int) $wizardGroupRecommendation['groups'] ?>"
                                         data-qualifiers="<?= (int) $wizardGroupRecommendation['qualifiers'] ?>">
                                        <div class="lsp-recommendation-copy">
                                            <b>Recommended for <?= (int) $wizardGroupRecommendation['team_count'] ?> <?= $approvedTeamCount > 0 ? 'approved teams' : 'team slots' ?></b>
                                            <small>
                                                <?= (int) $wizardGroupRecommendation['groups'] ?> groups of <?= implode('/', array_map('intval', $wizardGroupRecommendation['group_sizes'])) ?>
                                                · top <?= (int) $wizardGroupRecommendation['qualifiers'] ?> per group
                                                → <?= (int) $wizardGroupRecommendation['total_qualifiers'] ?> qualifiers
                                                · <?= (int) $wizardGroupRecommendation['bracket_size'] ?>-team bracket<?= (int) $wizardGroupRecommendation['byes'] > 0 ? ' (' . (int) $wizardGroupRecommendation['byes'] . ' byes)' : '' ?>
                                            </small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-use-group-recommendation>
                                            <i class="bi bi-stars me-1" aria-hidden="true"></i>Use recommendation
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="lsp-help">Approve teams (or set a team limit) to get a group recommendation.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($stages && $wizardCan('choose_structure') && !empty($wizardEditableSettings[$wizardFormatCheckedKey] ?? [])): ?>
                    <div class="lsp-format-settings-actions">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-check2 me-1" aria-hidden="true"></i>Save stage settings
                        </button>
                        <small>Same card keeps the stages; changing the group count re-draws the groups. Another card replaces the skeleton.</small>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($stages && $matchCount === 0): ?>
                    </div>
                </details>
        <?php endif; ?>

        <?php $wizardFormatNext = $wizardStepAfter('format'); ?>
        <div class="lsp-step-nav">
            <?php if (!$stages || $wizardCan('choose_structure')): ?>
                <button type="submit" form="formatStructureForm"
                        class="btn btn-primary lsp-step-next"
                        data-format-next
                        data-next-step="<?= htmlspecialchars($wizardFormatNext, ENT_QUOTES) ?>">
                    Next: <?= htmlspecialchars($wizardStepLabel($wizardFormatNext)) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                </button>
            <?php else: ?>
                <a class="btn btn-primary lsp-step-next" href="?id=<?= (int) $id ?>&amp;step=<?= rawurlencode($wizardFormatNext) ?>#step-<?= rawurlencode($wizardFormatNext) ?>" data-lsp-step-go="<?= htmlspecialchars($wizardFormatNext, ENT_QUOTES) ?>">Next: <?= htmlspecialchars($wizardStepLabel($wizardFormatNext)) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
/* Manual group draw (Teams step)
   ------------------------------
   Only the OPENING group/crossover stage can be drawn by hand. Later group
   stages receive their teams through Advance qualifiers, so a draw board there
   would fight the progression engine. The board is also read-only once the
   stage has fixtures, because moving teams would leave those fixtures stale. */
$wizardDrawStage      = $wizardRoundRobin;
$wizardDrawGroups     = $wizardDrawStage ? array_values($wizardDrawStage['groups'] ?? []) : [];
$wizardDrawStageId    = $wizardDrawStage ? (int) $wizardDrawStage['id'] : 0;
$wizardDrawStats      = $wizardDrawStage
    ? $wizardStageStats($wizardDrawStageId)
    : ['matches' => 0, 'completed' => 0, 'complete' => false, 'percent' => 0];
$wizardDrawLocked     = $wizardDrawStats['matches'] > 0;
$wizardDrawIsOpening  = $wizardDrawStage !== null
    && isset($stages[0])
    && (int) ($stages[0]['id'] ?? 0) === $wizardDrawStageId;
$wizardDrawUnplaced   = $wizardDrawStage ? array_diff_key($stageTeamNames, $wizardPlacedIds) : [];

/* One team card, shared by the unassigned pool and every group container.
   The move menu doubles as the touch/keyboard alternative to dragging. */
$wizardTeamCard = static function (
    int $teamId,
    string $teamName,
    $seed,
    int $currentGroupId,
    array $groupChoices,
    bool $locked
): string {
    $escapedName = htmlspecialchars($teamName, ENT_QUOTES);
    $card = '<div class="lsp-team-card" data-lsp-team-card data-team-id="' . $teamId
        . '" data-team-name="' . $escapedName . '"' . ($locked ? '' : ' draggable="true"') . '>';
    if (!$locked) {
        $card .= '<i class="bi bi-grip-vertical lsp-team-grip" aria-hidden="true"></i>';
    }
    $card .= '<span class="lsp-team-name">' . $escapedName . '</span>';
    if ($seed !== null && $seed !== '') {
        $card .= '<span class="lsp-team-seed">#' . (int) $seed . '</span>';
    }
    if (!$locked) {
        $card .= '<details class="lsp-team-move" draggable="false">'
            . '<summary aria-label="Move ' . $escapedName . ' to another group" title="Move to another group">'
            . '<i class="bi bi-arrow-down-up" aria-hidden="true"></i></summary>'
            . '<div class="lsp-team-move-menu">';
        foreach ($groupChoices as $groupChoice) {
            $choiceId = (int) ($groupChoice['id'] ?? 0);
            if ($choiceId <= 0 || $choiceId === $currentGroupId) continue;
            $choiceName = htmlspecialchars((string) ($groupChoice['name'] ?? ('Group #' . $choiceId)), ENT_QUOTES);
            $card .= '<button type="button" data-lsp-move-team="' . $teamId
                . '" data-lsp-move-target="' . $choiceId . '">' . $choiceName . '</button>';
        }
        if ($currentGroupId !== 0) {
            $card .= '<button type="button" data-lsp-move-team="' . $teamId
                . '" data-lsp-move-target="0">Unassigned</button>';
        }
        $card .= '</div></details>';
    }
    $card .= '</div>';
    return $card;
};
?>

<?php [$wizardTeamsState, $wizardTeamsStatus] = $wizardState('teams'); ?>
<!-- ==================== STEP 2 · TEAMS ==================== -->
<section class="lsp-step-card <?= $wizardTeamsState ?><?= $setupActiveStep === 'teams' ? ' is-active' : '' ?>" id="step-teams" data-step-panel="teams" aria-labelledby="step-teams-title">
    <div class="lsp-step-summary">
        <span class="lsp-step-index"><?= $wizardStepNumber('teams') ?></span>
        <span class="lsp-step-heading">
            <span class="lsp-step-title" id="step-teams-title">Teams</span>
            <span class="lsp-step-detail" data-lsp-draw-detail>
                <?= $wizardRoundRobin
                    ? $wizardPlacedCount . ' of ' . $approvedTeamCount . ' placed'
                    : $approvedTeamCount . ' approved team' . ($approvedTeamCount === 1 ? '' : 's') ?>
            </span>
        </span>
        <span class="lsp-step-status <?= $wizardTeamsState ?>"><?= $wizardTeamsStatus ?></span>
    </div>
    <div class="lsp-step-body">
        <p class="lsp-lead"><?= $wizardDrawIsOpening && $wizardDrawGroups
            ? ($wizardDrawLocked
                ? 'The draw is locked because this stage already has fixtures.'
                : 'Drag each team into the group it should play in. Every drop saves automatically.')
            : 'Approved teams go into the opening group.' ?></p>

        <?php if (!$stages): ?>
            <div class="lsp-empty">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                <b>Choose a format first</b>
                <small>The format decides whether teams play a round robin or go straight into a bracket.</small>
                <a class="btn btn-primary" href="?id=<?= (int) $id ?>&amp;step=format#step-format" data-lsp-step-go="format">Choose a format</a>
            </div>
        <?php elseif ($approvedTeamCount === 0): ?>
            <div class="lsp-empty">
                <i class="bi bi-people" aria-hidden="true"></i>
                <b>No approved teams yet</b>
                <small>Approve the team registrations first, then come back to this step.</small>
                <a class="btn btn-primary" href="pending_teams.php?id=<?= (int) $id ?>">Review teams</a>
            </div>
        <?php elseif (!$wizardRoundRobin): ?>
            <div class="lsp-note">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <span><b>No group draw needed.</b> The bracket will seed all <?= $approvedTeamCount ?> approved team<?= $approvedTeamCount === 1 ? '' : 's' ?> when you generate it.</span>
            </div>
        <?php else: ?>
            <div class="lsp-simple-summary">
                <span class="lsp-simple-summary-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
                <span class="lsp-simple-summary-copy">
                    <b data-lsp-draw-summary><?= $wizardPlacedCount ?> of <?= $approvedTeamCount ?> teams placed</b>
                    <small data-lsp-draw-hint><?= $wizardPlacedCount >= $approvedTeamCount
                        ? 'Teams are ready.'
                        : ($wizardDrawIsOpening
                            ? ($wizardDrawLocked ? 'The draw is locked.' : 'Drag a team into a group below, or fill automatically.')
                            : 'Teams for this stage are advanced from the previous stage.') ?></small>
                </span>
                <span class="lsp-simple-summary-value" data-lsp-draw-percent><?= $wizardTeamPercent ?>%</span>
            </div>
            <div class="lp-progress lsp-simple-progress" role="img" aria-label="<?= $wizardPlacedCount ?> of <?= $approvedTeamCount ?> teams placed" data-lsp-draw-progress-wrap>
                <span style="width:<?= $wizardTeamPercent ?>%" data-lsp-draw-progress></span>
            </div>

            <?php if ($wizardDrawIsOpening && $wizardPlacedCount === 0 && !$wizardDrawLocked && !empty($wizardRoundRobin['groups'])): ?>
                <form method="post" class="lsp-actions">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="stage_id" value="<?= (int) $wizardRoundRobin['id'] ?>">
                    <button name="seedStageTeams" class="btn btn-primary"><i class="bi bi-shuffle me-1" aria-hidden="true"></i>Fill the group automatically</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($wizardRoundRobin['groups'])): ?>
                <?php if (!$wizardDrawIsOpening): ?>
                    <div class="lsp-note">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <span><b>This group stage is fed by the previous stage.</b> Place its teams with <b>Advance qualifiers</b> on the Matches step — a manual draw here would overwrite the progression.</span>
                    </div>
                    <div class="lsp-simple-groups">
                        <?php foreach ($wizardRoundRobin['groups'] as $wizardGroupRow): ?>
                            <span class="lsp-group-chip">
                                <b><?= htmlspecialchars((string) $wizardGroupRow['name']) ?></b>
                                <small><?= count($wizardGroupRow['teams'] ?? []) ?> team<?= count($wizardGroupRow['teams'] ?? []) === 1 ? '' : 's' ?></small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php if ($wizardDrawLocked): ?>
                        <div class="lsp-note">
                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            <span><b>Manual grouping is locked.</b> <?= (int) $wizardDrawStats['matches'] ?> fixture<?= $wizardDrawStats['matches'] === 1 ? '' : 's' ?> already exist in this stage. Archive or delete them on <a href="schedule.php?id=<?= (int) $id ?>">Schedule</a> before changing the draw.</span>
                        </div>
                    <?php endif; ?>

                    <div class="lsp-draw" data-lsp-draw data-stage-id="<?= (int) $wizardDrawStageId ?>" data-locked="<?= $wizardDrawLocked ? '1' : '0' ?>">
                        <div class="lsp-draw-head">
                            <b>Manual grouping</b>
                            <span class="lsp-help"><?= $wizardDrawLocked
                                ? 'Read-only: this stage already has fixtures.'
                                : 'Drag a team card into a group. The draw saves automatically.' ?></span>
                            <span class="lsp-draw-status" data-lsp-draw-status role="status" aria-live="polite"></span>
                        </div>

                        <section class="lsp-draw-pool" data-lsp-draw-pool data-stage-group-id="0" aria-label="Unassigned teams">
                            <div class="lsp-draw-group-head">
                                <b>Unassigned</b>
                                <span class="lsp-group-count" data-lsp-group-count="0"><?= count($wizardDrawUnplaced) ?></span>
                            </div>
                            <div class="lsp-draw-list" data-lsp-draw-list>
                                <?php foreach ($wizardDrawUnplaced as $wizardUnplacedId => $wizardUnplacedName): ?>
                                    <?= $wizardTeamCard((int) $wizardUnplacedId, (string) $wizardUnplacedName, null, 0, $wizardDrawGroups, $wizardDrawLocked) ?>
                                <?php endforeach; ?>
                                <p class="lsp-draw-empty" data-lsp-draw-empty <?= $wizardDrawUnplaced ? 'hidden' : '' ?>>Drop a team here to remove it from a group.</p>
                            </div>
                        </section>

                        <div class="lsp-draw-groups">
                            <?php foreach ($wizardDrawGroups as $wizardGroupRow): ?>
                                <section class="lsp-draw-group" data-lsp-draw-group data-stage-group-id="<?= (int) $wizardGroupRow['id'] ?>" aria-label="<?= htmlspecialchars((string) $wizardGroupRow['name'], ENT_QUOTES) ?>">
                                    <div class="lsp-draw-group-head">
                                        <b><?= htmlspecialchars((string) $wizardGroupRow['name']) ?></b>
                                        <span class="lsp-group-count" data-lsp-group-count="<?= (int) $wizardGroupRow['id'] ?>"><?= count($wizardGroupRow['teams'] ?? []) ?></span>
                                    </div>
                                    <div class="lsp-draw-list" data-lsp-draw-list>
                                        <?php foreach (($wizardGroupRow['teams'] ?? []) as $wizardMember): ?>
                                            <?= $wizardTeamCard((int) $wizardMember['team_id'], (string) $wizardMember['team_name'], $wizardMember['seed'] ?? null, (int) $wizardGroupRow['id'], $wizardDrawGroups, $wizardDrawLocked) ?>
                                        <?php endforeach; ?>
                                        <p class="lsp-draw-empty" data-lsp-draw-empty <?= !empty($wizardGroupRow['teams']) ? 'hidden' : '' ?>>Drop a team here</p>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!$wizardDrawLocked): ?>
                        <noscript><p class="lsp-help">Enable JavaScript to drag teams between groups, or use “Fill the group automatically” above.</p></noscript>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <p class="lsp-help">This stage has no group yet. Re-apply a group format in Step 1.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php $wizardTeamsPrev = $wizardStepBefore('teams'); $wizardTeamsNext = $wizardStepAfter('teams'); ?>
        <div class="lsp-step-nav">
            <a class="lp-link-btn" href="?id=<?= (int) $id ?>&amp;step=<?= rawurlencode($wizardTeamsPrev) ?>#step-<?= rawurlencode($wizardTeamsPrev) ?>" data-lsp-step-go="<?= htmlspecialchars($wizardTeamsPrev, ENT_QUOTES) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Previous: <?= htmlspecialchars($wizardStepLabel($wizardTeamsPrev)) ?></a>
            <a class="btn btn-primary lsp-step-next" href="?id=<?= (int) $id ?>&amp;step=<?= rawurlencode($wizardTeamsNext) ?>#step-<?= rawurlencode($wizardTeamsNext) ?>" data-lsp-step-go="<?= htmlspecialchars($wizardTeamsNext, ENT_QUOTES) ?>">Next: <?= htmlspecialchars($wizardStepLabel($wizardTeamsNext)) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
    </div>
</section>

<?php [$wizardMatchesState, $wizardMatchesStatus] = $wizardState('matches'); ?>
<!-- ==================== STEP 3 · MATCHES ==================== -->
<section class="lsp-step-card <?= $wizardMatchesState ?><?= $setupActiveStep === 'matches' ? ' is-active' : '' ?>" id="step-matches" data-step-panel="matches" aria-labelledby="step-matches-title">
    <div class="lsp-step-summary">
        <span class="lsp-step-index"><?= $wizardStepNumber('matches') ?></span>
        <span class="lsp-step-heading">
            <span class="lsp-step-title" id="step-matches-title">Matches</span>
            <span class="lsp-step-detail">
                <?= $wizardTaskStats['matches'] > 0
                    ? $wizardTaskStats['completed'] . ' of ' . $wizardTaskStats['matches'] . ' played'
                    : 'Not generated yet' ?>
            </span>
        </span>
        <span class="lsp-step-status <?= $wizardMatchesState ?>"><?= $wizardMatchesStatus ?></span>
    </div>
    <div class="lsp-step-body">
       

        <?php if (!$stages): ?>
            <div class="lsp-empty">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                <b>Choose a format first</b>
                <small>The matches are generated from the stages created by the format.</small>
                <a class="btn btn-primary" href="?id=<?= (int) $id ?>&amp;step=format#step-format" data-lsp-step-go="format">Choose a format</a>
            </div>
        <?php else: ?>
            <?php
            $wizardTaskKey = (string) ($wizardNextTask['task'] ?? 'choose_structure');
            $wizardTaskNext = is_array($wizardNextTask['next_stage'] ?? null) ? $wizardNextTask['next_stage'] : null;
            $wizardTaskRound = (int) ($wizardNextTask['round'] ?? 0);
            $wizardTaskRoundsTotal = (int) ($wizardNextTask['rounds_total'] ?? 0);
            $wizardTaskStageType = $wizardTaskStage ? (string) ($wizardTaskStage['stage_type'] ?? '') : '';
            $wizardTaskStageFormat = $wizardTaskStage ? stageFormat($wizardTaskStage) : '';
            $wizardTaskPercent = (int) ($wizardTaskStats['percent'] ?? 0);
            if ($wizardTaskKey === 'generate_swiss_round' && $wizardTaskRoundsTotal > 0) {
                $wizardTaskPercent = (int) round(min(1, max(0, $wizardTaskRound - 1) / $wizardTaskRoundsTotal) * 100);
            }
            $wizardTaskStageName = $wizardTaskStage ? (string) ($wizardTaskStage['name'] ?? 'Stage') : 'Stage';

            /* The stage before the current one decides how a later knockout is
               drawn: a multi-group group stage feeds one seeded bracket, while a
               crossover stage keeps its pool-based playoff patterns. */
            $wizardTaskStageIndex = null;
            foreach (array_values($stages) as $wizardStageIndex => $wizardStageRow) {
                if ((int) ($wizardStageRow['id'] ?? 0) === $wizardTaskStageId) { $wizardTaskStageIndex = $wizardStageIndex; break; }
            }
            $wizardStageRows = array_values($stages);
            $wizardTaskPreviousStage = ($wizardTaskStageIndex !== null && $wizardTaskStageIndex > 0)
                ? $wizardStageRows[$wizardTaskStageIndex - 1]
                : null;
            $wizardTaskSeededFromGroups = $wizardTaskStageType === 'knockout'
                && $wizardTaskPreviousStage !== null
                && (string) ($wizardTaskPreviousStage['stage_type'] ?? '') === 'group'
                && count($wizardTaskPreviousStage['groups'] ?? []) > 1;

            /* Advance form: carries the top N of a finished group/crossover
               stage into the next stage. Pool sources saved by the preset become
               the mapping, so Pools -> playoffs keeps Pool 1 <- Bracket A + D. */
            $wizardAdvanceForm = static function (array $source, array $target) use ($csrf, $id): string {
                $sourceGroups = array_values($source['groups'] ?? []);
                $targetGroups = array_values($target['groups'] ?? []);
                $sourceIndexById = [];
                foreach ($sourceGroups as $sourceIndex => $sourceGroup) {
                    $sourceIndexById[(int) ($sourceGroup['id'] ?? 0)] = $sourceIndex;
                }
                $mappingParts = [];
                $hasSources = false;
                foreach ($targetGroups as $targetIndex => $targetGroup) {
                    $declared = array_values($targetGroup['sources'] ?? []);
                    if (!$declared) { $mappingParts[] = (string) $targetIndex; continue; }
                    $hasSources = true;
                    $indexes = [];
                    foreach ($declared as $declaredId) {
                        if (isset($sourceIndexById[(int) $declaredId])) $indexes[] = $sourceIndexById[(int) $declaredId];
                    }
                    $mappingParts[] = $indexes ? implode(',', $indexes) : (string) $targetIndex;
                }
                $defaultPerGroup = max(1, (int) ($source['qualifiers_per_group'] ?? 2));
                $mappingHtml = $hasSources
                    ? '<input type="hidden" name="pool_mapping" value="' . htmlspecialchars(implode('|', $mappingParts), ENT_QUOTES) . '">'
                    : '';
                return '<form method="post" class="lsp-advance-form">'
                    . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES) . '">'
                    . '<input type="hidden" name="league_id" value="' . (int) $id . '">'
                    . '<input type="hidden" name="source_stage_id" value="' . (int) ($source['id'] ?? 0) . '">'
                    . '<input type="hidden" name="target_stage_id" value="' . (int) ($target['id'] ?? 0) . '">'
                    . $mappingHtml
                    . '<div class="lsp-setting-row"><label for="lspAdvancePerGroup">Top</label>'
                    . '<input id="lspAdvancePerGroup" name="per_group" type="number" min="1" max="64" value="' . $defaultPerGroup . '" class="form-control">'
                    . '<span>team(s) per group / pool advance</span></div>'
                    . '<label class="lsp-check"><input type="checkbox" name="eliminate_rest" value="1" checked> Eliminate the teams below the cut-off</label>'
                    . '<button name="promoteQualifiers" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Advance qualifiers</button>'
                    . '</form>';
            };
            ?>
            <div class="lsp-simple-summary">
                <span class="lsp-simple-summary-icon"><i class="bi <?= $wizardTaskKey === 'done' ? 'bi-trophy-fill' : 'bi-diagram-3-fill' ?>" aria-hidden="true"></i></span>
                <span class="lsp-simple-summary-copy">
                    <b><?= htmlspecialchars($wizardTaskStageName) ?></b>
                    <small>
                        <?php if ($wizardTaskKey === 'done'): ?>
                            Tournament complete
                        <?php elseif ($wizardTaskKey === 'generate_swiss_round'): ?>
                            Round <?= max(1, $wizardTaskRound - 1) ?> of <?= $wizardTaskRoundsTotal ?> complete
                        <?php elseif ($wizardTaskStats['matches'] === 0): ?>
                            Not generated yet
                        <?php else: ?>
                            <?= $wizardTaskStats['completed'] ?> of <?= $wizardTaskStats['matches'] ?> played
                        <?php endif; ?>
                    </small>
                </span>
                <span class="lsp-simple-summary-value"><?= $wizardTaskPercent ?>%</span>
            </div>
            <div class="lp-progress lsp-simple-progress" role="img" aria-label="Stage progress">
                <span style="width:<?= $wizardTaskPercent ?>%"></span>
            </div>

            <?php if (count($stages) > 1): ?>
                <div class="lsp-simple-groups">
                    <?php foreach ($stages as $wizardStageRow): $wizardStageRowStats = $wizardStageStats((int) $wizardStageRow['id']); ?>
                        <span class="lsp-group-chip">
                            <b><?= htmlspecialchars((string) $wizardStageRow['name']) ?></b>
                            <small><?= $wizardStageRowStats['matches'] === 0 ? 'Not generated' : $wizardStageRowStats['completed'] . '/' . $wizardStageRowStats['matches'] ?></small>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($wizardTaskKey === 'generate' && $wizardTaskStage): ?>
                <?php if ($wizardTaskStageFormat === 'swiss'): ?>
                    <p class="lsp-help">Generate round 1, let the results come in, then generate the next round from the standings.</p>
                    <form method="post" class="lsp-actions">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                        <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="stage_id" value="<?= (int) $wizardTaskStageId ?>">
                        <button name="generateSwissRound" class="btn btn-primary"><i class="bi bi-shuffle me-1" aria-hidden="true"></i>Generate Swiss round</button>
                    </form>
                <?php elseif ($wizardTaskSeededFromGroups): ?>
                    <p class="lsp-help">The qualifiers are placed. Draw the seeded bracket from every team in <?= htmlspecialchars($wizardTaskStageName) ?>.</p>
                    <form method="post" class="lsp-actions">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                        <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="stage_id" value="<?= (int) $wizardTaskStageId ?>">
                        <?php if ($wizardTaskStageFormat === 'double_elimination'): ?>
                            <label class="lsp-check lsp-generate-reset">
                                <input type="checkbox" name="bracket_reset" value="1"
                                    <?= function_exists('stageSetting') && !empty(stageSetting($wizardTaskStage, 'bracket_reset', 0)) ? 'checked' : '' ?>>
                                Bracket reset grand final
                                <small>If the losers-bracket team wins the first final, play a second one.</small>
                            </label>
                        <?php endif; ?>
                        <button name="generateSeededBracket" class="btn btn-primary"><i class="bi bi-diagram-2 me-1" aria-hidden="true"></i>Generate bracket</button>
                    </form>
                <?php else: ?>
                    <?= $wizardGenerationForm($wizardTaskStage) ?>
                    <p class="lsp-help">
                        <?= !empty($wizardNextTask['auto_seeds'])
                            ? 'The approved teams are drawn into the opening groups automatically.'
                            : '' ?>
                    </p>
                <?php endif; ?>
            <?php elseif ($wizardTaskKey === 'generate_swiss_round' && $wizardTaskStage): ?>
                <p class="lsp-help">Round <?= $wizardTaskRound ?> of <?= $wizardTaskRoundsTotal ?> is ready to draw. The current round must be complete.</p>
                <form method="post" class="lsp-actions">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="stage_id" value="<?= (int) $wizardTaskStageId ?>">
                    <button name="generateSwissRound" class="btn btn-primary"><i class="bi bi-shuffle me-1" aria-hidden="true"></i>Generate round <?= $wizardTaskRound ?></button>
                </form>
            <?php elseif ($wizardTaskKey === 'await_results' && $wizardTaskStage): ?>
                <p class="lsp-help">Enter the results on the Schedule page. This step updates automatically.</p>
                <a class="btn btn-primary" href="schedule.php?id=<?= (int) $id ?>"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Open Schedule</a>
            <?php elseif ($wizardTaskKey === 'advance' && $wizardTaskStage && $wizardTaskNext): ?>
                <p class="lsp-help"><?= htmlspecialchars($wizardTaskStageName) ?> is complete. Carry its qualifiers into <?= htmlspecialchars((string) ($wizardTaskNext['name'] ?? 'the next stage')) ?>.</p>
                <?= $wizardAdvanceForm($wizardTaskStage, $wizardTaskNext) ?>
            <?php elseif ($wizardTaskKey === 'place_teams' && $wizardTaskStage): ?>
                <p class="lsp-help"><?= htmlspecialchars($wizardTaskStageName) ?> needs teams. Place them on the Teams step or advance them from the previous stage.</p>
                <a class="btn btn-primary" href="?id=<?= (int) $id ?>&amp;step=teams#step-teams" data-lsp-step-go="teams"><i class="bi bi-people me-1" aria-hidden="true"></i>Open Teams</a>
            <?php elseif ($wizardTaskKey === 'add_groups' && $wizardTaskStage): ?>
                <p class="lsp-help"><?= htmlspecialchars($wizardTaskStageName) ?> has no group. Re-apply a group format in Step 1.</p>
                <a class="btn btn-primary" href="?id=<?= (int) $id ?>&amp;step=format#step-format" data-lsp-step-go="format">Change format</a>
            <?php elseif ($wizardTaskKey === 'advance_and_next'): ?>
                <?php if ($wizardTaskStage && in_array((string) ($wizardTaskStage['stage_type'] ?? ''), ['group', 'crossover'], true) && count($wizardTaskStage['groups'] ?? []) === 1):
                    $wizardTaskTeamCount = count($wizardStageTeamIds($wizardTaskStage));
                    $wizardTaskPlayoffDefault = max(2, min(8, $wizardTaskTeamCount ?: 2));
                ?>
                    <p class="lsp-help">The stage is complete. Choose how many teams advance; a seeded bracket is created automatically.</p>
                    <form method="post" class="lsp-playoff-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                        <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="source_stage_id" value="<?= (int) $wizardTaskStageId ?>">
                        <label for="playoffTeams">Top</label>
                        <input id="playoffTeams" name="playoff_teams" type="number" min="2" max="<?= max(2, $wizardTaskTeamCount) ?>" value="<?= $wizardTaskPlayoffDefault ?>" class="form-control">
                        <span>teams advance</span>
                        <button name="createPlayoffs" class="btn btn-primary"><i class="bi bi-trophy me-1" aria-hidden="true"></i>Create playoffs</button>
                    </form>
                <?php else: ?>
                    <p class="lsp-simple-done"><i class="bi bi-trophy-fill" aria-hidden="true"></i> Tournament complete.</p>
                    <a class="btn btn-primary" href="standing.php?id=<?= (int) $id ?>">Open Standings</a>
                <?php endif; ?>
            <?php elseif ($wizardTaskKey === 'done'): ?>
                <p class="lsp-simple-done"><i class="bi bi-trophy-fill" aria-hidden="true"></i> Tournament complete.</p>
                <a class="btn btn-primary" href="standing.php?id=<?= (int) $id ?>">Open Standings</a>
            <?php elseif ($wizardTaskKey === 'choose_structure'): ?>
                <p class="lsp-help">Choose a format first.</p>
                <a class="btn btn-primary" href="?id=<?= (int) $id ?>&amp;step=format#step-format" data-lsp-step-go="format">Choose a format</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php $wizardMatchesPrev = $wizardStepBefore('matches'); ?>
        <div class="lsp-step-nav">
            <a class="lp-link-btn" href="?id=<?= (int) $id ?>&amp;step=<?= rawurlencode($wizardMatchesPrev) ?>#step-<?= rawurlencode($wizardMatchesPrev) ?>" data-lsp-step-go="<?= htmlspecialchars($wizardMatchesPrev, ENT_QUOTES) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Previous: <?= htmlspecialchars($wizardStepLabel($wizardMatchesPrev)) ?></a>
        </div>
    </div>
</section>
</div><!-- /.lsp-steps-wrap -->

