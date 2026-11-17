<?php
/**
 * Setup page — manual editor (kept for reference, not included)
 * -------------------------------------------------------------
 * NOT INCLUDED by setup.php any more: the collapsed "More options" section was
 * removed from the Setup page so the wizard stays Format -> Teams -> Matches.
 * The file is kept on disk as the reference for the manual tools (pipeline,
 * pools, stages, promotion) so pieces can be moved into the guided flow later.
 *
 * Original purpose: compact manual editor for the edge cases the 3-step wizard
 * does not cover:
 * a structure pipeline, a quick stage builder (stage + groups in one form),
 * collapsible per-stage group/team management, standings pools and qualifier
 * promotion. Every POST action name is unchanged (toggleGrouping / addGroup /
 * deleteGroup / addStageWithGroups / updateStageDetails / deleteStage /
 * addStageGroup / deleteStageGroup / assignStageTeam / promoteQualifiers), so
 * setup.php keeps handling submissions exactly as before.
 *
 * Expected variables (from setup.php): $id, $csrf, $groups, $groupingOn,
 * $groupCount, $stages, $stageTypes, $stageTeamNames, $stageGroupNames,
 * $leagueScoringRules, $stageCount, $stageGroupCount, $stageAssignedTeamCount,
 * $approvedTeamCount, $matchCount, $setupStructureKey, $setupStructureIsPreset.
 *
 * This is an include partial, so those variables are created by setup.php and
 * are unknown to this file on its own. The guard block below coalesces them:
 * the partial then renders with harmless defaults instead of "undefined
 * variable" notices, and editors/static analysers stop flagging each of the
 * ~120 usages further down.
 */
$id                     = (int) ($id ?? 0);
$csrf                   = (string) ($csrf ?? '');
$groups                 = isset($groups) && is_array($groups) ? $groups : [];
$groupingOn             = (bool) ($groupingOn ?? false);
$groupCount             = (int) ($groupCount ?? count($groups));
$stages                 = isset($stages) && is_array($stages) ? $stages : [];
$stageTypes             = $stageTypes ?? null;
$stageTypes             = is_array($stageTypes) && $stageTypes
    ? $stageTypes
    : (function_exists('stageTypeLabels') ? stageTypeLabels() : []);
$stageTeamNames         = isset($stageTeamNames) && is_array($stageTeamNames) ? $stageTeamNames : [];
$stageGroupNames        = isset($stageGroupNames) && is_array($stageGroupNames) ? $stageGroupNames : [];
$initialLeagueScoringRules = $leagueScoringRules ?? null;
$leagueScoringRules     = is_array($initialLeagueScoringRules)
    ? $initialLeagueScoringRules
    : ['win' => 0, 'loss' => 0, 'tiebreaks' => []];
$stageCount             = (int) ($stageCount ?? count($stages));
$stageGroupCount        = (int) ($stageGroupCount ?? 0);
$stageAssignedTeamCount = (int) ($stageAssignedTeamCount ?? 0);
$approvedTeamCount      = (int) ($approvedTeamCount ?? count($stageTeamNames));
$matchCount             = (int) ($matchCount ?? 0);
$setupStructureKey      = $setupStructureKey ?? null;
$setupStructureIsPreset = (bool) ($setupStructureIsPreset ?? false);
$setupState             = isset($setupState) && is_array($setupState) ? $setupState : [];
$setupCapabilities      = is_array($setupState['capabilities'] ?? null) ? $setupState['capabilities'] : [];
/* Missing capabilities default to "allowed" so the partial still renders when
   it is included without the state array. */
$setupCan = static function (string $capability) use ($setupCapabilities): bool {
    return array_key_exists($capability, $setupCapabilities) ? (bool) $setupCapabilities[$capability] : true;
};

$setupStructureShort = [
    'round_robin'          => 'Round robin',
    'swiss_system'         => 'Swiss system',
    'round_robin_playoffs' => 'Round robin + playoffs',
    'single_elimination'   => 'Single elimination',
    'double_elimination'   => 'Double elimination',
    'groups_knockout'      => 'Groups → knockout',
    'pools_playoffs'       => 'Pools → crossover → playoffs',
    'custom'               => 'Custom',
];
$setupStructureShortLabel = $stageCount === 0
    ? 'Not set up yet'
    : ($setupStructureShort[$setupStructureKey] ?? 'Custom');


