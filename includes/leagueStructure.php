<?php
/**
 * League structure presets
 * ------------------------
 * A league is created without a structure. The organizer applies ONE preset on
 * the Setup page and the preset seeds the stage/group skeleton. Structure is the
 * only tournament model — there is no league "format" anymore, and the chosen
 * preset code is not stored: the skeleton itself is the structure.
 *
 * The presets are deliberately sport-agnostic: Basketball, Volleyball, DOTA,
 * Pickleball … all use the same list. Sport differences (scoring, best-of,
 * roster limits) come from config/sportRules.php and config/scoringRules.php.
 */

require_once __DIR__ . '/stageHelper.php';
require_once __DIR__ . '/setupWizard.php';

/**
 * The structures offered on the Setup page, in display order.
 *
 * From this release on the LMS speaks four competition formats — round robin,
 * Swiss, single elimination and double elimination — and a structure is just a
 * chain of stages built from them. Multi-stage presets stay for the existing
 * leagues (and for the ANP2026 guidelines).
 *
 * @return array<string,string> code => label
 */
function structureOptions(): array {
    return [
        'round_robin'          => 'Round robin — every team plays every other team',
        'swiss_system'         => 'Swiss system — equal records play each other every round',
        'single_elimination'   => 'Single elimination — seeded bracket with byes',
        'double_elimination'   => 'Double elimination — winners bracket, losers bracket, grand final',
        'round_robin_playoffs' => 'Round robin + playoffs — league table, then a seeded knockout',
        'groups_knockout'      => 'Groups → knockout — 2–8 group tables, then a bracket',
        'pools_playoffs'       => 'Pools → crossover → playoffs (multi-stage)',
        'custom'               => 'Custom — an existing hand-built skeleton',
    ];
}

/**
 * Short, UI-ready description of every selectable structure.
 *
 * The Setup wizard and the Advanced panel both render these cards, so the copy
 * lives here instead of being duplicated in each view. "custom" is not a card:
 * building the stages by hand is offered as a separate link.
 *
 * @return array<string,array{title:string,text:string}>
 */
function structureCards(): array {
    return [
        'round_robin'          => ['title' => 'Round robin',          'text' => 'One table; every team plays every other team.'],
        'swiss_system'         => ['title' => 'Swiss system',         'text' => 'Fixed rounds; equal records are paired each round.'],
        'single_elimination'   => ['title' => 'Single elimination',   'text' => 'A seeded bracket; lose a match and you are out.'],
        'double_elimination'   => ['title' => 'Double elimination',   'text' => 'Winners and losers brackets; two losses to go out.'],
        'round_robin_playoffs' => ['title' => 'Round robin + playoffs', 'text' => 'Round robin first, then a seeded knockout.'],
        'groups_knockout'      => ['title' => 'Groups → knockout',    'text' => '2–8 group tables first, then a bracket for the qualifiers.'],
        'pools_playoffs'       => ['title' => 'Pools → playoffs',     'text' => 'Brackets, crossover pools, then the playoffs.'],
    ];
}

/**
 * @return array<string, array{stages:array}>
 */
