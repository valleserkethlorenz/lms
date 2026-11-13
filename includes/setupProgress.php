<?php
/**
 * Setup progress model
 * --------------------
 * Presentation helper for the organizer Setup page.
 *
 * The page used to show a row of raw counters. This file turns the same
 * information into five milestones — Structure → Stages → Teams → Matches →
 * Finish — and a weighted completion percentage, so the page can render a
 * progress bar without storing any extra state.
 *
 * Everything is derived from data the page already loads (stages with their
 * groups/teams, per-stage match counters, approved teams), which means the bar
 * moves automatically as the organizer works. Nothing here writes to the
 * database.
 *
 * Requires: nothing. Pure PHP, safe to unit test.
 */

/**
 * The five milestones, in display order, with their weight in the total score.
 *
 * The weights add up to 100 so the percentage can be read at face value:
 * generating the first matches is worth more than renaming a stage.
 *
 * @return array<string,array{title:string,label:string,icon:string,weight:int}>
 */
function setupProgressStepDefinitions(): array
{
    return [
        'structure' => ['title' => 'Structure', 'label' => 'Competition structure', 'icon' => 'bi-signpost-split-fill', 'weight' => 15],
        'stages'    => ['title' => 'Stages',    'label' => 'Stages & groups',       'icon' => 'bi-diagram-2-fill',     'weight' => 20],
        'teams'     => ['title' => 'Teams',     'label' => 'Teams placed',          'icon' => 'bi-people-fill',        'weight' => 20],
        'matches'   => ['title' => 'Matches',   'label' => 'Matches generated',     'icon' => 'bi-calendar-event-fill','weight' => 25],
        'finish'    => ['title' => 'Finish',    'label' => 'Results & finish',      'icon' => 'bi-trophy-fill',        'weight' => 20],
    ];
}

/**
 * Is this stage type built from groups (tables) rather than direct pairings?
 */
function setupProgressStageNeedsGroups(string $stageType): bool
{
    return in_array($stageType, ['group', 'crossover'], true);
}

/**
 * Build the milestone model for the Setup page.
 *
 * The optional `task` (from setupNextTask()) lets the next action mirror the
 * wizard — advance qualifiers, create the next stage or place teams — instead
 * of falling back to a generic "collect results" sentence.
 *
 * @param array{
 *     stages?:array<int,array<string,mixed>>,
 *     stage_stats?:array<int,array{matches?:int,completed?:int,complete?:bool}>,
 *     approved_teams?:int,
 *     assigned_teams?:int,
 *     matches?:int,
 *     structure_label?:string,
 *     league_id?:int,
 *     task?:string,
 *     next_stage_name?:string,
 *     active_stage_name?:string
 * } $options
 * @return array<string,mixed>
 */
