<?php
/**
 * Setup wizard state machine
 * --------------------------
 * The Setup page shows ONE task at a time. This file decides which task that is
 * from the league's own data — nothing extra is stored, so the wizard simply
 * follows the stages, their groups/teams and their matches.
 *
 * A league can be configured stage by stage: create Stage 1 (with its groups),
 * generate its matches, let the results come in, then create Stage 2 and carry
 * the qualifiers forward.
 *
 * Requires: includes/stageHelper.php (stage type / pattern labels).
 */

/** Default generation pattern for a stage (mirrors the Schedule page). */
function stageDefaultPattern(string $stageType, int $groupCount = 0, int $stageCount = 1): string {
    if ($stageType === 'crossover') {
        // Four brackets cross A/D and B/C; two pools play a round robin inside
        // each pool (the Pools -> playoffs preset).
        return $groupCount >= 4 ? 'crossover_cross' : 'round_robin_groups';
    }
    if ($stageType === 'knockout') {
        // One pool of qualifiers (or a lone bracket) draws a normal seeded
        // knockout: 1 v N, 2 v N-1 … with byes. Two or more pools use the
        // pool-based playoff patterns.
        if ($groupCount <= 1) return 'seeded_bracket';
        return 'playoff_bracket';
    }
    if ($stageType === 'placement') return 'playoff_bracket';
    return 'round_robin_groups';
}

/**
 * Patterns offered for a stage, in preference order (first = default).
 *
 * @return array<int,string>
 */
function stagePatternChoices(string $stageType, int $groupCount = 0, int $stageCount = 1): array {
    if ($stageType === 'crossover') {
        return $groupCount >= 4 ? ['crossover_cross'] : ['round_robin_groups'];
    }
    if ($stageType === 'knockout') {
        // A knockout stage with one pool of qualifiers is a normal seeded
        // bracket (QF/SF/Final depending on the number of teams). Two or more
        // pools use the pool-based patterns. A later stage with no pools yet
        // cannot be generated until its qualifiers are promoted.
        if ($groupCount === 1) return ['seeded_bracket'];
        if ($groupCount === 0) return $stageCount === 1 ? ['seeded_bracket'] : [];
        return ['playoff_bracket', 'pool_qf'];
    }
    if ($stageType === 'placement') return ['playoff_bracket'];
    return ['round_robin_groups'];
}

/**
 * Generation patterns the Setup wizard can create for a stage, with labels.
 *
 * This is the single source of the generation choices: round robin inside the
 * groups, crossover pairs, a seeded bracket for one pool, and — on a knockout
 * stage fed by two or more pools — the league's finals pattern first (the
 * Volleyball F1/F2 choice), followed by the pool-based playoff patterns.
 *
 * @param array<string,mixed> $stage row from getLeagueStages() with 'groups'
 * @return array<string,string> pattern key => label; empty when the stage must
 *         wait for its qualifiers to be advanced first
 */
function stageGenerationPatternOptions(mysqli $conn, int $leagueId, array $stage, int $stageCount = 1): array {
    $labels = function_exists('stagePatternLabels') ? stagePatternLabels() : [];
    $type = (string)($stage['stage_type'] ?? 'group');
    $groupCount = count($stage['groups'] ?? []);
    if (!in_array($type, ['group', 'crossover', 'knockout'], true)) return [];

    $choices = stagePatternChoices($type, $groupCount, $stageCount);
    if ($type === 'knockout' && $groupCount >= 2) {
        // The league's finals pattern leads; the pool-based patterns follow.
        $finalsPattern = function_exists('getLeagueSportProfile')
            ? (string)(getLeagueSportProfile($conn, $leagueId)['finals_pattern'] ?? '')
            : '';
        $finalsChoices = [];
        if ($finalsPattern === 'direct_final_bronze') {
            $finalsChoices = ['direct_final_bronze', 'semis_final_bronze'];
        } elseif ($finalsPattern === 'semis_final_bronze') {
            $finalsChoices = ['semis_final_bronze', 'direct_final_bronze'];
        }
        $choices = array_merge($finalsChoices, ['pool_qf', 'playoff_bracket']);
    }

    $options = [];
    foreach ($choices as $patternKey) {
        if (isset($labels[$patternKey])) $options[$patternKey] = $labels[$patternKey];
    }
    return $options;
}

/**
 * Suggested next stage when a stage is finished and nothing follows it.
 *
 * @return array{type:string,name:string,groups:array<int,string>}
 */
function suggestNextStageType(string $previousType): array {
    if ($previousType === 'group')     return ['type' => 'knockout',  'name' => 'Knockout',         'groups' => []];
    if ($previousType === 'crossover') return ['type' => 'knockout',  'name' => 'Playoffs',         'groups' => []];
    if ($previousType === 'knockout')  return ['type' => 'placement', 'name' => 'Battle for Third', 'groups' => []];
    return ['type' => 'knockout', 'name' => 'Final Stage', 'groups' => []];
}

/** Default group names created with a new stage (Group A, Group B … / Pool 1, Pool 2 …). */
function defaultStageGroupNames(int $groupCount, string $stageType = 'group'): array {
    $names = [];
    for ($i = 0; $i < max(0, $groupCount); $i++) {
        $names[] = $stageType === 'crossover'
            ? 'Pool ' . ($i + 1)
            : 'Group ' . chr(65 + ($i % 26));
    }
    return $names;
}