function leagueStructurePresets(): array {
    return [
        'round_robin' => [
            'stages' => [
                ['name' => 'League Stage', 'type' => 'group', 'format' => 'round_robin', 'groups' => ['Group A']],
            ],
        ],
        'swiss_system' => [
            // One table, a fixed number of rounds. The default is editable in
            // the Setup wizard; when it is left empty the engine derives it from
            // the team count (ceil(log2(n)), minimum 3).
            'stages' => [
                ['name' => 'Swiss Stage', 'type' => 'group', 'format' => 'swiss', 'groups' => ['Swiss Table'], 'rounds' => 5],
            ],
        ],
        'single_elimination' => [
            // A seeded knockout stage: the bracket pattern draws the approved
            // teams and handles byes, so any team count from 2 up works.
            'stages' => [
                ['name' => 'Bracket', 'type' => 'knockout', 'format' => 'single_elimination', 'groups' => []],
            ],
        ],
        'double_elimination' => [
            // Winners bracket + losers bracket + grand final. Losing a winners
            // match drops the team into the losers bracket instead of ending it.
            'stages' => [
                ['name' => 'Double Elimination', 'type' => 'knockout', 'format' => 'double_elimination', 'groups' => []],
            ],
        ],
        'round_robin_playoffs' => [
            // The normal basketball league shape: one league table, then a
            // seeded knockout for the top N. The qualifier count is editable in
            // the Setup wizard and drives the automatic promotion.
            'stages' => [
                ['name' => 'League Stage', 'type' => 'group', 'format' => 'round_robin', 'qualifiers' => 4, 'groups' => ['Group A']],
                ['name' => 'Playoffs', 'type' => 'knockout', 'format' => 'single_elimination', 'groups' => ['Qualifiers']],
            ],
        ],
        'groups_knockout' => [
            'stages' => [
                ['name' => 'Group Stage', 'type' => 'group', 'format' => 'round_robin', 'qualifiers' => 2, 'groups' => ['Group A', 'Group B']],
                ['name' => 'Knockout', 'type' => 'knockout', 'format' => 'single_elimination', 'groups' => ['Qualifiers'], 'sources' => 'all'],
            ],
        ],
        'pools_playoffs' => [
            'stages' => [
                ['name' => 'Elimination (Brackets)', 'type' => 'group', 'format' => 'round_robin', 'qualifiers' => 2,
                 'groups' => ['Bracket A', 'Bracket B', 'Bracket C', 'Bracket D']],
                ['name' => 'Crossover / Pools', 'type' => 'crossover', 'format' => 'round_robin', 'qualifiers' => 4,
                 'groups' => ['Pool 1', 'Pool 2'], 'carries' => true, 'sources' => [[0, 3], [1, 2]]],
                ['name' => 'Playoffs', 'type' => 'knockout', 'format' => 'single_elimination', 'groups' => []],
            ],
        ],
        'custom' => [
            'stages' => [],
        ],
    ];
}


/**
 * Editable knobs of every preset, shown in the Setup wizard's Format step.
 *
 * The values are written to the stage columns the engine already reads:
 *   - groups     -> number of tbl_league_stage_group rows (Groups → knockout only)
 *   - qualifiers -> tbl_league_stage.qualifiers_per_group (top N advance)
 *   - rounds     -> tbl_league_stage.rounds_planned (Swiss only)
 *
 * Keeping the schema here means the picker, the validator, the preset defaults
 * and the tests all describe the same settings.
 *
 * @return array<string,array<int,array{stage:int,field:string,label:string,min:int,max:int,default:int}>>
 */
function leagueStructureEditableSettings(): array {
    return [
        'round_robin_playoffs' => [
            ['stage' => 0, 'field' => 'qualifiers', 'label' => 'Top teams advance to the playoffs', 'min' => 2, 'max' => 64, 'default' => 4],
        ],
        'groups_knockout' => [
            ['stage' => 0, 'field' => 'groups', 'label' => 'Number of groups', 'min' => 2, 'max' => 8, 'default' => 2],
            ['stage' => 0, 'field' => 'qualifiers', 'label' => 'Top teams per group advance to the knockout', 'min' => 1, 'max' => 64, 'default' => 2],
        ],
        'pools_playoffs' => [
            ['stage' => 0, 'field' => 'qualifiers', 'label' => 'Top teams per bracket advance to the crossover pools', 'min' => 1, 'max' => 64, 'default' => 2],
            ['stage' => 1, 'field' => 'qualifiers', 'label' => 'Top teams per pool advance to the playoffs', 'min' => 1, 'max' => 64, 'default' => 4],
        ],
        'swiss_system' => [
            ['stage' => 0, 'field' => 'rounds', 'label' => 'Swiss rounds', 'min' => 3, 'max' => 30, 'default' => 5],
        ],
    ];
}

/**
 * Default values of the editable settings for one preset, keyed by stage index.
 *
 * @return array<int,array<string,int>>
 */
function leagueStructureDefaultSettings(string $structureKey): array {
    $presets = leagueStructurePresets();
    $preset  = $presets[$structureKey] ?? null;
    if ($preset === null) return [];

    $defaults = [];
    foreach (leagueStructureEditableSettings()[$structureKey] ?? [] as $field) {
        $stageDef = $preset['stages'][$field['stage']] ?? [];
        if ($field['field'] === 'qualifiers') {
            $value = (int)($stageDef['qualifiers'] ?? $field['default']);
        } elseif ($field['field'] === 'rounds') {
            $value = (int)($stageDef['rounds'] ?? $field['default']);
        } elseif ($field['field'] === 'groups') {
            $value = count($stageDef['groups'] ?? []) ?: (int)$field['default'];
        } else {
            $value = (int)$field['default'];
        }
        $defaults[$field['stage']][$field['field']] = $value;
    }
    return $defaults;
}