/* What the organizer should do next, in the words of the structure they chose.
   The first stage of the stage-based presets is seeded with the approved teams
   automatically (seedStageGroupsFromApprovedTeams), which is why setup can be
   skipped altogether. */
$setupNextStepByStructure = [
    'round_robin'          => 'Generate the round robin in the <em>Matches</em> step — <em>Group A</em> is seeded for you.',
    'swiss_system'         => 'Generate the first Swiss round in the <em>Matches</em> step; equal records are paired each round.',
    'round_robin_playoffs' => 'Generate the round robin, then use <em>Create playoffs</em> after the last league match.',
    'single_elimination'   => 'Generate the seeded bracket in the <em>Matches</em> step — byes are handled for you.',
    'double_elimination'   => 'Generate the double-elimination bracket in the <em>Matches</em> step; losers drop into the losers bracket.',
    'groups_knockout'      => 'Generate the group stage, then use <em>Advance qualifiers</em> to fill the knockout.',
    'pools_playoffs'       => 'Generate each bracket, then advance the qualifiers into the crossover pools.',
    'custom'               => 'Add a stage below, or build the matches by hand on the Schedule page.',
];
$setupNextStep = $stageCount === 0
    ? ($matchCount > 0
        ? 'Matches exist but no stages — add the stages below and tag the matches to them.'
        : 'Choose a structure in the <em>Format</em> step above, or add a custom stage below.')
    : ($setupNextStepByStructure[$setupStructureKey] ?? 'Adjust the stages below, then generate the matches in the Matches step above.');

/* One-line scoring summary for the Stages panel. The tiebreak labels come from
   config/scoringRules.php; the guard keeps the partial usable if that helper is
   not loaded and avoids a dangling "tiebreaks" when none are configured. */
$setupTiebreakLabels = [];
foreach (($leagueScoringRules['tiebreaks'] ?? []) as $setupTiebreakKey) {
    $setupTiebreakLabels[] = function_exists('tiebreakLabel')
        ? tiebreakLabel((string) $setupTiebreakKey)
        : (string) $setupTiebreakKey;
}
$setupScoringLine = 'Win ' . (int) ($leagueScoringRules['win'] ?? 0) . ' pt'
    . ' · loss ' . (int) ($leagueScoringRules['loss'] ?? 0) . ' pt';
if ($setupTiebreakLabels) $setupScoringLine .= ' · tiebreaks ' . implode(' → ', $setupTiebreakLabels);
?>