/**
 * Which single task the Setup page should show.
 *
 * @param array<int,array<string,mixed>> $stages rows from getLeagueStages() with
 *        each row's 'groups' filled in (each group carrying its 'teams').
 * @param array<int,array{matches:int,completed:int,complete:bool}> $stageStats keyed by stage id.
 * @return array{task:string,stage:?array,stage_id:int,next_stage:?array,index:int,total:int,
 *               matches:int,completed:int,auto_seeds:bool}
 */
function setupNextTask(array $stages, array $stageStats = []): array {
    $total = count($stages);
    if ($total === 0) {
        return [
            'task' => 'choose_structure', 'stage' => null, 'stage_id' => 0, 'next_stage' => null,
            'index' => 0, 'total' => 0, 'matches' => 0, 'completed' => 0, 'auto_seeds' => false,
        ];
    }

    foreach ($stages as $index => $stage) {
        $stageId = (int)($stage['id'] ?? 0);
        $groups  = $stage['groups'] ?? [];
        $type    = (string)($stage['stage_type'] ?? 'group');
        $groupCount = count($groups);
        $teamCount  = 0;
        foreach ($groups as $group) $teamCount += count($group['teams'] ?? []);

        $stats     = $stageStats[$stageId] ?? [];
        $matches   = (int)($stats['matches'] ?? 0);
        $completed = (int)($stats['completed'] ?? 0);
        $complete  = (bool)($stats['complete'] ?? false);
        $needsGroups = in_array($type, ['group', 'crossover'], true);

        $context = [
            'stage' => $stage, 'stage_id' => $stageId, 'next_stage' => $stages[$index + 1] ?? null,
            'index' => $index, 'total' => $total, 'matches' => $matches, 'completed' => $completed,
            'auto_seeds' => false,
        ];

        /* 1. a stage that needs groups but has none */
        if ($needsGroups && $groupCount === 0) return ['task' => 'add_groups'] + $context;

        /* 2. nothing generated yet — make sure the teams are there */
        if ($matches === 0) {
            if ($groupCount > 0 && $teamCount === 0) {
                if ($index === 0) {
                    // The first draw fills the groups with the approved teams.
                    $context['auto_seeds'] = true;
                    return ['task' => 'generate'] + $context;
                }
                return ['task' => 'place_teams'] + $context;
            }
            return ['task' => 'generate'] + $context;
        }

        /* 3. matches exist — wait for the results */
        if (!$complete) return ['task' => 'await_results'] + $context;

        /* 3b. a Swiss stage may still have planned rounds to generate */
        $format = function_exists('stageFormat')
            ? stageFormat($stage)
            : (string)($stage['stage_format'] ?? '');
        $roundsTotal  = (int)($stage['rounds_total'] ?? $stage['rounds_planned'] ?? 0);
        $currentRound = (int)($stage['current_round'] ?? 0);
        if ($format === 'swiss' && $roundsTotal > 0 && $currentRound < $roundsTotal) {
            $context['round'] = $currentRound + 1;
            $context['rounds_total'] = $roundsTotal;
            return ['task' => 'generate_swiss_round'] + $context;
        }

        /* 4. stage finished — carry the qualifiers forward */
        $next = $context['next_stage'];
        if ($next === null) {
            // A finished knockout/placement stage ends the tournament; a finished
            // group/crossover stage normally needs a follow-up stage.
            if (in_array($type, ['knockout', 'placement'], true)) {
                return ['task' => 'done'] + $context;
            }
            return ['task' => 'advance_and_next'] + $context;
        }

        $nextTeams = 0;
        foreach (($next['groups'] ?? []) as $nextGroup) $nextTeams += count($nextGroup['teams'] ?? []);
        $nextStats = $stageStats[(int)($next['id'] ?? 0)] ?? [];
        $nextStarted = ((int)($nextStats['matches'] ?? 0)) > 0;

        if ($nextTeams === 0 && !$nextStarted) {
            // Nothing to carry over when the finished stage has no table to rank.
            $canPromote = $groupCount > 0 && in_array($type, ['group', 'crossover'], true);
            if ($canPromote) return ['task' => 'advance'] + $context;

            // The next stage needs teams, but this stage cannot supply them.
            $nextContext = [
                'stage' => $next, 'stage_id' => (int)($next['id'] ?? 0),
                'next_stage' => $stages[$index + 2] ?? null,
                'index' => $index + 1, 'total' => $total,
                'matches' => (int)($nextStats['matches'] ?? 0),
                'completed' => (int)($nextStats['completed'] ?? 0),
                'auto_seeds' => false,
            ];
            return ['task' => 'place_teams'] + $nextContext;
        }
        // Already advanced/started: evaluate the next stage instead.
    }

    $lastId = (int)($stages[$total - 1]['id'] ?? 0);
    return [
        'task' => 'done', 'stage' => $stages[$total - 1], 'stage_id' => $lastId, 'next_stage' => null,
        'index' => $total - 1, 'total' => $total,
        'matches' => (int)($stageStats[$lastId]['matches'] ?? 0),
        'completed' => (int)($stageStats[$lastId]['completed'] ?? 0),
        'auto_seeds' => false,
    ];
}