/**
 * Sanitize the settings posted by the Format step.
 *
 * Unknown stages/fields are ignored, values are clamped to the schema range,
 * and fields the organizer did not post keep their preset default.
 *
 * @param array<int,array<string,mixed>> $input
 * @return array<int,array<string,int>>
 */
function leagueStructureSanitizeSettings(string $structureKey, array $input): array {
    $clean = leagueStructureDefaultSettings($structureKey);
    foreach (leagueStructureEditableSettings()[$structureKey] ?? [] as $field) {
        $stageIndex = (int)$field['stage'];
        $raw = $input[$stageIndex][$field['field']] ?? null;
        if ($raw === null || $raw === '') continue;
        $clean[$stageIndex][$field['field']] = max(
            (int)$field['min'],
            min((int)$field['max'], (int)$raw)
        );
    }
    return $clean;
}

/**
 * Balanced group sizes for a team count: e.g. 14 teams in 4 groups -> [4,4,3,3].
 *
 * @return int[]
 */
function leagueStructureBalancedGroupSizes(int $teamCount, int $groups): array {
    $groups = max(1, $groups);
    $teamCount = max(0, $teamCount);
    $base = intdiv($teamCount, $groups);
    $extra = $teamCount % $groups;

    $sizes = array_fill(0, $groups, $base);
    for ($i = 0; $i < $extra; $i++) $sizes[$i]++;
    return $sizes;
}

/** Smallest power of two that can hold this many bracket slots. */
function leagueStructureNextPowerOfTwo(int $value): int {
    $size = 2;
    while ($size < max(2, $value)) $size *= 2;
    return $size;
}

/**
 * Smart recommendation for the Groups → knockout layout.
 *
 * The optimizer scores every group count from 2 to 8 against the team count:
 *   - balanced group sizes near four teams per group,
 *   - no group smaller than three or larger than six where possible,
 *   - top two advance per group (top one when groups are tiny),
 *   - fewer bracket byes, without letting more than half the field advance.
 *
 * The result is a suggestion only; the organizer can override both the group
 * count and the qualifier count in the Format step.
 *
 * @return array{
 *     team_count:int,groups:int,qualifiers:int,group_sizes:int[],
 *     smallest_group:int,largest_group:int,total_qualifiers:int,
 *     bracket_size:int,byes:int,warning:string
 * }
 */
function recommendedGroupSetup(int $teamCount, int $maxGroups = 8): array {
    $teamCount = max(0, $teamCount);
    $maxGroups = max(2, min(8, $maxGroups));

    if ($teamCount < 4) {
        $sizes = leagueStructureBalancedGroupSizes($teamCount, 2);
        return [
            'team_count'       => $teamCount,
            'groups'           => 2,
            'qualifiers'       => 1,
            'group_sizes'      => $sizes,
            'smallest_group'   => max(0, $sizes ? min($sizes) : 0),
            'largest_group'    => max(0, $sizes ? max($sizes) : 0),
            'total_qualifiers' => 2,
            'bracket_size'     => 2,
            'byes'             => 0,
            'warning'          => 'Groups → knockout needs at least four teams. Consider Round robin + playoffs for a smaller field.',
        ];
    }

    $highestGroupCount = min($maxGroups, intdiv($teamCount, 2));
    $best = null;
    $bestScore = INF;

    for ($groups = 2; $groups <= $highestGroupCount; $groups++) {
        $sizes = leagueStructureBalancedGroupSizes($teamCount, $groups);
        $minSize = min($sizes);
        $maxSize = max($sizes);
        $qualifiers = $minSize >= 3 ? 2 : 1;
        $totalQualifiers = $groups * $qualifiers;
        $bracketSize = leagueStructureNextPowerOfTwo($totalQualifiers);
        $byes = $bracketSize - $totalQualifiers;

        $score  = abs($minSize - 4) * 3 + abs($maxSize - 4) * 2;
        if ($minSize < 3) $score += (3 - $minSize) * 6;
        if ($maxSize > 6) $score += ($maxSize - 6) * 5;
        $score += $byes * 2;

        $advanceRatio = $teamCount > 0 ? $totalQualifiers / $teamCount : 0.0;
        if ($advanceRatio > 0.5) $score += ($advanceRatio - 0.5) * 20;
        $score += $groups * 0.1;

        if ($score >= $bestScore) continue;
        $bestScore = $score;
        $best = [
            'team_count'       => $teamCount,
            'groups'           => $groups,
            'qualifiers'       => $qualifiers,
            'group_sizes'      => $sizes,
            'smallest_group'   => $minSize,
            'largest_group'    => $maxSize,
            'total_qualifiers' => $totalQualifiers,
            'bracket_size'     => $bracketSize,
            'byes'             => $byes,
            'warning'          => '',
        ];
    }

    if ($best === null) {
        $sizes = leagueStructureBalancedGroupSizes($teamCount, 2);
        return [
            'team_count'       => $teamCount,
            'groups'           => 2,
            'qualifiers'       => 1,
            'group_sizes'      => $sizes,
            'smallest_group'   => max(0, $sizes ? min($sizes) : 0),
            'largest_group'    => max(0, $sizes ? max($sizes) : 0),
            'total_qualifiers' => 2,
            'bracket_size'     => 2,
            'byes'             => 0,
            'warning'          => '',
        ];
    }

    return $best;
}

