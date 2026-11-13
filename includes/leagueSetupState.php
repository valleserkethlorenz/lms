<?php
/**
 * League setup state — the single source of "where is this league?"
 * -----------------------------------------------------------------
 * The Setup page used to derive its step flags in three places at once: the
 * wizard (includes/setupWizard.php), the milestone model
 * (includes/setupProgress.php) and setup.php itself. The three copies could
 * disagree, which is how a league ended up "on Teams" in the header and
 * "on Matches" in the panel.
 *
 * This file is now the only place that maps league data to UI state:
 *
 *   - which of the three steps (Format -> Teams -> Matches) is open and done,
 *   - what the organizer should do next,
 *   - which capabilities the current structure actually has
 *     (choose a structure, edit stages, place teams, generate, promote,
 *     create playoffs, discard).
 *
 * The two older helpers stay as the engines: setupNextTask() decides the next
 * task from the stages, setupProgressModel() scores the milestones. Nothing
 * here talks to the database, so it is safe to unit test.
 *
 * Requires: includes/setupWizard.php, includes/setupProgress.php,
 *           includes/leagueStructure.php (structure cards).
 */

require_once __DIR__ . '/setupWizard.php';
require_once __DIR__ . '/setupProgress.php';
require_once __DIR__ . '/leagueStructure.php';

/**
 * The wizard steps, in display order.
 *
 * @return string[]
 */
function leagueSetupStepKeys(): array
{
    return ['format', 'teams', 'matches'];
}

/**
 * URL aliases kept so older links keep working.
 *
 * @return array<string,string>
 */
function leagueSetupStepAliases(): array
{
    return [
        'structure' => 'format',
        'stages'    => 'format',
        'format'    => 'format',
        'teams'     => 'teams',
        'finish'    => 'matches',
        'matches'   => 'matches',
    ];
}

/**
 * The structure cards shown by the Format step.
 *
 * They come from leagueStructure.php so the picker, the detector and the docs
 * can never drift again (the page used to hardcode three of the five presets).
 *
 * @return array<string,array{title:string,text:string}>
 */
function leagueSetupStructureCards(): array
{
    return function_exists('structureCards') ? structureCards() : [];
}

/** Is this stage type built from groups (tables) rather than direct pairings? */
function leagueSetupStageNeedsGroups(string $stageType): bool
{
    return in_array($stageType, ['group', 'crossover'], true);
}

/**
 * Group/bracket bookkeeping shared by the steps, the panels and the tests.
 *
 * @param array<int,array<string,mixed>> $stages
 * @param array<int,array{matches?:int,completed?:int,complete?:bool}> $stageStats
 * @return array{
 *     group_count:int,
 *     has_group_stage:bool,
 *     started_knockout:bool,
 *     first_group_stage:?array,
 *     can_create_playoffs:bool
 * }
 */
function leagueSetupStageSummary(array $stages, array $stageStats = []): array
{
    $groupCount      = 0;
    $hasGroupStage   = false;
    $startedKnockout = false;
    $firstGroupStage = null;

    foreach ($stages as $stage) {
        $stageId = (int) ($stage['id'] ?? 0);
        $type    = (string) ($stage['stage_type'] ?? 'group');
        $groups  = is_array($stage['groups'] ?? null) ? $stage['groups'] : [];
        $groupCount += count($groups);

        if (leagueSetupStageNeedsGroups($type)) {
            if ($firstGroupStage === null) $firstGroupStage = $stage;
            $hasGroupStage = true;
            continue;
        }

        /* A knockout stage only counts as started once it has matches or
           promoted teams; an empty Playoffs stage is what "Create playoffs"
           fills later. */
        if (in_array($type, ['knockout', 'placement'], true)) {
            $stats     = $stageStats[$stageId] ?? [];
            $teamCount = 0;
            foreach ($groups as $group) $teamCount += count($group['teams'] ?? []);
            if ((int) ($stats['matches'] ?? 0) > 0 || $teamCount > 0) $startedKnockout = true;
        }
    }

    /* "Create playoffs" is offered only for a finished single-group round robin
       whose knockout stage has not started yet. */
    $canCreatePlayoffs = false;
    if ($firstGroupStage !== null) {
        $firstStats = $stageStats[(int) ($firstGroupStage['id'] ?? 0)] ?? [];
        $firstGroups = is_array($firstGroupStage['groups'] ?? null) ? $firstGroupStage['groups'] : [];
        $firstGroupTeams = count($firstGroups) === 1 ? count($firstGroups[0]['teams'] ?? []) : 0;
        $canCreatePlayoffs = !empty($firstStats['complete']) && $firstGroupTeams >= 2 && !$startedKnockout;
    }

    return [
        'group_count'        => $groupCount,
        'has_group_stage'    => $hasGroupStage,
        'started_knockout'   => $startedKnockout,
        'first_group_stage'  => $firstGroupStage,
        'can_create_playoffs'=> $canCreatePlayoffs,
    ];
}

/**
 * Build the whole Setup state from data the page already loaded.
 *
 * @param array{
 *     league_id?:int,
 *     stages?:array<int,array<string,mixed>>,
 *     stage_stats?:array<int,array{matches?:int,completed?:int,complete?:bool}>,
 *     approved_teams?:int,
 *     assigned_teams?:int,
 *     matches?:int,
 *     structure_key?:string|null,
 *     structure_label?:string,
 *     structure_is_preset?:bool,
 *     requested_step?:string
 * } $options
 * @return array<string,mixed>
 */