function setupProgressModel(array $options = []): array
{
    $stages      = is_array($options['stages'] ?? null) ? $options['stages'] : [];
    $stageStats  = is_array($options['stage_stats'] ?? null) ? $options['stage_stats'] : [];
    $approved    = max(0, (int) ($options['approved_teams'] ?? 0));
    $matches     = max(0, (int) ($options['matches'] ?? 0));
    $leagueId    = max(0, (int) ($options['league_id'] ?? 0));
    $structureLabel = trim((string) ($options['structure_label'] ?? ''));
    $task        = (string) ($options['task'] ?? '');
    $nextStageName   = trim((string) ($options['next_stage_name'] ?? ''));
    $activeStageName = trim((string) ($options['active_stage_name'] ?? ''));

    $stageCount        = count($stages);
    $groupCount        = 0;
    $readyStages       = 0;
    $stagesWithMatches = 0;
    $firstGroupStage   = null;
    $firstStageWithoutMatches = null;

    foreach ($stages as $stage) {
        $stageId = (int) ($stage['id'] ?? 0);
        $type    = (string) ($stage['stage_type'] ?? 'group');
        $groups  = is_array($stage['groups'] ?? null) ? $stage['groups'] : [];
        $groupCount += count($groups);

        if (setupProgressStageNeedsGroups($type)) {
            if ($firstGroupStage === null) $firstGroupStage = $stage;
            if (count($groups) > 0) $readyStages++;
        } else {
            // Bracket and placement stages pair the teams directly, so they never
            // wait for a group to be created.
            $readyStages++;
        }

        $stats = $stageStats[$stageId] ?? [];
        if ((int) ($stats['matches'] ?? 0) > 0) {
            $stagesWithMatches++;
        } elseif ($firstStageWithoutMatches === null) {
            $firstStageWithoutMatches = $stage;
        }
    }

    $stagesReady = $stageCount > 0 && $readyStages === $stageCount;

    /* Teams placed in the FIRST group stage. This is the draw the organizer
       actually prepares before the first matches can be generated. Later stages
       receive their teams from the promotion step, so they are not counted here. */
    $placedTeams = 0;
    if ($firstGroupStage !== null) {
        $placedIds = [];
        foreach (($firstGroupStage['groups'] ?? []) as $group) {
            foreach (($group['teams'] ?? []) as $member) {
                $teamId = (int) ($member['team_id'] ?? 0);
                if ($teamId > 0) $placedIds[$teamId] = true;
            }
        }
        $placedTeams = count($placedIds);
    } elseif ($stageCount > 0) {
        // A bracket-only league draws the approved teams at generation time.
        $placedTeams = $approved;
    }

    /* Results: sum the per-stage counters that setup.php already collected. */
    $played = 0;
    $completedMatches = 0;
    foreach ($stageStats as $stats) {
        $played += (int) ($stats['matches'] ?? 0);
        $completedMatches += (int) ($stats['completed'] ?? 0);
    }
    if ($played < $matches) $played = $matches; // fallback for callers without stage stats

    $teamsDone   = $stageCount > 0 && $approved > 0
        && ($firstGroupStage === null || $placedTeams >= $approved);
    $matchesDone = $matches > 0;
    $swissRoundsRemain = $task === 'generate_swiss_round';
    $finished    = $stageCount > 0
        && $stagesWithMatches === $stageCount
        && $played > 0
        && $completedMatches >= $played
        && !$swissRoundsRemain;

    $doneMap = [
        'structure' => $stageCount > 0,
        'stages'    => $stagesReady,
        'teams'     => $teamsDone,
        'matches'   => $matchesDone && !$swissRoundsRemain,
        'finish'    => $finished,
    ];

    $scoreMap = [
        'structure' => $stageCount > 0 ? 1.0 : 0.0,
        'stages'    => $stageCount > 0 ? min(1.0, $readyStages / $stageCount) : 0.0,
        'teams'     => $approved > 0
            ? ($firstGroupStage === null ? 1.0 : min(1.0, $placedTeams / $approved))
            : 0.0,
        'matches'   => ($matches > 0 && $stageCount > 0) ? min(1.0, $stagesWithMatches / $stageCount) : 0.0,
        'finish'    => $played > 0 ? min(1.0, $completedMatches / $played) : 0.0,
    ];

    $definitions = setupProgressStepDefinitions();
    $score = 0.0;
    foreach ($definitions as $key => $definition) {
        $score += ($scoreMap[$key] ?? 0.0) * (int) $definition['weight'];
    }
    $percent = (int) round($score);
    if ($percent < 0) $percent = 0;
    if ($percent > 100) $percent = 100;

    /* The next step the organizer should open. */
    $openStep = 'finish';
    foreach ($definitions as $key => $definition) {
        if (empty($doneMap[$key])) { $openStep = $key; break; }
    }

    $details = [
        'structure' => $stageCount > 0
            ? ($structureLabel !== '' ? $structureLabel : 'Structure applied')
            : 'Choose how the league is played',
        'stages'    => $stageCount > 0
            ? $stageCount . ' ' . ($stageCount === 1 ? 'stage' : 'stages') . ' · ' . $groupCount . ' ' . ($groupCount === 1 ? 'group' : 'groups')
            : 'Created by the structure preset',
        'teams'     => $approved > 0
            ? $placedTeams . ' of ' . $approved . ' team' . ($approved === 1 ? '' : 's') . ' placed'
            : 'No approved teams yet',
        'matches'   => $matches > 0
            ? $matches . ' ' . ($matches === 1 ? 'match' : 'matches') . ' · ' . $stagesWithMatches . ' of ' . max(1, $stageCount) . ' stages'
            : 'None generated yet',
        'finish'    => $finished
            ? 'Tournament complete'
            : ($played > 0 ? $completedMatches . ' of ' . $played . ' played' : 'Opens when matches exist'),
    ];

    $steps = [];
    $completed = 0;
    foreach ($definitions as $key => $definition) {
        $isDone = !empty($doneMap[$key]);
        if ($isDone) $completed++;
        $state = $isDone ? 'is-done' : ($key === $openStep ? 'is-current' : 'is-upcoming');
        $steps[] = [
            'key'    => $key,
            'title'  => $definition['title'],
            'label'  => $definition['label'],
            'icon'   => $definition['icon'],
            'detail' => $details[$key],
            'state'  => $state,
            'done'   => $isDone,
            'weight' => (int) $definition['weight'],
        ];
    }

    /* The one-sentence "what now" shown under the bar and in the step footer. */
    if ($stageCount === 0) {
        $next = [
            'step' => 'structure',
            'label' => 'Choose the competition structure',
            'hint' => 'Pick a preset and the stages and groups are created for you. You can change it until the first match exists.',
            'cta' => 'Choose structure',
            'anchor' => '#step-structure',
            'href' => '',
        ];
    } elseif (!$stagesReady) {
        $missing = 'your stage';
        foreach ($stages as $stage) {
            if (setupProgressStageNeedsGroups((string) ($stage['stage_type'] ?? 'group'))
                && count($stage['groups'] ?? []) === 0) {
                $missing = (string) ($stage['name'] ?? 'your stage');
                break;
            }
        }
        $next = [
            'step' => 'stages',
            'label' => 'Create the groups for ' . $missing,
            'hint' => 'Group and crossover stages need at least one group before their matches can be generated.',
            'cta' => 'Set up stages',
            'anchor' => '#step-stages',
            'href' => '',
        ];
    } elseif ($approved === 0) {
        $next = [
            'step' => 'teams',
            'label' => 'Approve the teams that registered',
            'hint' => 'Only approved teams can be placed in a stage. Review the applications first.',
            'cta' => 'Review teams',
            'anchor' => '#step-teams',
            'href' => 'pending_teams.php?id=' . $leagueId,
        ];
    } elseif ($task === 'place_teams' && $activeStageName !== '') {
        $next = [
            'step' => 'teams',
            'label' => 'Place the teams in ' . $activeStageName,
            'hint' => 'This stage cannot receive qualifiers automatically, so add its teams by hand before generating matches.',
            'cta' => 'Place teams',
            'anchor' => '#step-teams',
            'href' => '',
        ];
    } elseif (!$teamsDone) {
        $next = [
            'step' => 'teams',
            'label' => 'Place the approved teams in ' . ($firstGroupStage['name'] ?? 'the first stage'),
            'hint' => 'Use the automatic draw or set the seeds yourself. ' . $placedTeams . ' of ' . $approved . ' teams are placed.',
            'cta' => 'Place teams',
            'anchor' => '#step-teams',
            'href' => '',
        ];
    } elseif (!$matchesDone) {
        $next = [
            'step' => 'matches',
            'label' => 'Generate the matches for ' . ($firstStageWithoutMatches['name'] ?? 'the first stage'),
            'hint' => 'Preview the pairings, generate them as drafts, then publish them on the Schedule page.',
            'cta' => 'Generate matches',
            'anchor' => '#step-matches',
            'href' => '',
        ];
    } elseif ($task === 'generate_swiss_round') {
        $next = [
            'step' => 'matches',
            'label' => 'Generate the next Swiss round' . ($activeStageName !== '' ? ' for ' . $activeStageName : ''),
            'hint' => 'The current round is complete. Lock it and pair the next round from the standings.',
            'cta' => 'Generate round',
            'anchor' => '#step-matches',
            'href' => '',
        ];
    } elseif ($task === 'advance') {
        $next = [
            'step' => 'finish',
            'label' => 'Advance the qualifiers into ' . ($nextStageName !== '' ? $nextStageName : 'the next stage'),
            'hint' => 'The source stage is complete. Carry its top teams forward so the next stage can be drawn.',
            'cta' => 'Advance qualifiers',
            'anchor' => '#step-finish',
            'href' => '',
        ];
    } elseif ($task === 'advance_and_next') {
        $next = [
            'step' => 'finish',
            'label' => 'The stage is complete — create the next stage or finish the tournament',
            'hint' => 'Create the next stage and carry the qualifiers forward, or stop here if this was the last stage.',
            'cta' => 'Set up the next stage',
            'anchor' => '#step-finish',
            'href' => '',
        ];
    } elseif (!$finished) {
        $next = [
            'step' => 'finish',
            'label' => $played > 0
                ? $completedMatches . ' of ' . $played . ' matches have results'
                : 'Open the schedule and publish the matches',
            'hint' => 'The assigned Committee submits each result. Standings and progression update automatically.',
            'cta' => 'Open schedule',
            'anchor' => '#step-finish',
            'href' => 'schedule.php?id=' . $leagueId,
        ];
    } else {
        $next = [
            'step' => 'finish',
            'label' => 'Setup complete — the tournament has been played',
            'hint' => 'Review the final standings, the champion and the archived match history.',
            'cta' => 'Open standings',
            'anchor' => '#step-finish',
            'href' => 'standing.php?id=' . $leagueId,
        ];
    }

    return [
        'percent' => $percent,
        'completed' => $completed,
        'total' => count($definitions),
        'done' => $completed === count($definitions),
        'open_step' => $openStep,
        'next' => $next,
        'steps' => $steps,
        'stages' => $stageCount,
        'groups' => $groupCount,
        'approved_teams' => $approved,
        'placed_teams' => $placedTeams,
        'matches' => $matches,
        'played' => $played,
        'completed_matches' => $completedMatches,
    ];
}