/**
 * Persist the planned Swiss round count on a stage.
 */
function updateStagePlannedRounds(mysqli $conn, int $stageId, int $rounds): bool {
    $rounds = max(0, min(30, $rounds));
    $stmt = $conn->prepare("UPDATE tbl_league_stage SET rounds_planned = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $rounds, $stageId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Save the editable settings on stages that already exist.
 *
 * Used when the organizer edits the current preset without replacing its
 * skeleton: the stages, groups, sources and team placements are kept and only
 * the cut-off / round values change. Callers must refuse this once matches
 * exist, for the same reason the preset itself is locked then.
 *
 * @param array<int,array<string,int>> $settings
 * @return int number of stage values written
 */
function updateLeagueStructureSettings(mysqli $conn, int $leagueId, string $structureKey, array $settings): int {
    $settings = array_replace_recursive(leagueStructureDefaultSettings($structureKey), $settings);
    $stages = getLeagueStages($conn, $leagueId);
    $written = 0;

    foreach (leagueStructureEditableSettings()[$structureKey] ?? [] as $field) {
        $stage = $stages[(int)$field['stage']] ?? null;
        if (!$stage) continue;

        $stageId = (int)$stage['id'];
        $value = (int)($settings[$field['stage']][$field['field']] ?? $field['default']);
        $value = max((int)$field['min'], min((int)$field['max'], $value));

        /* Group count is structural: it rebuilds the stage groups instead. */
        if ($field['field'] === 'groups') continue;

        if ($field['field'] === 'qualifiers') {
            $stmt = $conn->prepare("UPDATE tbl_league_stage SET qualifiers_per_group = ? WHERE id = ? AND league_id = ?");
            if (!$stmt) continue;
            $stmt->bind_param('iii', $value, $stageId, $leagueId);
            if ($stmt->execute() && $stmt->affected_rows >= 0) $written++;
            $stmt->close();
            continue;
        }

        if ($field['field'] === 'rounds' && updateStagePlannedRounds($conn, $stageId, $value)) {
            $written++;
        }
    }
    return $written;
}

/**
 * Create the stage/group skeleton of a structure preset. Runs inside the league
 * creation transaction, so a failure rolls the whole league back.
 *
 * @param array<int,array<string,int>> $settings editable values from the Format step
 * @return int number of stages created (0 for presets without stages)
 */
function applyLeagueStructureTemplate(mysqli $conn, int $leagueId, string $structure, array $settings = []): int {
    $presets = leagueStructurePresets();
    $preset  = $presets[$structure] ?? $presets['round_robin'];
    if (empty($preset['stages'])) return 0;

    $settings = array_replace_recursive(leagueStructureDefaultSettings($structure), $settings);

    // Stage tables come from database/migrations/034_anp2026_stages_and_rules.sql. Skip the
    // template (never fail league creation) when a deployment has not run it yet.
    foreach (['tbl_league_stage', 'tbl_league_stage_group', 'tbl_league_stage_group_source'] as $stageTable) {
        $check = @$conn->query("SHOW TABLES LIKE '$stageTable'");
        if (!$check || $check->num_rows === 0) return 0;
    }

    $createdStages = 0;
    $previousGroupIds = [];
    foreach ($preset['stages'] as $stageIndex => $stageDef) {
        $stageSettings = $settings[$stageIndex] ?? [];
        $qualifiers = isset($stageSettings['qualifiers'])
            ? (int)$stageSettings['qualifiers']
            : (isset($stageDef['qualifiers']) ? (int)$stageDef['qualifiers'] : null);

        $stageId = createStage(
            $conn,
            $leagueId,
            (string)$stageDef['name'],
            (string)$stageDef['type'],
            $stageIndex + 1,
            $qualifiers,
            !empty($stageDef['carries']),
            isset($stageDef['format']) ? (string)$stageDef['format'] : null
        );
        if ($stageId <= 0) continue;
        $createdStages++;

        $rounds = isset($stageSettings['rounds'])
            ? (int)$stageSettings['rounds']
            : (isset($stageDef['rounds']) ? (int)$stageDef['rounds'] : 0);
        if ($rounds > 0) updateStagePlannedRounds($conn, $stageId, $rounds);

        $currentGroupIds = [];
        $stageGroups = $stageDef['groups'] ?? [];
        if ($stageGroups && isset($stageSettings['groups'])) {
            $groupCount = max(1, min(8, (int)$stageSettings['groups']));
            $stageGroups = defaultStageGroupNames($groupCount, (string)$stageDef['type']);
        }
        foreach ($stageGroups as $groupIndex => $groupName) {
            $currentGroupIds[$groupIndex] = createStageGroup($conn, $stageId, (string)$groupName, $groupIndex + 1);
        }

        // A pool can declare which groups of the previous stage feed it
        // (ANP2026: Pool 1 <- Bracket A + D, Pool 2 <- Bracket B + C).
        // "all" merges every previous group into the first target group,
        // which is how Groups → knockout builds one qualifier pool.
        $sourceMap = $stageDef['sources'] ?? [];
        if ($sourceMap === 'all') {
            $sourceMap = $previousGroupIds ? [array_keys($previousGroupIds)] : [];
        }
        foreach ($sourceMap as $groupIndex => $sourceIndexes) {
            $targetGroupId = (int)($currentGroupIds[$groupIndex] ?? 0);
            if ($targetGroupId <= 0) continue;

            $sourceIds = [];
            foreach ((array)$sourceIndexes as $sourceIndex) {
                $sourceId = (int)($previousGroupIds[(int)$sourceIndex] ?? 0);
                if ($sourceId > 0) $sourceIds[] = $sourceId;
            }
            if ($sourceIds) setStageGroupSources($conn, $targetGroupId, $sourceIds);
        }

        $previousGroupIds = $currentGroupIds;
    }

    return $createdStages;
}

/**
 * Shape of a stage skeleton: "<type>:<groupCount>:<format>|…".
 *
 * Only the layout the presets define is compared, so renamed stages or edited
 * qualifier counts still match their preset. Knockout group counts are ignored
 * because promotion creates temporary groups inside the target stage.
 *
 * @param array<int,array<string,mixed>> $stages rows shaped like getLeagueStages()
 *        entries with a 'groups' key (or preset stage definitions).
 */
function leagueStructureSignature(array $stages): string {
    $parts = [];
    foreach ($stages as $stage) {
        $type = (string)($stage['stage_type'] ?? $stage['type'] ?? '');
        // The format separates structures that share a stage type — a single
        // and a double elimination stage are both "knockout", and a Swiss stage
        // is a "group" stage, so type + group count alone is ambiguous.
        $format = stageFormat([
            'stage_type'   => $type,
            'stage_format' => (string)($stage['stage_format'] ?? $stage['format'] ?? ''),
        ]);
        /* Only table stages carry their group count in the preset signature.
           A later knockout stage receives temporary "A Pool"/"B Pool" groups
           when its qualifiers are promoted; those containers must not make the
           skeleton look hand-built. */
        $groupCount = in_array($type, ['group', 'crossover'], true) ? count($stage['groups'] ?? []) : 0;
        $parts[] = $type . ':' . $groupCount . ':' . $format;
    }
    return implode('|', $parts);
}

/**
 * Recognise which preset produced a league's stage skeleton.
 *
 * @param array<int,array<string,mixed>> $stages rows from getLeagueStages() with
 *        each row's 'groups' filled in.
 * @return string|null the preset key ('custom' when the league has no stages, as
 *         the custom preset is empty), or null when the skeleton was changed by
 *         hand and no longer matches a preset.
 */
function detectLeagueStructure(array $stages): ?string {
    $signature = leagueStructureSignature($stages);
    foreach (leagueStructurePresets() as $key => $preset) {
        $presetStages = [];
        foreach (($preset['stages'] ?? []) as $stage) {
            $presetStages[] = [
                'type'   => $stage['type'],
                'format' => $stage['format'] ?? '',
                'groups' => $stage['groups'] ?? [],
            ];
        }
        if (leagueStructureSignature($presetStages) === $signature) return $key;
    }

    /* Dynamic Groups → knockout: the preset supports 2-8 groups, so the exact
       signature only matches the two-group default. Recognise any group count
       in that range followed by one single-elimination knockout (optionally a
       trailing placement stage). */
    $rows = array_values($stages);
    if (count($rows) >= 2) {
        $first  = $rows[0];
        $second = $rows[1];
        $firstType   = (string)($first['stage_type'] ?? '');
        $firstGroups = count($first['groups'] ?? []);
        $secondType  = (string)($second['stage_type'] ?? '');
        $secondFormat = $secondType === 'knockout' ? stageFormat($second) : '';

        $trailingOk = true;
        foreach (array_slice($rows, 2) as $later) {
            if ((string)($later['stage_type'] ?? '') !== 'placement') { $trailingOk = false; break; }
        }

        if ($firstType === 'group'
            && $firstGroups >= 2 && $firstGroups <= 8
            && $secondType === 'knockout'
            && $secondFormat === 'single_elimination'
            && $trailingOk) {
            return 'groups_knockout';
        }
    }

    return null;
}

/**
 * Add the playoff half of round_robin_playoffs to an existing plain round robin.
 *
 * The league stage, its group and every team placement are left untouched; only
 * the empty Playoffs stage is appended. Returns the new stage id, or 0 when the
 * league is not in the expected one-group round-robin shape.
 */
function addPlayoffsStageToRoundRobin(mysqli $conn, int $leagueId): int {
    $stages = getLeagueStages($conn, $leagueId);
    if (count($stages) !== 1) return 0;
    $stage = $stages[0];
    if ((string)($stage['stage_type'] ?? '') !== 'group') return 0;
    if (count(getStageGroups($conn, (int)$stage['id'])) !== 1) return 0;

    $stageId = createStageWithGroups($conn, $leagueId, 'Playoffs', 'knockout', ['Qualifiers'], null, false, 'single_elimination');
    if ($stageId <= 0) return 0;
    return count(getStageGroups($conn, $stageId)) > 0 ? $stageId : 0;
}

/**
 * Guard for applying or changing a league's structure preset.
 *
 * The skeleton can be applied or replaced at any time **before the first match
 * exists**: replacing deletes the league's stages (and, by cascade, their groups,
 * pool sources and team placements) and builds the new skeleton. Once matches
 * exist the guard refuses, because `tbl_schedule.stage_id` has no foreign key and
 * old matches would silently fall out of every stage table.
 *
 * @return string|null user-facing error message, or null when it may be applied.
 */
function structurePresetApplyError(string $structureKey, int $matchCount): ?string {
    $presets = leagueStructurePresets();
    if ($structureKey === '' || !array_key_exists($structureKey, $presets)) {
        return 'Choose one of the built-in competition structures.';
    }
    if ($structureKey === 'custom') {
        return 'A custom structure has no stages to create. Choose one of the built-in presets.';
    }
    if ($matchCount > 0) {
        return 'This league already has matches, so the structure can no longer be replaced. Delete those matches on the Schedule page first, or use Discard setup & start over on Setup.';
    }
    return null;
}

/**
 * Replace a league's stage skeleton with a preset.
 *
 * Deleting the stages cascades to stage groups, pool sources and team placements
 * (see database/migrations/034_anp2026_stages_and_rules.sql). Call this inside a
 * transaction, and only after structurePresetApplyError() returned null.
 *
 * @param array<int,array<string,int>> $settings editable values from the Format step
 * @return int number of stages created by the new preset
 */
function replaceLeagueStructure(mysqli $conn, int $leagueId, string $structureKey, array $settings = []): int {
    $stmt = $conn->prepare("DELETE FROM tbl_league_stage WHERE league_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $leagueId);
        $stmt->execute();
        $stmt->close();
    }
    return applyLeagueStructureTemplate($conn, $leagueId, $structureKey, $settings);
}