<!-- ==================== 2. STRUCTURE ==================== -->
<div class="activity-section dash-animate dash-animate-d1" id="structure">
    <div class="activity-header">
        <div class="activity-title">
            <i class="bi bi-diagram-2-fill"></i> Competition structure
            <span class="activity-badge-count"><?= htmlspecialchars($setupStructureShortLabel) ?></span>
        </div>
    </div>
    <div class="lp-section-body">
        <p class="lsp-lead">
            <?= $stages
                ? 'The pipeline below is exactly what the Schedule page will use.'
                : 'Start from a preset in the Format step above, or build a custom stage below.' ?>
        </p>
        <?php if ($stages): ?>
            <ol class="lsp-pipeline">
                <?php foreach ($stages as $pipeIndex => $pipeStage):
                    $pipeTeamIds = [];
                    foreach ($pipeStage['groups'] as $pipeGroup) {
                        foreach ($pipeGroup['teams'] as $pipeMember) $pipeTeamIds[(int) $pipeMember['team_id']] = true;
                    }
                    $pipeIsBracket = in_array($pipeStage['stage_type'], ['knockout', 'placement'], true);
                ?>
                    <?php if ($pipeIndex > 0): ?><li class="lsp-pipe-arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></li><?php endif; ?>
                    <li class="lsp-pipe-step">
                        <span class="lsp-pipe-head">
                            <span class="lsp-pipe-num"><?= $pipeIndex + 1 ?></span>
                            <b><?= htmlspecialchars($pipeStage['name']) ?></b>
                        </span>
                        <span class="lsp-pipe-type"><?= htmlspecialchars($stageTypes[$pipeStage['stage_type']] ?? $pipeStage['stage_type']) ?></span>
                        <span class="lsp-pipe-meta">
                            <?php if ($pipeStage['groups']): ?>
                                <?= count($pipeStage['groups']) ?> group<?= count($pipeStage['groups']) === 1 ? '' : 's' ?> · <?= count($pipeTeamIds) ?> team<?= count($pipeTeamIds) === 1 ? '' : 's' ?>
                            <?php elseif ($pipeIsBracket): ?>
                                Pairs up the approved teams
                            <?php else: ?>
                                Needs a group first
                            <?php endif; ?>
                            <?php if ((int) ($pipeStage['qualifiers_per_group'] ?? 0) > 0): ?> · top <?= (int) $pipeStage['qualifiers_per_group'] ?> advance<?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <div class="lsp-subhead">Format and stages</div>
        <div class="lsp-manual-tools">
            <?php if ($setupCan('choose_structure')): ?>
                <a class="btn btn-outline-primary" href="?id=<?= (int) $id ?>&amp;step=format#step-format"><i class="bi bi-sliders2 me-1" aria-hidden="true"></i>Change overall format</a>
            <?php endif; ?>
            <a class="lp-link-btn" href="#stages">Add or edit stages <i class="bi bi-arrow-down"></i></a>
        </div>
        <?php if ($matchCount > 0): ?>
            <div class="lsp-note">
                <i class="bi bi-lock-fill"></i>
                <span>Locked: this league already has <?= (int) $matchCount ?> match<?= $matchCount === 1 ? '' : 'es' ?>. The structure can only be changed while there are no active matches — archive or delete them on the Schedule page first, use <strong>Discard setup &amp; start over</strong> below, or edit the stages by hand.</span>
            </div>
        <?php elseif ($stages): ?>
            <div class="lsp-note">
                <i class="bi bi-info-circle-fill"></i>
                <span>Changing the overall format resets group placements. To fine-tune the current setup instead, add or edit stages below.</span>
            </div>
        <?php endif; ?>

        <?php if (!$setupStructureIsPreset && $stages): ?>
            <div class="lsp-note">
                <i class="bi bi-tools"></i>
                <span>These stages were changed by hand, so they no longer match a preset. The pipeline above is exactly what the Schedule page will use.</span>
            </div>
        <?php endif; ?>

        <div class="lsp-next">
            <i class="bi bi-arrow-right-circle-fill"></i>
            <span><b>Next step:</b> <?= $setupNextStep ?></span>
        </div>
    </div>
</div>

<!-- ==================== 3. STANDINGS POOLS ==================== -->
<div class="activity-section dash-animate dash-animate-d3" id="pools">
    <div class="activity-header">
        <div class="activity-title">
            <i class="bi bi-collection-fill"></i> Standings pools
            <span class="activity-badge-count"><?= $groupingOn ? ((int) $groupCount . ' pool' . ($groupCount === 1 ? '' : 's')) : 'Off' ?></span>
        </div>
        <div class="lsp-head-actions">
            <?php if ($groupingOn): ?>
                <form method="post" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="toggleGrouping" value="1">
                    <button type="submit" class="lp-link-btn"
                            data-lsp-confirm="Turn pools off? The pools are kept in the database but the Standings page will show one combined table again.">Turn off</button>
                </form>
                <a class="lp-link-btn" href="standing.php?id=<?= (int) $id ?>">Assign teams in Standings</a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poolModal">
                    <i class="bi bi-plus-lg me-1"></i>Add pool
                </button>
            <?php else: ?>
                <form method="post" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="toggleGrouping" value="1">
                    <input type="hidden" name="enabled" value="1">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-toggle-on me-1"></i>Turn on pools</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="lp-section-body">
        <p class="lsp-lead">
            Pools only change the <a href="standing.php?id=<?= (int) $id ?>">Standings</a> page.
            They are not the stage groups below.
        </p>
        <?php if (!$groupingOn): ?>
            <p class="lsp-hint lsp-muted-line"><i class="bi bi-dash-circle"></i> Pools are off — the standings page shows one combined table.</p>
        <?php elseif ($groups): ?>
            <div class="lp-group-list">
                <?php foreach ($groups as $g): ?>
                    <span class="lp-group-chip">
                        <span class="lp-group-order"><?= (int) $g['display_order'] ?></span>
                        <?= htmlspecialchars($g['name']) ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                            <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
                            <button name="deleteGroup" class="lp-group-remove" title="Remove pool" aria-label="Remove <?= htmlspecialchars($g['name'], ENT_QUOTES) ?>" data-lsp-confirm="Remove <?= htmlspecialchars($g['name'], ENT_QUOTES) ?>? Assigned teams will become ungrouped."><i class="bi bi-x-lg"></i></button>
                        </form>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="lsp-empty">
                <i class="bi bi-collection"></i>
                <b>No pools yet</b>
                <small>Create one pool per group of teams you want ranked separately — for example Group A and Group B.</small>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#poolModal">
                    <i class="bi bi-plus-lg me-1"></i>Add the first pool
                </button>
            </div>
        <?php endif; ?>
        <?php if ($groupingOn): ?>
            <div class="lsp-note">
                <i class="bi bi-info-circle-fill"></i>
                <span>Teams pick their pool on the <a href="standing.php?id=<?= (int) $id ?>">Standings</a> page, and a match counts toward a pool only when you tag it with that pool on the Schedule page.</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== 3b. STAGES ==================== -->