function leagueSetupState(array $options = []): array
{
    $leagueId       = max(0, (int) ($options['league_id'] ?? 0));
    $stages         = is_array($options['stages'] ?? null) ? $options['stages'] : [];
    $stageStats     = is_array($options['stage_stats'] ?? null) ? $options['stage_stats'] : [];
    $approved       = max(0, (int) ($options['approved_teams'] ?? 0));
    $assigned       = max(0, (int) ($options['assigned_teams'] ?? 0));
    $matches        = max(0, (int) ($options['matches'] ?? 0));
    $structureKey   = isset($options['structure_key']) && is_string($options['structure_key']) && $options['structure_key'] !== ''
        ? $options['structure_key']
        : null;
    $structureLabel = trim((string) ($options['structure_label'] ?? ''));
    $isPreset       = (bool) ($options['structure_is_preset'] ?? ($structureKey !== null && $structureKey !== 'custom'));
    $requestedStep  = (string) ($options['requested_step'] ?? '');

    $stageCount = count($stages);
    $stageSummary = leagueSetupStageSummary($stages, $stageStats);

    /* The wizard tasks come from the stage engine, not from copied flags. */
    $task = setupNextTask($stages, $stageStats);
    $taskKey = (string) ($task['task'] ?? '');

    $structureCards = leagueSetupStructureCards();
    $structureTitle = 'Custom structure';
    if ($structureKey !== null && isset($structureCards[$structureKey]['title'])) {
        $structureTitle = (string) $structureCards[$structureKey]['title'];
    } elseif ($structureLabel !== '') {
        $structureTitle = $structureLabel;
    }

    $progress = setupProgressModel([
        'stages'            => $stages,
        'stage_stats'       => $stageStats,
        'approved_teams'    => $approved,
        'assigned_teams'    => $assigned,
        'matches'           => $matches,
        'structure_label'   => $isPreset ? $structureTitle : 'Custom structure',
        'league_id'         => $leagueId,
        'task'              => (string) ($task['task'] ?? ''),
        'next_stage_name'   => (string) ($task['next_stage']['name'] ?? ''),
        'active_stage_name' => (string) ($task['stage']['name'] ?? ''),
    ]);

    /* The five milestone states collapse into the three visible steps. */
    $milestoneState = [];
    foreach (($progress['steps'] ?? []) as $milestone) {
        $milestoneState[(string) ($milestone['key'] ?? '')] = (string) ($milestone['state'] ?? '');
    }

    $done = [
        'format'  => ($milestoneState['structure'] ?? '') === 'is-done'
            && ($milestoneState['stages'] ?? '') === 'is-done',
        'teams'   => ($milestoneState['teams'] ?? '') === 'is-done',
        'matches' => $matches > 0 && $taskKey !== 'generate_swiss_round',
    ];
    $finishDone = ($milestoneState['finish'] ?? '') === 'is-done';

    /* Only structures that actually use a group draw get the Teams step: a
       bracket-only league seeds its teams at generation time, so a whole step
       saying "no draw needed" is noise. */
    $stepKeys = ($stageSummary['has_group_stage'] || $stageCount === 0)
        ? leagueSetupStepKeys()
        : ['format', 'matches'];

    $aliases    = leagueSetupStepAliases();
    $activeStep = $aliases[$requestedStep] ?? '';
    if (!in_array($activeStep, $stepKeys, true)) {
        if (!$done['format']) {
            $activeStep = 'format';
        } elseif (in_array('teams', $stepKeys, true) && !$done['teams']) {
            $activeStep = 'teams';
        } else {
            $activeStep = 'matches';
        }
    }
    $activeIndex = array_search($activeStep, $stepKeys, true);
    if ($activeIndex === false) $activeIndex = 0;

    $doneCount = 0;
    foreach ($stepKeys as $stepKey) {
        if (!empty($done[$stepKey])) $doneCount++;
    }
    $percent = (int) round(($doneCount / max(1, count($stepKeys))) * 100);

    return [
        'league_id'  => $leagueId,
        'structure'  => [
            'key'        => $structureKey,
            'label'      => $structureLabel,
            'title'      => $structureTitle,
            'is_preset'  => $isPreset,
            'has_stages' => $stageCount > 0,
            'cards'      => $structureCards,
        ],
        'stages'          => $stages,
        'stage_stats'     => $stageStats,
        'stage_count'     => $stageCount,
        'group_count'     => (int) $stageSummary['group_count'],
        'approved_teams'  => $approved,
        'assigned_teams'  => $assigned,
        'matches'         => $matches,
        'task'            => $task,
        'next'            => $progress['next'] ?? [],
        'progress'        => $progress,
        'percent'         => $percent,
        'steps'           => $progress['steps'] ?? [],
        'step_keys'       => $stepKeys,
        'done'            => $done,
        'finish_done'     => $finishDone,
        'active_step'     => $activeStep,
        'active_index'    => $activeIndex,
        'done_count'      => $doneCount,
        'first_group_stage' => $stageSummary['first_group_stage'],
        'capabilities'    => [
            /* The skeleton may only be replaced while the league has no
               matches; afterwards tbl_schedule.stage_id would dangle. */
            'choose_structure' => $matches === 0,
            'edit_stages'      => true,
            'place_teams'      => (bool) $stageSummary['has_group_stage'],
            'generate'         => $stageCount > 0,
            'promote'          => $stageCount >= 2,
            'create_playoffs'  => (bool) $stageSummary['can_create_playoffs'],
            'discard'          => true,
        ],
    ];
}