<div class="activity-section dash-animate dash-animate-d2" id="stages">
    <div class="activity-header">
        <div class="activity-title">
            <i class="bi bi-diagram-2-fill"></i> Stages
            <span class="activity-badge-count"><?= (int) $stageCount ?> stage<?= $stageCount === 1 ? '' : 's' ?></span>
        </div>
        <div class="lsp-head-actions">
            <?php if ($stageGroupCount > 0): ?>
                <span class="lsp-count"><i class="bi bi-people-fill"></i><?= (int) $stageAssignedTeamCount ?>/<?= (int) $approvedTeamCount ?> teams placed</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="lp-section-body">
        <div class="lsp-quick-stage" id="add-stage">
            <div class="lsp-quick-stage-head">
                <b><i class="bi bi-plus-square-fill" aria-hidden="true"></i> Add a stage</b>
                <small>Configure the phase once; group names are generated automatically.</small>
            </div>
            <form method="post" class="lsp-quick-stage-form row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                <div class="col-md-3">
                    <label class="form-label" for="lspQuickStageName">Stage name</label>
                    <input id="lspQuickStageName" name="stage_name" class="form-control" maxlength="80" list="lspStageNameSuggestions" placeholder="e.g. Group Stage" required>
                    <datalist id="lspStageNameSuggestions">
                        <option value="League Stage"></option>
                        <option value="Swiss Stage"></option>
                        <option value="Group Stage"></option>
                        <option value="Crossover / Pools"></option>
                        <option value="Playoffs"></option>
                        <option value="Losers Bracket"></option>
                        <option value="Battle for Third"></option>
                    </datalist>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lspQuickStageFormat">Format</label>
                    <select id="lspQuickStageFormat" name="stage_format" class="form-select" data-lsp-add-stage-format>
                        <option value="">Default for type</option>
                        <?php foreach (tournamentFormatOptions() as $quickFormatKey => $quickFormatLabel): ?>
                            <option value="<?= htmlspecialchars((string) $quickFormatKey, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $quickFormatLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lspQuickStageType">Type</label>
                    <select id="lspQuickStageType" name="stage_type" class="form-select" data-lsp-add-stage-type>
                        <?php foreach ($stageTypes as $quickTypeKey => $quickTypeLabel): ?>
                            <option value="<?= htmlspecialchars((string) $quickTypeKey, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $quickTypeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lspQuickStageGroups">Groups</label>
                    <input id="lspQuickStageGroups" name="group_count" type="number" min="0" max="8" value="<?= $stages ? 1 : 2 ?>" class="form-control" data-lsp-add-stage-groups>
                </div>
                <div class="col-md-1">
                    <label class="form-label" for="lspQuickStageAdvance">Advance</label>
                    <input id="lspQuickStageAdvance" name="qualifiers_per_group" type="number" min="0" max="64" value="2" class="form-control" data-lsp-add-stage-advance>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button name="addStageWithGroups" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Add stage</button>
                </div>
                <div class="col-12">
                    <div class="form-check lsp-check">
                        <input class="form-check-input" type="checkbox" name="carries_previous_results" value="1" id="lspQuickStageCarry">
                        <label class="form-check-label" for="lspQuickStageCarry">This stage's table also counts earlier stages' results</label>
                    </div>
                    <div class="form-text">Use 0 groups for a bracket. Teams advance from each group by the number you set here.</div>
                </div>
            </form>
        </div>

        <?php if (!$stages): ?>
            <div class="lsp-empty">
                <i class="bi bi-diagram-2"></i>
                <b>No stages yet</b>
                <small>Use the form above to add the first stage. A stage is one phase of the tournament, for example “Elimination”, “Pools”, then “Playoffs”.</small>
            </div>
        <?php else: ?>
            <p class="lsp-lead">
                <?= htmlspecialchars($setupScoringLine) ?>.
                Teams move forward through <a href="#qualify">Advance qualifiers</a>.
            </p>
            <ol class="lsp-stage-list">
            <?php foreach ($stages as $stageIndex => $stageRow):
                $stageRowId = (int) $stageRow['id'];
                $stageGroupRows = $stageRow['groups'];
                $stagePlacement = [];
                foreach ($stageGroupRows as $stageGroupRow) {
                    foreach ($stageGroupRow['teams'] as $stageMember) $stagePlacement[(int) $stageMember['team_id']] = $stageGroupRow['name'];
                }
                $stageMemberCount = count($stagePlacement);
            ?>
                <li class="lsp-stage">
                    <div class="lsp-stage-head">
                        <span class="lsp-stage-num"><?= $stageIndex + 1 ?></span>
                        <div class="lsp-stage-title">
                            <b><?= htmlspecialchars($stageRow['name']) ?></b>
                            <span class="lp-row-status is-neutral"><?= htmlspecialchars($stageTypes[$stageRow['stage_type']] ?? $stageRow['stage_type']) ?></span>
                            <span class="lp-row-status <?= $stageRow['complete'] ? 'is-approved' : 'is-pending' ?>"><?= $stageRow['complete'] ? 'Complete' : 'In progress' ?></span>
                            <?php if ((int) ($stageRow['carries_previous_results'] ?? 0) === 1): ?><span class="lp-row-status is-scheduled">Includes earlier results</span><?php endif; ?>
                            <span class="lsp-stage-sub">
                                <?= count($stageGroupRows) ?> group<?= count($stageGroupRows) === 1 ? '' : 's' ?> · <?= $stageMemberCount ?> team<?= $stageMemberCount === 1 ? '' : 's' ?>
                                <?php if (!empty($stageRow['qualifiers_per_group'])): ?> · top <?= (int) $stageRow['qualifiers_per_group'] ?> advance per group<?php endif; ?>
                            </span>
                        </div>
                        <form method="post" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                            <input type="hidden" name="stage_id" value="<?= $stageRowId ?>">
                            <button name="deleteStage" class="league-icon-btn lp-action-danger" title="Delete stage" aria-label="Delete stage <?= htmlspecialchars($stageRow['name'], ENT_QUOTES) ?>" data-lsp-confirm="Delete this stage, its groups and the team placements inside it?"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <div class="lsp-stage-body">
                        <details class="lsp-details lsp-stage-manage">
                            <summary>Manage stage, groups &amp; teams — <?= count($stageGroupRows) ?> group<?= count($stageGroupRows) === 1 ? '' : 's' ?> · <?= $stageMemberCount ?> team<?= $stageMemberCount === 1 ? '' : 's' ?></summary>
                            <div class="lsp-details-body">
                        <details class="lsp-details">
                            <summary>Edit stage settings</summary>
                            <div class="lsp-details-body">
                                <form method="post" class="row g-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                    <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                                    <input type="hidden" name="stage_id" value="<?= $stageRowId ?>">
                                    <div class="col-md-5">
                                        <label class="form-label" for="lspPanelStageName<?= $stageRowId ?>">Stage name</label>
                                        <input id="lspPanelStageName<?= $stageRowId ?>" name="stage_name" class="form-control" maxlength="80" value="<?= htmlspecialchars((string) $stageRow['name'], ENT_QUOTES) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="lspPanelStageType<?= $stageRowId ?>">Type</label>
                                        <select id="lspPanelStageType<?= $stageRowId ?>" name="stage_type" class="form-select">
                                            <?php foreach ($stageTypes as $panelTypeKey => $panelTypeLabel): ?>
                                                <option value="<?= htmlspecialchars((string) $panelTypeKey, ENT_QUOTES) ?>"<?= (string) $stageRow['stage_type'] === (string) $panelTypeKey ? ' selected' : '' ?>><?= htmlspecialchars((string) $panelTypeLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="lspPanelStageOrder<?= $stageRowId ?>">Order</label>
                                        <input id="lspPanelStageOrder<?= $stageRowId ?>" name="display_order" type="number" min="1" max="99" value="<?= max(1, (int) ($stageRow['display_order'] ?? $stageIndex + 1)) ?>" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="lspPanelStageQpg<?= $stageRowId ?>">Advancing</label>
                                        <input id="lspPanelStageQpg<?= $stageRowId ?>" name="qualifiers_per_group" type="number" min="0" max="64" value="<?= (int) ($stageRow['qualifiers_per_group'] ?? 0) ?>" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check lsp-check">
                                            <input class="form-check-input" type="checkbox" name="carries_previous_results" value="1" id="lspPanelStageCarry<?= $stageRowId ?>"<?= (int) ($stageRow['carries_previous_results'] ?? 0) === 1 ? ' checked' : '' ?>>
                                            <label class="form-check-label" for="lspPanelStageCarry<?= $stageRowId ?>">Carries previous results into this stage</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button name="updateStageDetails" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save stage settings</button>
                                    </div>
                                </form>
                            </div>
                        </details>
                        <?php if (!$stageGroupRows): ?>
                            <?php if (in_array($stageRow['stage_type'], ['knockout', 'placement'], true)): ?>
                                <div class="lsp-empty">
                                    <i class="bi bi-trophy"></i>
                                    <b>No groups needed</b>
                                    <small>This stage draws its pairings from the approved teams (or from the previous stage's qualifiers) when you generate it in the Matches step above. Add a group only if the pattern needs one — e.g. Finals F1 pairs A1 v B2.</small>
                                </div>
                            <?php else: ?>
                                <div class="lsp-empty">
                                    <i class="bi bi-inboxes"></i>
                                    <b>This stage has no groups yet</b>
                                    <small>Add a group or bracket below — for example Bracket A — then place the teams that play in it.</small>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="lsp-groups">
                            <?php foreach ($stageGroupRows as $groupRow):
                                $groupRowId = (int) $groupRow['id'];
                                $groupMemberIds = [];
                                foreach ($groupRow['teams'] as $stageMember) $groupMemberIds[(int) $stageMember['team_id']] = true;
                            ?>
                                <div class="lsp-group">
                                    <div class="lsp-group-head">
                                        <b><?= htmlspecialchars($groupRow['name']) ?></b>
                                        <span class="lsp-group-count"><?= count($groupRow['teams']) ?> team<?= count($groupRow['teams']) === 1 ? '' : 's' ?></span>
                                        <form method="post" class="m-0 ms-auto">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                                            <input type="hidden" name="stage_id" value="<?= $stageRowId ?>">
                                            <input type="hidden" name="stage_group_id" value="<?= $groupRowId ?>">
                                            <button name="deleteStageGroup" class="lp-group-remove" title="Remove group" aria-label="Remove group <?= htmlspecialchars($groupRow['name'], ENT_QUOTES) ?>" data-lsp-confirm="Remove this group? The teams stay approved in the league."><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    </div>
                                    <?php if ($groupRow['sources']): ?>
                                        <div class="lsp-group-sources"><i class="bi bi-arrow-down-right-circle"></i> Fed by: <?= htmlspecialchars(implode(', ', array_map(fn($sid) => ($stageGroupNames[$sid] ?? ('Group #' . $sid)), $groupRow['sources']))) ?></div>
                                    <?php endif; ?>
                                    <div class="lsp-group-chips">
                                        <?php foreach ($groupRow['teams'] as $stageMember): ?>
                                            <form method="post" class="m-0">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                                                <input type="hidden" name="stage_group_id" value="<?= $groupRowId ?>">
                                                <input type="hidden" name="team_id" value="<?= (int) $stageMember['team_id'] ?>">
                                                <input type="hidden" name="unassign" value="1">
                                                <span class="lp-group-chip">
                                                    <?php if (!empty($stageMember['seed'])): ?><span class="lp-group-order"><?= (int) $stageMember['seed'] ?></span><?php endif; ?>
                                                    <?= htmlspecialchars($stageMember['team_name']) ?>
                                                    <button name="assignStageTeam" class="lp-group-remove" title="Remove from this group" aria-label="Remove <?= htmlspecialchars($stageMember['team_name'], ENT_QUOTES) ?> from <?= htmlspecialchars($groupRow['name'], ENT_QUOTES) ?>"><i class="bi bi-x-lg"></i></button>
                                                </span>
                                            </form>
                                        <?php endforeach; ?>
                                        <?php if (!$groupRow['teams']): ?><span class="lp-groups-empty"><i class="bi bi-info-circle"></i>No teams in this group yet.</span><?php endif; ?>
                                    </div>
                                    <form method="post" class="lsp-assign">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                                        <input type="hidden" name="stage_group_id" value="<?= $groupRowId ?>">
                                        <select name="team_id" class="form-select" aria-label="Team to add to <?= htmlspecialchars($groupRow['name'], ENT_QUOTES) ?>" required>
                                            <option value="">Add a team…</option>
                                            <?php foreach ($stageTeamNames as $teamOptionId => $teamOptionName): ?>
                                                <?php if (isset($groupMemberIds[(int) $teamOptionId])) continue; ?>
                                                <option value="<?= (int) $teamOptionId ?>"><?= htmlspecialchars($teamOptionName) ?><?= isset($stagePlacement[(int) $teamOptionId]) ? ' — currently in ' . htmlspecialchars($stagePlacement[(int) $teamOptionId]) : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button name="assignStageTeam" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i><span class="visually-hidden">Add team to <?= htmlspecialchars($groupRow['name'], ENT_QUOTES) ?></span></button>
                                    </form>
                                    <details class="lsp-details">
                                        <summary>Group table</summary>
                                        <div class="lsp-details-body">
                                            <?php if ($groupRow['standings']): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <thead><tr><th>#</th><th>Team</th><th class="text-center">MP</th><th class="text-center">W</th><th class="text-center">L</th><th class="text-center">PF</th><th class="text-center">PA</th><th class="text-center">Pts</th></tr></thead>
                                                        <tbody>
                                                        <?php foreach ($groupRow['standings'] as $stageLine): ?>
                                                            <tr>
                                                                <td><?= (int) $stageLine['rank'] ?></td>
                                                                <td><?= htmlspecialchars($stageLine['team_name']) ?></td>
                                                                <td class="text-center"><?= (int) $stageLine['played'] ?></td>
                                                                <td class="text-center"><?= (int) $stageLine['wins'] + (int) ($stageLine['forfeit_wins'] ?? 0) ?></td>
                                                                <td class="text-center"><?= (int) $stageLine['losses'] + (int) ($stageLine['forfeit_losses'] ?? 0) + (int) ($stageLine['double_forfeits'] ?? 0) ?></td>
                                                                <td class="text-center"><?= (int) $stageLine['points_for'] ?></td>
                                                                <td class="text-center"><?= (int) $stageLine['points_against'] ?></td>
                                                                <td class="text-center fw-bold"><?= (int) $stageLine['points'] ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="lp-groups-empty"><i class="bi bi-hourglass"></i>No results yet — publish and complete matches to fill this table.</div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="lsp-inline-form lsp-add-group">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                            <input type="hidden" name="stage_id" value="<?= $stageRowId ?>">
                            <input name="stage_group_name" class="form-control" maxlength="80" placeholder="New group name — e.g. Bracket A / Pool 1" aria-label="New group name for <?= htmlspecialchars($stageRow['name'], ENT_QUOTES) ?>" required>
                            <button name="addStageGroup" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Add group</button>
                        </form>
                            </div>
                        </details>
                    </div>
                </li>
            <?php endforeach; ?>
            </ol>
            <div class="lsp-note">
                <i class="bi bi-calendar-check-fill"></i>
                <span>
                    Ready for matches? Generate them in <a href="?id=<?= (int) $id ?>&amp;step=matches#step-matches">Setup → Matches</a>
                    — preview the pairings and generate there. Schedule is for dates, venues, Committee, publishing and results.
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($setupCan('promote')): ?>
<!-- ==================== 4. ADVANCE QUALIFIERS ==================== -->
<div class="activity-section dash-animate dash-animate-d4" id="qualify">
    <div class="activity-header">
        <div class="activity-title">
            <i class="bi bi-arrow-right-circle-fill"></i> Advance qualifiers
            <span class="activity-badge-count">Optional</span>
        </div>
    </div>
    <div class="lp-section-body lsp-advance">
        <p class="lsp-lead">Copy a finished stage's best teams into the next one. Teams already there are replaced.</p>
        <form method="post" id="lspAdvanceForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="league_id" value="<?= (int) $id ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="lspFromStage">From stage</label>
                    <select id="lspFromStage" name="source_stage_id" class="form-select">
                        <?php foreach ($stages as $stagePickIndex => $stagePick): ?>
                            <option value="<?= (int) $stagePick['id'] ?>"<?= $stagePickIndex === 0 ? ' selected' : '' ?>
                                    data-groups="<?= count($stagePick['groups']) ?>"
                                    data-qpg="<?= (int) ($stagePick['qualifiers_per_group'] ?? 0) ?>"><?= htmlspecialchars($stagePick['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lspToStage">Into stage</label>
                    <select id="lspToStage" name="target_stage_id" class="form-select">
                        <?php foreach ($stages as $stagePickIndex => $stagePick): ?>
                            <option value="<?= (int) $stagePick['id'] ?>"<?= $stagePickIndex === 1 ? ' selected' : '' ?>><?= htmlspecialchars($stagePick['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lspPerGroup">Teams taken from each group</label>
                    <input id="lspPerGroup" name="per_group" type="number" min="1" max="64" value="<?= (int) ($stages[0]['qualifiers_per_group'] ?? 0) ?: 2 ?>" class="form-control">
                    <div class="form-text">2 keeps the top two of every group.</div>
                </div>
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="eliminate_rest" value="1" id="lspElimRest" checked>
                <label class="form-check-label small" for="lspElimRest">Mark the teams that do not qualify as eliminated (they can no longer be scheduled)</label>
            </div>
            <details class="lsp-details mt-3">
                <summary>Advanced: combine groups into new pools</summary>
                <div class="lsp-details-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="lspMappingPreset">How groups feed the next stage</label>
                            <select id="lspMappingPreset" class="form-select">
                                <option value="straight">Straight through — each group feeds its own group</option>
                                <option value="crossover">Crossover — pair the ends (A+D, B+C …)</option>
                                <option value="halves">Combine halves — first half, second half</option>
                                <option value="custom">Custom mapping (type it myself)</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="lspPoolMapping">Mapping</label>
                            <input id="lspPoolMapping" name="pool_mapping" class="form-control" placeholder="e.g. 0,3|1,2" inputmode="numeric">
                            <div class="form-text">Group positions in the “From stage” (0 = first group). A <code>|</code> starts a new pool.</div>
                        </div>
                    </div>
                </div>
            </details>
            <div class="lsp-advance-actions">
                <button name="promoteQualifiers" class="btn btn-primary"
                        data-lsp-confirm="Advance qualifiers now? The teams currently placed in the next stage's groups will be replaced.">
                    <i class="bi bi-arrow-right-circle me-1"></i>Advance qualifiers
                </button>
                <small class="text-muted">Tip: check the group tables first — teams marked eliminated are skipped.</small>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($groupingOn): ?>
<!-- ==================== MODAL: ADD POOL ==================== -->
<div class="modal fade" id="poolModal" tabindex="-1" aria-labelledby="poolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="poolModalLabel">Add a pool</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="lsp-lead">Teams are placed into the pool on the Standings page.</p>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="league_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="display_order" value="<?= (int) $groupCount + 1 ?>">
                    <label class="form-label" for="lspPoolName">Pool name</label>
                    <input id="lspPoolName" name="group_name" class="form-control" maxlength="80" placeholder="e.g. Group A" required>
                    <div class="lsp-chip-row">
                        <?php foreach (['Group A', 'Group B', 'Group C', 'Pool 1', 'Pool 2'] as $poolSuggestion): ?>
                            <button type="button" class="lsp-chip-btn" data-lsp-fill="#lspPoolName" data-lsp-value="<?= htmlspecialchars($poolSuggestion, ENT_QUOTES) ?>"><?= htmlspecialchars($poolSuggestion) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button name="addGroup" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add pool</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

