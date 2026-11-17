<?php
/**
 * Tournament format engine
 * ------------------------
 * The single place that understands the four competition formats the LMS now
 * uses, for both setup and scheduling:
 *
 *   round_robin          every team plays every other team (groups + standings)
 *   swiss                fixed number of rounds; each round pairs equal records
 *   single_elimination   one bracket; a loss eliminates
 *   double_elimination   winners bracket + losers bracket + grand final
 *
 * The file is deliberately split in two halves:
 *
 *   1. PURE functions (no database, no globals) that build the bracket node
 *      graph, the Swiss pairings and the round-robin rounds. They are unit
 *      tested in tests/Unit/TournamentEngineTest.php.
 *   2. DB functions that persist a plan, propagate a result to the next node
 *      (winner forward, loser down to the losers bracket) and manage stage
 *      transitions/seeding.
 *
 * A bracket NODE is one match. Its key is unique inside a stage:
 *   W-R2-M1  winners bracket, round 2, match 1
 *   L-R3-M2  losers bracket,  round 3, match 2
 *   GF-M1    grand final
 * A node stores where its winner goes (next_node_key/next_node_slot) and where
 * its loser goes (loser_next_node_key/loser_next_node_slot) — see migration
 * 041_tournament_structures.sql.
 */

/* =========================================================================
 * 1. Format registry
 * ====================================================================== */

/**
 * Every stage format, its coarse stage type and how the UI treats it.
 *
 * @return array<string,array{label:string,type:string,short:string,side:bool}>
 */
function tournamentFormats(): array
{
    return [
        'round_robin' => [
            'label' => 'Round robin',
            'short' => 'Round robin',
            'type'  => 'group',
            'side'  => false,
        ],
        'swiss' => [
            'label' => 'Swiss system',
            'short' => 'Swiss',
            'type'  => 'group',
            'side'  => false,
        ],
        'single_elimination' => [
            'label' => 'Single elimination',
            'short' => 'Single elim',
            'type'  => 'knockout',
            'side'  => true,
        ],
        'double_elimination' => [
            'label' => 'Double elimination',
            'short' => 'Double elim',
            'type'  => 'knockout',
            'side'  => true,
        ],
        'placement' => [
            'label' => 'Placement match',
            'short' => 'Placement',
            'type'  => 'placement',
            'side'  => false,
        ],
    ];
}

/** @return array<string,string> format => label */
function tournamentFormatOptions(): array
{
    $options = [];
    foreach (tournamentFormats() as $key => $format) {
        if ($key === 'placement') continue;
        $options[$key] = $format['label'];
    }
    return $options;
}

/** Coarse stage type (group / knockout / placement) of a format. */
function stageTypeForFormat(string $format): string
{
    $formats = tournamentFormats();
    return $formats[$format]['type'] ?? 'group';
}

/** Default format of a coarse stage type. */
function stageFormatForType(string $type): string
{
    if ($type === 'knockout')  return 'single_elimination';
    if ($type === 'placement') return 'placement';
    return 'round_robin';
}

/**
 * Format of a stage row.
 *
 * Falls back to the stage type when the stored value is missing **or disagrees
 * with the stage type** — that is how a database upgraded through the runtime
 * schema guard behaves before its `stage_format` backfill: every row carries the
 * column default `round_robin`, which must not turn a knockout stage into a table.
 *
 * @param array<string,mixed> $stage row from getLeagueStages()
 */
function stageFormat(array $stage): string
{
    $type   = (string)($stage['stage_type'] ?? 'group');
    $format = (string)($stage['stage_format'] ?? '');
    if ($format === '' || !array_key_exists($format, tournamentFormats())) {
        return stageFormatForType($type);
    }

    $formatType = stageTypeForFormat($format);
    $compatible = $formatType === $type || ($formatType === 'group' && $type === 'crossover');
    return $compatible ? $format : stageFormatForType($type);
}

/** Does this format run as a bracket (winners/losers graph)? */
function stageFormatUsesBracket(string $format): bool
{
    return in_array($format, ['single_elimination', 'double_elimination'], true);
}

/** Does this format keep a table (standings) instead of a bracket? */
function stageFormatUsesTable(string $format): bool
{
    return in_array($format, ['round_robin', 'swiss'], true);
}

/**
 * Decoded per-stage options from tbl_league_stage.settings_json.
 *
 * @return array<string,mixed>
 */
function stageSettings(array $stage): array
{
    $raw = (string)($stage['settings_json'] ?? '');
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** One value from the stage settings, or $default when it is absent. */
function stageSetting(array $stage, string $key, $default = null)
{
    $settings = stageSettings($stage);
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

/** Merge and persist stage options. Callers must keep the keys they do not own. */
function saveStageSettings(mysqli $conn, int $stageId, array $settings): bool
{
    $payload = $settings ? json_encode($settings, JSON_UNESCAPED_UNICODE) : null;
    if ($payload === false && $settings) return false;
    $stmt = $conn->prepare("UPDATE tbl_league_stage SET settings_json = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('si', $payload, $stageId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/* =========================================================================
 * 2. Pure: bracket geometry
 * ====================================================================== */

/** Next power of two that can hold the teams (minimum 2). */
function bracketSize(int $teamCount): int
{
    $size = 2;
    while ($size < $teamCount) $size *= 2;
    return $size;
}

/** Number of winners-bracket rounds for a team count. */
function bracketRoundCount(int $teamCount): int
{
    $size = bracketSize($teamCount);
    $rounds = 0;
    while ($size > 1) { $size = intdiv($size, 2); $rounds++; }
    return $rounds;
}

/** Stable node key: W-R2-M3 / L-R1-M2 / GF-M1. */
function bracketNodeKey(string $side, int $round, int $index): string
{
    if ($side === 'grand_final') return 'GF-M' . $index;
    $prefix = $side === 'losers' ? 'L' : ($side === 'consolation' ? 'C' : 'W');
    return $prefix . '-R' . $round . '-M' . $index;
}

/**
 * Human round label, counted from the end so the last round always reads
 * "Final" whatever the team count is.
 */
function bracketRoundLabel(int $round, int $totalRounds, string $side = 'winners'): string
{
    $fromEnd = $totalRounds - $round + 1;
    $name = 'Round ' . $round;
    if ($fromEnd === 1)      $name = 'Final';
    elseif ($fromEnd === 2)  $name = 'Semifinal';
    elseif ($fromEnd === 3)  $name = 'Quarterfinal';
    elseif ($fromEnd === 4)  $name = 'Round of 16';
    elseif ($fromEnd === 5)  $name = 'Round of 32';

    if ($side === 'losers')      return 'Losers ' . $name;
    if ($side === 'grand_final') return $round > 1 ? 'Grand Final reset' : 'Grand Final';
    return $name;
}

/**
 * Standard seeded order for a bracket of $size slots (1 v 16, 8 v 9 ...).
 *
 * @return int[]
 */
function bracketSeedOrder(int $size): array
{
    $order = [1];
    while (count($order) < $size) {
        $next = [];
        $sum  = count($order) * 2 + 1;
        foreach ($order as $seed) {
            $next[] = $seed;
            $next[] = $sum - $seed;
        }
        $order = $next;
    }
    return $order;
}

/**
 * First-round pairs as seed numbers; seed 0 marks a bye.
 *
 * @return array<int,array{0:int,1:int}>
 */
function bracketSeedPairs(int $teamCount): array
{
    if ($teamCount < 2) return [];
    $size  = bracketSize($teamCount);
    $order = bracketSeedOrder($size);
    $pairs = [];
    for ($i = 0; $i < $size; $i += 2) {
        $a = (int)$order[$i];
        $b = (int)$order[$i + 1];
        $pairs[] = [($a <= $teamCount) ? $a : 0, ($b <= $teamCount) ? $b : 0];
    }
    return $pairs;
}

/**
 * Series length of one round: the last round uses final_best_of when it is set.
 *
 * @param array{best_of?:int,final_best_of?:int} $options
 */
function bracketBestOf(int $round, int $totalRounds, array $options = []): ?int
{
    if ($round >= $totalRounds && !empty($options['final_best_of'])) return (int)$options['final_best_of'];
    $bestOf = (int)($options['best_of'] ?? 0);
    return $bestOf > 0 ? $bestOf : null;
}

/**
 * Single-elimination node graph.
 *
 * @param array{best_of?:int,final_best_of?:int} $options
 * @return array<string,array<string,mixed>> node key => node
 */
function singleEliminationPlan(int $teamCount, array $options = []): array
{
    if ($teamCount < 2) return [];
    $rounds = bracketRoundCount($teamCount);
    $pairs  = bracketSeedPairs($teamCount);
    $nodes  = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $count = intdiv(bracketSize($teamCount), 2 ** $round);
        for ($index = 1; $index <= $count; $index++) {
            $key  = bracketNodeKey('winners', $round, $index);
            $node = [
                'key'        => $key,
                'side'       => 'winners',
                'round'      => $round,
                'index'      => $index,
                'label'      => bracketRoundLabel($round, $rounds, 'winners'),
                'slots'      => ['a' => null, 'b' => null],
                'next'       => null,
                'loser_next' => null,
                'best_of'    => bracketBestOf($round, $rounds, $options),
            ];

            if ($round === 1) {
                $pair = $pairs[$index - 1] ?? [0, 0];
                $node['slots']['a'] = $pair[0] > 0 ? ['type' => 'seed', 'seed' => $pair[0]] : ['type' => 'bye'];
                $node['slots']['b'] = $pair[1] > 0 ? ['type' => 'seed', 'seed' => $pair[1]] : ['type' => 'bye'];
            } else {
                $node['slots']['a'] = ['type' => 'winner', 'node' => bracketNodeKey('winners', $round - 1, 2 * $index - 1)];
                $node['slots']['b'] = ['type' => 'winner', 'node' => bracketNodeKey('winners', $round - 1, 2 * $index)];
            }

            if ($round < $rounds) {
                $node['next'] = [
                    'node' => bracketNodeKey('winners', $round + 1, intdiv($index - 1, 2) + 1),
                    'slot' => ($index % 2 === 1) ? 'a' : 'b',
                ];
            }
            $nodes[$key] = $node;
        }
    }

    return $nodes;
}

/**
 * Double-elimination node graph: winners bracket, losers bracket and grand
 * final, with every winner/loser link resolved up front.
 *
 * Geometry for a bracket of $size slots and $k = log2($size) winners rounds:
 *   winners round r        size/2^r matches
 *   losers round j (odd)   size/2^(m+1) matches, m = (j+1)/2 -> pairs LB winners
 *   losers round j (even)  size/2^(m+1) matches, m = j/2     -> LB winner v WB loser
 *   losers rounds total    2 * (k - 1)
 *
 * @param array{best_of?:int,final_best_of?:int} $options
 * @return array<string,array<string,mixed>> node key => node
 */
function doubleEliminationPlan(int $teamCount, array $options = []): array
{
    if ($teamCount < 2) return [];
    $size    = bracketSize($teamCount);
    $rounds  = bracketRoundCount($teamCount);
    $pairs   = bracketSeedPairs($teamCount);
    $lbTotal = max(0, 2 * ($rounds - 1));
    $nodes   = [];

    /* --- Winners bracket ------------------------------------------------- */
    for ($round = 1; $round <= $rounds; $round++) {
        $count = intdiv($size, 2 ** $round);
        for ($index = 1; $index <= $count; $index++) {
            $key  = bracketNodeKey('winners', $round, $index);
            $node = [
                'key'        => $key,
                'side'       => 'winners',
                'round'      => $round,
                'index'      => $index,
                'label'      => bracketRoundLabel($round, $rounds, 'winners'),
                'slots'      => ['a' => null, 'b' => null],
                'next'       => null,
                'loser_next' => null,
                'best_of'    => bracketBestOf($round, $rounds, $options),
            ];

            if ($round === 1) {
                $pair = $pairs[$index - 1] ?? [0, 0];
                $node['slots']['a'] = $pair[0] > 0 ? ['type' => 'seed', 'seed' => $pair[0]] : ['type' => 'bye'];
                $node['slots']['b'] = $pair[1] > 0 ? ['type' => 'seed', 'seed' => $pair[1]] : ['type' => 'bye'];
            } else {
                $node['slots']['a'] = ['type' => 'winner', 'node' => bracketNodeKey('winners', $round - 1, 2 * $index - 1)];
                $node['slots']['b'] = ['type' => 'winner', 'node' => bracketNodeKey('winners', $round - 1, 2 * $index)];
            }

            $node['next'] = ($round < $rounds)
                ? ['node' => bracketNodeKey('winners', $round + 1, intdiv($index - 1, 2) + 1), 'slot' => ($index % 2 === 1) ? 'a' : 'b']
                : ['node' => bracketNodeKey('grand_final', 1, 1), 'slot' => 'a'];

            if ($lbTotal > 0) {
                $node['loser_next'] = ($round === 1)
                    ? ['node' => bracketNodeKey('losers', 1, intdiv($index - 1, 2) + 1), 'slot' => ($index % 2 === 1) ? 'a' : 'b']
                    : ['node' => bracketNodeKey('losers', 2 * ($round - 1), $index), 'slot' => 'b'];
            } elseif ($round === 1) {
                // Two teams only: the winners-bracket loser goes straight to the final.
                $node['loser_next'] = ['node' => bracketNodeKey('grand_final', 1, 1), 'slot' => 'b'];
            }

            $nodes[$key] = $node;
        }
    }

    /* --- Losers bracket -------------------------------------------------- */
    for ($j = 1; $j <= $lbTotal; $j++) {
        $m       = intdiv($j + 1, 2);
        $count   = intdiv($size, 2 ** ($m + 1));
        $isMajor = ($j % 2 === 0);
        for ($index = 1; $index <= $count; $index++) {
            $key  = bracketNodeKey('losers', $j, $index);
            $node = [
                'key'        => $key,
                'side'       => 'losers',
                'round'      => $j,
                'index'      => $index,
                'label'      => 'Losers ' . bracketRoundLabel($m, max(1, $rounds - 1), 'winners'),
                'slots'      => ['a' => null, 'b' => null],
                'next'       => null,
                'loser_next' => null,
                'best_of'    => bracketBestOf($m, $rounds, $options),
            ];

            if ($isMajor) {
                // Winner of the previous losers round meets the loser of WB round m+1.
                $node['slots']['a'] = ['type' => 'winner', 'node' => bracketNodeKey('losers', $j - 1, $index)];
                $node['slots']['b'] = ['type' => 'loser',  'node' => bracketNodeKey('winners', $m + 1, $index)];
            } elseif ($j === 1) {
                $node['slots']['a'] = ['type' => 'loser', 'node' => bracketNodeKey('winners', 1, 2 * $index - 1)];
                $node['slots']['b'] = ['type' => 'loser', 'node' => bracketNodeKey('winners', 1, 2 * $index)];
            } else {
                $node['slots']['a'] = ['type' => 'winner', 'node' => bracketNodeKey('losers', $j - 1, 2 * $index - 1)];
                $node['slots']['b'] = ['type' => 'winner', 'node' => bracketNodeKey('losers', $j - 1, 2 * $index)];
            }

            if (!$isMajor) {
                // Minor round: its winners meet the next major round one-for-one.
                $node['next'] = ['node' => bracketNodeKey('losers', $j + 1, $index), 'slot' => 'a'];
            } elseif ($j === $lbTotal) {
                $node['next'] = ['node' => bracketNodeKey('grand_final', 1, 1), 'slot' => 'b'];
            } else {
                $node['next'] = [
                    'node' => bracketNodeKey('losers', $j + 1, intdiv($index - 1, 2) + 1),
                    'slot' => ($index % 2 === 1) ? 'a' : 'b',
                ];
            }
            $nodes[$key] = $node;
        }
    }

    /* --- Grand final ----------------------------------------------------- */
    $nodes[bracketNodeKey('grand_final', 1, 1)] = [
        'key'        => bracketNodeKey('grand_final', 1, 1),
        'side'       => 'grand_final',
        'round'      => 1,
        'index'      => 1,
        'label'      => 'Grand Final',
        'slots'      => [
            'a' => ['type' => 'winner', 'node' => bracketNodeKey('winners', $rounds, 1)],
            'b' => $lbTotal > 0
                ? ['type' => 'winner', 'node' => bracketNodeKey('losers', $lbTotal, 1)]
                : ['type' => 'loser', 'node' => bracketNodeKey('winners', 1, 1)],
        ],
        'next'       => null,
        'loser_next' => null,
        'best_of'    => bracketBestOf($rounds, $rounds, $options),
    ];

    return $nodes;
}

/** Build the plan that belongs to a format. */
function bracketPlanForFormat(string $format, int $teamCount, array $options = []): array
{
    if ($format === 'double_elimination') return doubleEliminationPlan($teamCount, $options);
    if ($format === 'single_elimination') return singleEliminationPlan($teamCount, $options);
    return [];
}

/* =========================================================================
 * 3. Pure: round robin and Swiss
 * ====================================================================== */

/**
 * Round-robin rounds using the circle method. An odd team count gets one bye
 * per round (the bye marker 0 is dropped from the pair list).
 *
 * @param int[] $teamIds
 * @return array<int,array<int,array{0:int,1:int}>> round number (1-based) => pairs
 */
function roundRobinRounds(array $teamIds): array
{
    $teams = array_values(array_map('intval', $teamIds));
    if (count($teams) < 2) return [];
    if (count($teams) % 2 === 1) $teams[] = 0;

    $slots  = count($teams);
    $rounds = [];
    for ($round = 0; $round < $slots - 1; $round++) {
        $pairs = [];
        for ($i = 0; $i < intdiv($slots, 2); $i++) {
            $a = (int)$teams[$i];
            $b = (int)$teams[$slots - 1 - $i];
            if ($a === 0 || $b === 0) continue;
            $pairs[] = ($round % 2 === 0) ? [$a, $b] : [$b, $a];
        }
        if ($pairs) $rounds[$round + 1] = $pairs;

        // Keep the first slot fixed and rotate the rest.
        $fixed = array_shift($teams);
        $last  = array_pop($teams);
        array_unshift($teams, $last);
        array_unshift($teams, $fixed);
    }
    return $rounds;
}

/**
 * Pair one pool of equal-record teams, alternating between the standard
 * "1 v 2, 3 v 4" fold and the "top half v bottom half" shift. The variant with
 * the fewest rematches wins, so a repeat pairing is only ever a last resort.
 *
 * @param array<int,array{team_id:int}> $pool
 * @param array<int,array<int,bool>>    $played team id => opponent id => true
 * @return array<int,array{a:int,b:int}>
 */
function swissPairPool(array $pool, array $played = []): array
{
    $pool = array_values($pool);
    if (count($pool) < 2) return [];

    $fold = [];
    for ($i = 0; $i + 1 < count($pool); $i += 2) {
        $fold[] = ['a' => (int)$pool[$i]['team_id'], 'b' => (int)$pool[$i + 1]['team_id']];
    }

    $shift = [];
    $half  = intdiv(count($pool), 2);
    for ($i = 0; $i < $half; $i++) {
        $shift[] = ['a' => (int)$pool[$i]['team_id'], 'b' => (int)$pool[$i + $half]['team_id']];
    }

    $rematches = static function (array $pairs) use ($played): int {
        $count = 0;
        foreach ($pairs as $pair) {
            if (!empty($played[$pair['a']][$pair['b']])) $count++;
        }
        return $count;
    };

    return $rematches($shift) < $rematches($fold) ? $shift : $fold;
}

/**
 * Round 1 of a Swiss stage: top half v bottom half (1 v N/2+1, 2 v N/2+2 …);
 * the lowest seed takes the bye when the count is odd.
 *
 * @param int[] $teamIds ordered best seed first
 * @return array{pairs:array<int,array{a:int,b:int}>,bye:?int}
 */
function swissFirstRound(array $teamIds): array
{
    $teams = array_values(array_map('intval', $teamIds));
    $bye = null;
    if (count($teams) % 2 === 1) $bye = array_pop($teams);
    if (count($teams) < 2) return ['pairs' => [], 'bye' => $bye];

    $half  = intdiv(count($teams), 2);
    $pairs = [];
    for ($i = 0; $i < $half; $i++) {
        $pairs[] = ['a' => (int)$teams[$i], 'b' => (int)$teams[$i + $half]];
    }
    return ['pairs' => $pairs, 'bye' => $bye];
}

/**
 * Swiss pairings for one round.
 *
 * Teams are grouped by match points (best group first). A group with an odd
 * number of teams floats its lowest-ranked team down to the next group — the
 * standard float rule. Teams that already met are avoided by swissPairPool();
 * when every option is a rematch the least-bad pairing is used.
 *
 * @param array<int,array<string,mixed>> $standings rows with team_id/id, points,
 *        optional had_bye and played (opponent ids)
 * @param array<int,array<string,mixed>> $playedPairs every pairing already used
 * @return array{pairs:array<int,array{a:int,b:int}>,bye:?int,unpaired:int[]}
 */
function swissPairings(array $standings, array $playedPairs = [], array $options = []): array
{
    $rows = [];
    foreach ($standings as $standing) {
        $teamId = (int)($standing['team_id'] ?? $standing['id'] ?? 0);
        if ($teamId <= 0) continue;
        $rows[] = [
            'team_id' => $teamId,
            'points'  => (int)($standing['points'] ?? 0),
            'had_bye' => !empty($standing['had_bye']),
            'played'  => array_map('intval', (array)($standing['played'] ?? [])),
        ];
    }
    if (count($rows) < 2) {
        return ['pairs' => [], 'bye' => null, 'unpaired' => array_column($rows, 'team_id')];
    }

    $played = [];
    foreach ($playedPairs as $pair) {
        $a = (int)($pair['a'] ?? $pair[0] ?? 0);
        $b = (int)($pair['b'] ?? $pair[1] ?? 0);
        if ($a <= 0 || $b <= 0) continue;
        $played[$a][$b] = true;
        $played[$b][$a] = true;
    }

    // Odd team count: the lowest-ranked team without a bye takes it.
    $bye = null;
    if (count($rows) % 2 === 1) {
        $byeIndex = count($rows) - 1;
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            if (empty($rows[$i]['had_bye'])) { $byeIndex = $i; break; }
        }
        $bye = (int)$rows[$byeIndex]['team_id'];
        array_splice($rows, $byeIndex, 1);
    }

    // Group by match points, strongest group first.
    $groups = [];
    foreach ($rows as $row) $groups[(string)$row['points']][] = $row;
    uksort($groups, static fn($a, $b) => (int)$b <=> (int)$a);

    $pairs = [];
    $unpaired = [];
    $carry = null;
    foreach ($groups as $pool) {
        if ($carry !== null) { array_unshift($pool, $carry); $carry = null; }
        if (count($pool) % 2 === 1) $carry = array_pop($pool);
        foreach (swissPairPool($pool, $played) as $pair) $pairs[] = $pair;
    }
    if ($carry !== null) $unpaired[] = (int)$carry['team_id'];

    return ['pairs' => $pairs, 'bye' => $bye, 'unpaired' => $unpaired];
}

/** Planned Swiss rounds for a team count: ceil(log2(teams)), minimum 3. */
function swissRoundCount(int $teamCount): int
{
    if ($teamCount <= 2) return 1;
    return max(3, (int)ceil(log($teamCount, 2)));
}

/* =========================================================================
 * 4. Database: schema guard
 * ====================================================================== */

/**
 * Make sure the columns/tables from migration 041 exist.
 *
 * The migration file is the source of truth, but the LMS is deployed by copying
 * files, so this runtime guard adds anything the upgrade missed. It runs at most
 * once per request and never drops or rewrites data.
 */
function ensureTournamentEngineSchema(mysqli $conn): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $ready = false;

    $specs = [
        'tbl_league_stage' => [
            'stage_format'    => "ADD COLUMN stage_format ENUM('round_robin','single_elimination','double_elimination','swiss','placement') NOT NULL DEFAULT 'round_robin' AFTER stage_type",
            'stage_status'    => "ADD COLUMN stage_status ENUM('Pending','Active','Locked','Complete') NOT NULL DEFAULT 'Active' AFTER stage_format",
            'rounds_planned'  => "ADD COLUMN rounds_planned INT NULL AFTER stage_status",
            'settings_json'   => "ADD COLUMN settings_json TEXT NULL AFTER rounds_planned",
            'activated_at'    => "ADD COLUMN activated_at DATETIME NULL AFTER settings_json",
            'locked_at'       => "ADD COLUMN locked_at DATETIME NULL AFTER activated_at",
        ],
        'tbl_schedule' => [
            'bracket_side'         => "ADD COLUMN bracket_side ENUM('winners','losers','grand_final','consolation') NULL AFTER seed_b",
            'node_key'             => "ADD COLUMN node_key VARCHAR(24) NULL AFTER bracket_side",
            'next_node_key'        => "ADD COLUMN next_node_key VARCHAR(24) NULL AFTER node_key",
            'next_node_slot'       => "ADD COLUMN next_node_slot ENUM('a','b') NULL AFTER next_node_key",
            'loser_next_node_key'  => "ADD COLUMN loser_next_node_key VARCHAR(24) NULL AFTER next_node_slot",
            'loser_next_node_slot' => "ADD COLUMN loser_next_node_slot ENUM('a','b') NULL AFTER loser_next_node_key",
            'swiss_round'          => "ADD COLUMN swiss_round INT NULL AFTER loser_next_node_slot",
            'locked'               => "ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0 AFTER swiss_round",
            'locked_at'            => "ADD COLUMN locked_at DATETIME NULL AFTER locked",
        ],
    ];

    foreach ($specs as $table => $columns) {
        $existing = [];
        if ($result = @$conn->query("SHOW COLUMNS FROM `$table`")) {
            while ($row = $result->fetch_assoc()) $existing[(string)$row['Field']] = true;
            $result->free();
        }
        if (empty($existing)) return $ready;

        $add = [];
        foreach ($columns as $column => $sql) {
            if (!isset($existing[$column])) $add[] = $sql;
        }
        if ($add) @$conn->query("ALTER TABLE `$table` " . implode(', ', $add));
    }

    /* DDL causes an implicit COMMIT in MySQL, so only create the tables when
       they are actually missing. Running CREATE TABLE IF NOT EXISTS on every
       request silently committed callers' bracket-generation transactions. */
    $transitionTable = @$conn->query("SHOW TABLES LIKE 'tbl_league_stage_transition'");
    $hasTransitionTable = $transitionTable && $transitionTable->num_rows > 0;
    if ($transitionTable) $transitionTable->free();
    if (!$hasTransitionTable) {
        @$conn->query("CREATE TABLE IF NOT EXISTS tbl_league_stage_transition (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            league_id INT NOT NULL,
            from_stage_id INT NULL,
            to_stage_id INT NOT NULL,
            rule ENUM('top_per_group','top_overall','bottom_consolation') NOT NULL DEFAULT 'top_per_group',
            advance_count INT NOT NULL DEFAULT 2,
            settings_json TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_stage_transition_target (to_stage_id),
            KEY idx_stage_transition_league (league_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $slotTable = @$conn->query("SHOW TABLES LIKE 'tbl_league_stage_slot'");
    $hasSlotTable = $slotTable && $slotTable->num_rows > 0;
    if ($slotTable) $slotTable->free();
    if (!$hasSlotTable) {
        @$conn->query("CREATE TABLE IF NOT EXISTS tbl_league_stage_slot (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            stage_id INT NOT NULL,
            slot_code VARCHAR(24) NOT NULL,
            label VARCHAR(80) NOT NULL,
            slot_order INT NOT NULL DEFAULT 1,
            source_stage_id INT NULL,
            source_group_id INT NULL,
            source_rank INT NULL,
            source_kind ENUM('seed','group_rank','group_winner','loser','manual','bye') NOT NULL DEFAULT 'manual',
            team_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_stage_slot (stage_id, slot_code),
            KEY idx_stage_slot_order (stage_id, slot_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Keep legacy rows in step with their stage type (same backfill as the
    // migration) so a knockout stage never reads as a round robin.
    @$conn->query("UPDATE tbl_league_stage SET stage_format = 'single_elimination'
                    WHERE stage_type = 'knockout' AND stage_format = 'round_robin'");
    @$conn->query("UPDATE tbl_league_stage SET stage_format = 'placement'
                    WHERE stage_type = 'placement' AND stage_format = 'round_robin'");

    // Final verification: the new code needs every one of these columns.
    $required = ['tbl_league_stage' => ['stage_format', 'stage_status'], 'tbl_schedule' => ['node_key', 'next_node_key']];
    foreach ($required as $table => $columns) {
        $existing = [];
        if ($result = @$conn->query("SHOW COLUMNS FROM `$table`")) {
            while ($row = $result->fetch_assoc()) $existing[(string)$row['Field']] = true;
            $result->free();
        }
        foreach ($columns as $column) {
            if (!isset($existing[$column])) return $ready;
        }
    }
    foreach (['tbl_league_stage_transition', 'tbl_league_stage_slot'] as $table) {
        $check = @$conn->query("SHOW TABLES LIKE '$table'");
        if (!$check || $check->num_rows === 0) return $ready;
    }

    $ready = true;
    return $ready;
}

/* =========================================================================
 * 5. Database: bracket persistence and propagation
 * ====================================================================== */

/** Short label of a plan slot, stored in seed_a / seed_b for the bracket UI. */
function bracketSlotLabel($slot): ?string
{
    if (!is_array($slot)) return null;
    switch ((string)($slot['type'] ?? '')) {
        case 'seed':   return 'S' . (int)($slot['seed'] ?? 0);
        case 'bye':    return 'BYE';
        case 'winner': return 'W ' . bracketNodeShort((string)($slot['node'] ?? ''));
        case 'loser':  return 'L ' . bracketNodeShort((string)($slot['node'] ?? ''));
    }
    return null;
}

/** W-R1-M2 => R1M2 (seed columns hold 12 characters). */
function bracketNodeShort(string $key): string
{
    if (preg_match('/^[WL]-R(\d+)-M(\d+)$/', $key, $m)) return 'R' . $m[1] . 'M' . $m[2];
    return $key;
}

/** Insert one empty bracket node (a Draft row the organizer schedules later). */
function insertBracketNode(mysqli $conn, int $leagueId, int $stageId, ?int $stageGroupId, array $node, string $stageTag = 'knockout'): int
{
    $sql = "INSERT INTO tbl_schedule
              (league_id, team_a, team_b, round, status, is_automated, bracket, stage, stage_id, stage_group_id,
               seed_a, seed_b, best_of, venue, committee_user_id,
               bracket_side, node_key, next_node_key, next_node_slot, loser_next_node_key, loser_next_node_slot)
            VALUES (?, 0, 0, ?, 'Draft', 1, 'knockout', ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    $round     = (int)$node['round'];
    $seedA     = bracketSlotLabel($node['slots']['a'] ?? null);
    $seedB     = bracketSlotLabel($node['slots']['b'] ?? null);
    $bestOf    = isset($node['best_of']) ? (int)$node['best_of'] : null;
    $side      = (string)$node['side'];
    $nodeKey   = (string)$node['key'];
    $nextKey   = $node['next']['node'] ?? null;
    $nextSlot  = $node['next']['slot'] ?? null;
    $loserKey  = $node['loser_next']['node'] ?? null;
    $loserSlot = $node['loser_next']['slot'] ?? null;

    // league (i), round (i), stage tag (s), stage (i), group (i), seed a/b (s s),
    // best of (i), side (s), node + link keys/slots (s …)
    $stmt->bind_param(
        'iisiississssss',
        $leagueId, $round, $stageTag, $stageId, $stageGroupId,
        $seedA, $seedB, $bestOf, $side, $nodeKey,
        $nextKey, $nextSlot, $loserKey, $loserSlot
    );
    $ok = $stmt->execute();
    $id = $ok ? (int)$conn->insert_id : 0;
    $stmt->close();
    return $id;
}

/** Write a team into one side of a bracket match, never overwriting a filled side. */
function writeBracketSlot(mysqli $conn, int $matchId, string $slot, int $teamId): bool
{
    if ($matchId <= 0 || $teamId <= 0) return false;
    $column = $slot === 'b' ? 'team_b' : 'team_a';
    $stmt = $conn->prepare("UPDATE tbl_schedule SET `$column` = ? WHERE id = ? AND (`$column` = 0 OR `$column` IS NULL)");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $teamId, $matchId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();
    return $changed;
}

/** Write a team into the node named by key; returns true when the slot changed. */
function writeBracketSlotByKey(mysqli $conn, array $byKey, string $nodeKey, string $slot, int $teamId): bool
{
    $target = $byKey[$nodeKey] ?? null;
    if (!$target) return false;
    return writeBracketSlot($conn, (int)$target['id'], $slot, $teamId);
}

/** Every node of a stage, in visual order (winners, losers, grand final). */
function getStageBracketNodes(mysqli $conn, int $leagueId, int $stageId): array
{
    $rows = [];
    $sql = "SELECT * FROM tbl_schedule
            WHERE league_id = ? AND stage_id = ? AND node_key IS NOT NULL AND node_key <> ''
              AND (is_archived = 0 OR is_archived IS NULL)
            ORDER BY CASE bracket_side WHEN 'winners' THEN 1 WHEN 'losers' THEN 2 WHEN 'grand_final' THEN 3 ELSE 4 END,
                     round, node_key";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $rows;
    $stmt->bind_param('ii', $leagueId, $stageId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/** Loser of a decided match (0 when the result has no loser, e.g. a bye or draw). */
function bracketMatchLoser(array $row): int
{
    $winner = (int)($row['winner'] ?? 0);
    $a = (int)($row['team_a'] ?? 0);
    $b = (int)($row['team_b'] ?? 0);
    if ($winner <= 0) return 0;
    if ($winner === $a) return $b;
    if ($winner === $b) return $a;
    return 0;
}

/**
 * Reverse index of the graph: which node feeds which slot, and with what.
 *
 * @return array<string,array<string,array{node:string,kind:string}>>
 */
function bracketFeeds(array $rows): array
{
    $feeds = [];
    foreach ($rows as $row) {
        $key = (string)($row['node_key'] ?? '');
        if ($key === '') continue;
        if (!empty($row['next_node_key'])) {
            $feeds[(string)$row['next_node_key']][(string)($row['next_node_slot'] ?: 'a')] = ['node' => $key, 'kind' => 'winner'];
        }
        if (!empty($row['loser_next_node_key'])) {
            $feeds[(string)$row['loser_next_node_key']][(string)($row['loser_next_node_slot'] ?: 'a')] = ['node' => $key, 'kind' => 'loser'];
        }
    }
    return $feeds;
}

/**
 * What can still arrive in one slot of a bracket node?
 *   filled  — the team is already in the row
 *   waiting — a feeder match will fill it once it is decided
 *   empty   — no team can ever arrive (a bye or an unreachable slot)
 */
function bracketSlotState(array $row, string $slot, array $byKey, array $feeds, array $seen = []): string
{
    $team = (int)($slot === 'b' ? ($row['team_b'] ?? 0) : ($row['team_a'] ?? 0));
    if ($team > 0) return 'filled';

    $key = (string)($row['node_key'] ?? '');
    if ($key === '') return 'empty';

    $feed = $feeds[$key][$slot] ?? null;
    if ($feed === null) return 'empty';

    $sourceKey = (string)$feed['node'];
    if (isset($seen[$sourceKey])) return 'waiting';
    $source = $byKey[$sourceKey] ?? null;
    if ($source === null) return 'empty';

    $seen[$sourceKey] = true;
    $stateA = bracketSlotState($source, 'a', $byKey, $feeds, $seen);
    $stateB = bracketSlotState($source, 'b', $byKey, $feeds, $seen);
    if ($stateA === 'empty' && $stateB === 'empty') return 'empty';

    if (strtolower((string)($source['status'] ?? '')) === 'completed') {
        $value = $feed['kind'] === 'loser' ? bracketMatchLoser($source) : (int)$source['winner'];
        return $value > 0 ? 'waiting' : 'empty';
    }
    return 'waiting';
}

/** Copy every decided result one node forward (winner up, loser down). */
function syncBracketFeeds(mysqli $conn, int $leagueId, int $stageId): int
{
    $rows = getStageBracketNodes($conn, $leagueId, $stageId);
    if (empty($rows)) return 0;

    $byKey = [];
    foreach ($rows as $row) $byKey[(string)$row['node_key']] = $row;

    $writes = 0;
    foreach ($rows as $row) {
        if (strtolower((string)$row['status']) !== 'completed') continue;

        $winner = (int)$row['winner'];
        if ($winner > 0 && !empty($row['next_node_key'])) {
            $slot = (string)($row['next_node_slot'] ?: 'a');
            if (writeBracketSlotByKey($conn, $byKey, (string)$row['next_node_key'], $slot, $winner)) $writes++;
        }
        $loser = bracketMatchLoser($row);
        if ($loser > 0 && !empty($row['loser_next_node_key'])) {
            $slot = (string)($row['loser_next_node_slot'] ?: 'a');
            if (writeBracketSlotByKey($conn, $byKey, (string)$row['loser_next_node_key'], $slot, $loser)) $writes++;
        }
    }
    return $writes;
}

/**
 * Close a bracket row that has exactly one team: the team advances automatically.
 *
 * The surviving team is normalised to side A and the empty side is stamped
 * `seed_b = 'BYE'`. Older rows could hold the team on side B with a feeder
 * label on the empty side, which made the bracket view draw them as completed
 * finals instead of byes.
 */
function completeBracketBye(mysqli $conn, int $matchId, int $teamId): bool
{
    if ($matchId <= 0 || $teamId <= 0) return false;

    $stmt = $conn->prepare("SELECT team_a, team_b, seed_a, seed_b FROM tbl_schedule WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;

    $teamA = (int)$row['team_a'];
    $teamB = (int)$row['team_b'];
    if (($teamA > 0) === ($teamB > 0)) return false;
    if ($teamA !== $teamId && $teamB !== $teamId) return false;

    $seedA = $teamA === $teamId ? $row['seed_a'] : $row['seed_b'];
    $seedA = $seedA === null || trim((string)$seedA) === '' ? null : (string)$seedA;

    $stmt = $conn->prepare("UPDATE tbl_schedule
                               SET team_a = ?, team_b = 0, seed_a = ?, seed_b = 'BYE',
                                   status = 'Completed', winner = ?, a_score = 0, b_score = 0,
                                   published_at = NOW(), result_note = 'Bye — advances automatically'
                             WHERE id = ? AND LOWER(status) <> 'completed'");
    if (!$stmt) return false;
    $stmt->bind_param('isii', $teamId, $seedA, $teamId, $matchId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();
    return $changed;
}

/**
 * Collapse bye rows: a node with one team and an unreachable other side is
 * completed, and the team is pushed to the next node. Repeats until the graph
 * stops changing so a bye can cascade through several rounds.
 */
function collapseBracketByes(mysqli $conn, int $leagueId, int $stageId, int $maxPasses = 20): int
{
    $total = 0;
    for ($pass = 0; $pass < $maxPasses; $pass++) {
        $rows = getStageBracketNodes($conn, $leagueId, $stageId);
        if (empty($rows)) break;

        $byKey = [];
        foreach ($rows as $row) $byKey[(string)$row['node_key']] = $row;
        $feeds = bracketFeeds($rows);

        $changed = 0;
        foreach ($rows as $row) {
            if (strtolower((string)$row['status']) === 'completed') continue;
            if (empty($row['node_key'])) continue;

            $stateA = bracketSlotState($row, 'a', $byKey, $feeds);
            $stateB = bracketSlotState($row, 'b', $byKey, $feeds);

            $winner = 0;
            if ($stateA === 'filled' && $stateB === 'empty') $winner = (int)$row['team_a'];
            elseif ($stateB === 'filled' && $stateA === 'empty') $winner = (int)$row['team_b'];
            if ($winner <= 0) continue;

            if (completeBracketBye($conn, (int)$row['id'], $winner)) $changed++;
        }

        $total += $changed;
        if ($changed === 0) break;
        syncBracketFeeds($conn, $leagueId, $stageId);
    }
    return $total;
}

/** Fill everything that can be filled right now and return the number of byes. */
function resolveBracketPlaceholders(mysqli $conn, int $leagueId, int $stageId): int
{
    syncBracketFeeds($conn, $leagueId, $stageId);
    $byes = collapseBracketByes($conn, $leagueId, $stageId);
    syncBracketFeeds($conn, $leagueId, $stageId);
    $byes += collapseBracketByes($conn, $leagueId, $stageId);
    return $byes;
}

/** Persist the format/round count of a stage that just had a bracket drawn. */
function updateStageFormatState(mysqli $conn, int $stageId, string $format, ?int $rounds = null): void
{
    $stmt = $conn->prepare("UPDATE tbl_league_stage
                               SET stage_format = ?, stage_type = ?, stage_status = 'Active',
                                   rounds_planned = COALESCE(?, rounds_planned),
                                   activated_at = COALESCE(activated_at, NOW())
                             WHERE id = ?");
    if (!$stmt) return;
    $type = stageTypeForFormat($format);
    $stmt->bind_param('ssii', $format, $type, $rounds, $stageId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Generate a whole bracket graph for a stage from an ordered seed list.
 *
 * @param int[]  $seedTeamIds best seed first
 * @param string|null $format defaults to the stage's own format
 * @return array{errors:string[],created:int,ids:int[],byes:int,nodes:int,format:string}
 */
function createBracketForStage(mysqli $conn, int $leagueId, int $stageId, array $seedTeamIds, ?string $format = null, array $options = []): array
{
    $result = ['errors' => [], 'created' => 0, 'ids' => [], 'byes' => 0, 'nodes' => 0, 'format' => (string)$format];

    if (!ensureTournamentEngineSchema($conn)) {
        $result['errors'][] = 'The tournament tables are not ready. Run database/migrations/041_tournament_structures.sql.';
        return $result;
    }

    $stage = function_exists('getStage') ? getStage($conn, $stageId) : null;
    if (!$stage || (int)$stage['league_id'] !== $leagueId) {
        $result['errors'][] = 'That stage does not belong to this league.';
        return $result;
    }

    $format = $format ?: stageFormat($stage);
    $result['format'] = $format;
    if (!in_array($format, ['single_elimination', 'double_elimination'], true)) {
        $result['errors'][] = 'Only single and double elimination use a bracket.';
        return $result;
    }

    // Never append a second draw to a stage that already has fixtures. The
    // graph engine used to rely on every caller doing this check; keeping it
    // here protects the endpoint paths that call the engine directly.
    $existingStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule
                                    WHERE league_id = ? AND stage_id = ?
                                      AND (is_archived = 0 OR is_archived IS NULL)");
    if ($existingStmt) {
        $existingStmt->bind_param('ii', $leagueId, $stageId);
        $existingStmt->execute();
        $existingCount = (int)($existingStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $existingStmt->close();
        if ($existingCount > 0) {
            $result['errors'][] = 'This stage already has fixtures. Archive or delete them before drawing the bracket again.';
            return $result;
        }
    }

    $unique = [];
    foreach ($seedTeamIds as $teamId) {
        $teamId = (int)$teamId;
        if ($teamId > 0) $unique[$teamId] = true;
    }
    $teams = array_keys($unique);
    $teamCount = count($teams);
    if ($teamCount < 2) {
        $result['errors'][] = 'A bracket needs at least two teams.';
        return $result;
    }
    if ($teamCount > 128) {
        $result['errors'][] = 'A bracket supports up to 128 teams.';
        return $result;
    }

    $plan = bracketPlanForFormat($format, $teamCount, $options);
    if (empty($plan)) {
        $result['errors'][] = 'The bracket could not be planned for ' . $teamCount . ' teams.';
        return $result;
    }

    $stageGroupId = null;
    if (function_exists('getStageGroups')) {
        $groups = getStageGroups($conn, $stageId);
        if (!empty($groups)) $stageGroupId = (int)$groups[0]['id'];
    }

    $ids = [];
    foreach ($plan as $key => $node) {
        $id = insertBracketNode($conn, $leagueId, $stageId, $stageGroupId, $node, 'knockout');
        if ($id > 0) $ids[$key] = $id;
    }
    if (count($ids) !== count($plan)) {
        foreach ($ids as $id) $conn->query('DELETE FROM tbl_schedule WHERE id = ' . (int)$id);
        $result['errors'][] = 'The bracket could not be written. Please try again.';
        return $result;
    }

    // Seed the first round (later rounds are fed by their own links).
    foreach ($plan as $key => $node) {
        if (!isset($ids[$key])) continue;
        foreach (['a', 'b'] as $slot) {
            $source = $node['slots'][$slot] ?? null;
            if (!is_array($source) || ($source['type'] ?? '') !== 'seed') continue;
            $teamId = (int)($teams[(int)$source['seed'] - 1] ?? 0);
            if ($teamId > 0) writeBracketSlot($conn, $ids[$key], $slot, $teamId);
        }
    }

    $rounds = $format === 'double_elimination'
        ? max(1, 2 * (bracketRoundCount($teamCount) - 1) + 1)
        : bracketRoundCount($teamCount);
    updateStageFormatState($conn, $stageId, $format, $rounds);

    $result['ids']   = array_values($ids);
    $result['nodes'] = count($plan);
    $result['created'] = count($ids);
    $result['byes']  = resolveBracketPlaceholders($conn, $leagueId, $stageId);
    return $result;
}

/** The last node of a bracket: the grand final, or the final of a single bracket. */
function bracketFinalNode(mysqli $conn, int $leagueId, int $stageId): ?array
{
    foreach (getStageBracketNodes($conn, $leagueId, $stageId) as $row) {
        if (empty($row['next_node_key'])) return $row;
    }
    return null;
}

/** The optional second grand final of a double-elimination stage. */
function bracketResetFinalNode(mysqli $conn, int $leagueId, int $stageId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM tbl_schedule
                             WHERE league_id = ? AND stage_id = ? AND node_key = 'GF-M2'
                               AND (is_archived = 0 OR is_archived IS NULL)
                             ORDER BY id DESC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ii', $leagueId, $stageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Route GF-M1's winner and loser into GF-M2, or clear the link again. */
function setBracketResetLinks(mysqli $conn, int $gf1Id, bool $enabled): void
{
    if ($gf1Id <= 0) return;
    if ($enabled) {
        $stmt = $conn->prepare("UPDATE tbl_schedule
                                   SET next_node_key = 'GF-M2', next_node_slot = 'a',
                                       loser_next_node_key = 'GF-M2', loser_next_node_slot = 'b'
                                 WHERE id = ? AND node_key = 'GF-M1'");
    } else {
        $stmt = $conn->prepare("UPDATE tbl_schedule
                                   SET next_node_key = NULL, next_node_slot = NULL,
                                       loser_next_node_key = NULL, loser_next_node_slot = NULL
                                 WHERE id = ? AND node_key = 'GF-M1'");
    }
    if (!$stmt) return;
    $stmt->bind_param('i', $gf1Id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Create (or repair) the bracket-reset grand final.
 *
 * Both grand-finalists meet again after the losers-bracket entrant wins GF-M1.
 * The reset row is created on demand, never up front, so a reset that is not
 * required leaves no unused fixture behind.
 */
function ensureBracketResetFinal(mysqli $conn, int $leagueId, int $stageId, array $gf1, int $winnerId, int $loserId): int
{
    if ($winnerId <= 0 || $loserId <= 0) return 0;

    $existing = bracketResetFinalNode($conn, $leagueId, $stageId);
    if ($existing) {
        if (strtolower((string)$existing['status']) !== 'completed') {
            $repair = $conn->prepare("UPDATE tbl_schedule
                                         SET team_a = ?, team_b = ?, seed_a = 'GF1 W', seed_b = 'GF1 L'
                                       WHERE id = ? AND LOWER(status) <> 'completed'");
            if ($repair) {
                $existingId = (int)$existing['id'];
                $repair->bind_param('iii', $winnerId, $loserId, $existingId);
                $repair->execute();
                $repair->close();
            }
        }
        setBracketResetLinks($conn, (int)($gf1['id'] ?? 0), true);
        return (int)$existing['id'];
    }

    $stageGroupId = isset($gf1['stage_group_id']) && $gf1['stage_group_id'] !== null
        ? (int)$gf1['stage_group_id'] : null;
    $bestOf = !empty($gf1['best_of']) ? (int)$gf1['best_of'] : null;
    $node = [
        'key'        => 'GF-M2',
        'side'       => 'grand_final',
        'round'      => max(1, (int)($gf1['round'] ?? 1)) + 1,
        'index'      => 2,
        'slots'      => ['a' => null, 'b' => null],
        'next'       => null,
        'loser_next' => null,
        'best_of'    => $bestOf,
    ];

    $matchId = insertBracketNode($conn, $leagueId, $stageId, $stageGroupId, $node, 'knockout');
    if ($matchId <= 0) return 0;

    $fill = $conn->prepare("UPDATE tbl_schedule
                               SET team_a = ?, team_b = ?, seed_a = 'GF1 W', seed_b = 'GF1 L'
                             WHERE id = ?");
    if ($fill) {
        $fill->bind_param('iii', $winnerId, $loserId, $matchId);
        $fill->execute();
        $fill->close();
    }

    setBracketResetLinks($conn, (int)($gf1['id'] ?? 0), true);
    return $matchId;
}

/** Drop an unplayed reset final and restore GF-M1 as the terminal node. */
function clearBracketResetFinal(mysqli $conn, int $leagueId, int $stageId, int $gf1Id): int
{
    $reset = bracketResetFinalNode($conn, $leagueId, $stageId);
    if ($reset && strtolower((string)$reset['status']) === 'completed') return 0;

    $removed = 0;
    if ($reset) {
        $stmt = $conn->prepare("DELETE FROM tbl_schedule
                                 WHERE id = ? AND league_id = ? AND stage_id = ?
                                   AND node_key = 'GF-M2' AND LOWER(status) <> 'completed'");
        if ($stmt) {
            $resetId = (int)$reset['id'];
            $stmt->bind_param('iii', $resetId, $leagueId, $stageId);
            $stmt->execute();
            $removed = $stmt->affected_rows > 0 ? 1 : 0;
            $stmt->close();
        }
    }
    // Only release the graph links once no reset row remains (protects the
    // bracket if the delete itself failed).
    if (!bracketResetFinalNode($conn, $leagueId, $stageId)) {
        setBracketResetLinks($conn, $gf1Id, false);
    }
    return $removed;
}

/**
 * Progress one bracket match through the node graph.
 *
 * Winner  -> next_node_key/next_node_slot
 * Loser   -> loser_next_node_key/loser_next_node_slot (double elimination)
 * Loser   -> eliminated (single elimination, losers bracket, grand final)
 *
 * Exception: a GF-M1 losers-side win with bracket_reset enabled creates GF-M2
 * and keeps both teams alive; GF-M2 decides the champion.
 *
 * @return array<string,mixed> actions for tournamentProgression + notifications
 */
function progressBracketStageMatch(mysqli $conn, int $leagueId, int $scheduleId, int $winnerId, array $match): array
{
    $stageId  = (int)($match['stage_id'] ?? 0);
    $stage    = function_exists('getStage') ? getStage($conn, $stageId) : null;
    $format   = $stage ? stageFormat($stage) : 'single_elimination';
    $side     = (string)($match['bracket_side'] ?? 'winners');
    $isDouble = $format === 'double_elimination';
    $loser    = bracketMatchLoser($match);
    if (!isset($match['id'])) $match['id'] = $scheduleId;

    $actions = [
        'stage_id'     => $stageId,
        'stage_type'   => $stage['stage_type'] ?? 'knockout',
        'stage_format' => $format,
        'bracket'      => true,
        'bracket_side' => $side,
        'eliminated'   => [],
        'advanced'     => [],
    ];

    if ($winnerId > 0) $actions['advanced'][] = $winnerId;

    /* Double-elimination reset final: when the losers-bracket entrant wins
       GF-M1, both teams have one loss and meet again. The reset row is created
       on demand, so a winners-side GF1 win leaves no unused fixture. */
    $resetEnabled = $isDouble && $stage && !empty(stageSetting($stage, 'bracket_reset', 0));
    $isFirstGrandFinal = $isDouble && $side === 'grand_final'
        && (string)($match['node_key'] ?? '') === 'GF-M1';
    $resetRequired = false;
    if ($isFirstGrandFinal) {
        $winnersFinalist = (int)($match['team_a'] ?? 0);
        $losersFinalist  = (int)($match['team_b'] ?? 0);
        if ($resetEnabled && $winnerId > 0 && $loser > 0
            && $losersFinalist > 0 && $winnerId === $losersFinalist) {
            $resetId = ensureBracketResetFinal($conn, $leagueId, $stageId, $match, $winnerId, $loser);
            if ($resetId > 0) {
                $resetRequired = true;
                $actions['bracket_reset_required'] = true;
                $actions['bracket_reset_match_id'] = $resetId;
            }
        } else {
            // A winners-side win (or reset disabled) ends the tournament. Drop
            // an unplayed reset left behind by a corrected earlier result.
            clearBracketResetFinal($conn, $leagueId, $stageId, (int)$match['id']);
        }
    }

    // A winners-bracket loss in double elimination drops the team down one
    // bracket; every other loss ends the team's tournament. The GF1 loser in
    // a reset final stays alive for GF-M2.
    $dropsDown = $isDouble && $side === 'winners' && !empty($match['loser_next_node_key']);
    if ($loser > 0 && !$dropsDown && !$resetRequired) {
        if (function_exists('markTeamProgression')) markTeamProgression($conn, $loser, 'Eliminated');
        if (function_exists('logProgression')) logProgression($conn, $leagueId, $scheduleId, 'eliminate', $loser, null, 'Eliminated');
        $actions['eliminated'][] = $loser;
    }
    if ($loser > 0 && $dropsDown) {
        $actions['dropped_to_losers'] = $loser;
    }

    syncBracketFeeds($conn, $leagueId, $stageId);
    collapseBracketByes($conn, $leagueId, $stageId);
    syncBracketFeeds($conn, $leagueId, $stageId);

    $final = bracketFinalNode($conn, $leagueId, $stageId);
    if ($final && (int)$final['id'] === $scheduleId
        && strtolower((string)$final['status']) === 'completed'
        && (int)$final['winner'] > 0) {
        $champion = (int)$final['winner'];
        if (function_exists('markTeamProgression')) markTeamProgression($conn, $champion, 'Qualified');
        if (function_exists('logProgression')) logProgression($conn, $leagueId, $scheduleId, 'champion', $champion, null, 'Qualified');
        $actions['champion'] = $champion;
        $actions['tournament_complete'] = true;
    }

    if (function_exists('isStageRoundComplete') && isStageRoundComplete($conn, $leagueId, $stageId, (int)($match['round'] ?? 1))) {
        $actions['stage_round_complete'] = (int)($match['round'] ?? 1);
    }
    if (function_exists('isStageComplete') && isStageComplete($conn, $leagueId, $stageId)) {
        $actions['stage_complete'] = $stageId;
    }
    return $actions;
}

/* =========================================================================
 * 6. Database: Swiss rounds and round locking
 * ====================================================================== */

/** Teams of a stage: its group members, or the approved teams for a first stage. */
function stageTeamPool(mysqli $conn, int $leagueId, int $stageId): array
{
    $teams = [];
    if (function_exists('getStageGroups') && function_exists('getStageGroupTeams')) {
        foreach (getStageGroups($conn, $stageId) as $group) {
            foreach (getStageGroupTeams($conn, (int)$group['id']) as $member) {
                $teamId = (int)($member['team_id'] ?? 0);
                if ($teamId <= 0 || isset($teams[$teamId])) continue;
                $teams[$teamId] = [
                    'team_id'   => $teamId,
                    'team_name' => (string)($member['team_name'] ?? ''),
                    'group_id'  => (int)$group['id'],
                ];
            }
        }
    }

    if (empty($teams) && function_exists('getLeagueStages')) {
        $stages = getLeagueStages($conn, $leagueId);
        if (empty($stages) || (int)($stages[0]['id'] ?? 0) !== $stageId) return [];

        $stmt = $conn->prepare("SELECT id, team_name FROM tbl_team
                                 WHERE league_id = ? AND LOWER(status) = 'approved'
                                   AND progression_status <> 'Eliminated'
                                 ORDER BY team_name");
        if ($stmt) {
            $stmt->bind_param('i', $leagueId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $teams[(int)$row['id']] = ['team_id' => (int)$row['id'], 'team_name' => (string)$row['team_name'], 'group_id' => 0];
            }
            $stmt->close();
        }
    }
    return array_values($teams);
}

/** Completed pairings of a stage, as [['a'=>id,'b'=>id], …]. */
function stagePlayedPairs(mysqli $conn, int $leagueId, int $stageId): array
{
    $pairs = [];
    $stmt = $conn->prepare("SELECT team_a, team_b FROM tbl_schedule
                             WHERE league_id = ? AND stage_id = ?
                               AND LOWER(status) = 'completed' AND team_a > 0 AND team_b > 0
                               AND (is_archived = 0 OR is_archived IS NULL)");
    if (!$stmt) return $pairs;
    $stmt->bind_param('ii', $leagueId, $stageId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pairs[] = ['a' => (int)$row['team_a'], 'b' => (int)$row['team_b']];
    }
    $stmt->close();
    return $pairs;
}

/** Swiss standings rows shaped for swissPairings(): team_id, points, had_bye, played. */
function swissStandingsRows(mysqli $conn, int $leagueId, int $stageId): array
{
    $groups = function_exists('getStageGroups') ? getStageGroups($conn, $stageId) : [];
    $rules  = function_exists('getLeagueScoringRules') ? getLeagueScoringRules($conn, $leagueId) : [];

    $rows = [];
    if (count($groups) > 1 && function_exists('getMultiGroupStandings')) {
        $rows = getMultiGroupStandings($conn, $leagueId, array_map(static fn($g) => (int)$g['id'], $groups), $rules);
    } elseif (!empty($groups) && function_exists('getStageGroupStandings')) {
        $rows = getStageGroupStandings($conn, $leagueId, (int)$groups[0]['id'], $rules);
    }
    if (empty($rows) && function_exists('getLeagueStandings')) {
        $rows = getLeagueStandings($conn, $leagueId, ['rules' => $rules]);
    }

    // Pairings already used + byes already handed out.
    $played = [];
    foreach (stagePlayedPairs($conn, $leagueId, $stageId) as $pair) {
        $played[$pair['a']][] = $pair['b'];
        $played[$pair['b']][] = $pair['a'];
    }
    $byes = [];
    $stmt = $conn->prepare("SELECT CASE WHEN team_a > 0 THEN team_a ELSE team_b END AS team_a
                             FROM tbl_schedule
                             WHERE league_id = ? AND stage_id = ? AND LOWER(status) = 'completed'
                               AND (team_a = 0 OR team_b = 0)
                               AND (is_archived = 0 OR is_archived IS NULL)");
    if ($stmt) {
        $stmt->bind_param('ii', $leagueId, $stageId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $byes[(int)$row['team_a']] = true;
        $stmt->close();
    }

    $normalised = [];
    foreach ($rows as $row) {
        $teamId = (int)($row['id'] ?? $row['team_id'] ?? 0);
        if ($teamId <= 0) continue;
        $normalised[] = [
            'team_id'   => $teamId,
            'team_name' => (string)($row['team_name'] ?? ''),
            'points'    => (int)($row['points'] ?? 0),
            'had_bye'   => !empty($byes[$teamId]),
            'played'    => $played[$teamId] ?? [],
            'rank'      => (int)($row['rank'] ?? 0),
        ];
    }
    return $normalised;
}

/** Lock every row of one stage round so it can no longer be regenerated. */
function lockStageRound(mysqli $conn, int $leagueId, int $stageId, int $round): int
{
    if ($round <= 0) return 0;
    $stmt = $conn->prepare("UPDATE tbl_schedule SET locked = 1, locked_at = NOW()
                             WHERE league_id = ? AND stage_id = ? AND round = ? AND locked = 0
                               AND (is_archived = 0 OR is_archived IS NULL)");
    if (!$stmt) return 0;
    $stmt->bind_param('iii', $leagueId, $stageId, $round);
    $stmt->execute();
    $changed = max(0, (int)$stmt->affected_rows);
    $stmt->close();
    return $changed;
}

/** Every playable row of a stage round is completed. */
function stageRoundComplete(mysqli $conn, int $leagueId, int $stageId, int $round): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total,
                                   COUNT(CASE WHEN LOWER(status) = 'completed' THEN 1 END) AS completed
                              FROM tbl_schedule
                             WHERE league_id = ? AND stage_id = ? AND round = ?
                               AND team_a > 0 AND team_b > 0
                               AND (is_archived = 0 OR is_archived IS NULL)");
    if (!$stmt) return false;
    $stmt->bind_param('iii', $leagueId, $stageId, $round);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (int)$row['total'] > 0 && (int)$row['total'] === (int)$row['completed'];
}

/** Insert one Swiss fixture (a bye is completed immediately). */
function insertSwissMatch(mysqli $conn, int $leagueId, int $stageId, ?int $stageGroupId, int $round, int $teamA, int $teamB, bool $bye = false): int
{
    $status      = $bye ? 'Completed' : 'Draft';
    $winner      = $bye ? $teamA : 0;
    $publishedAt = $bye ? date('Y-m-d H:i:s') : null;
    $sql = "INSERT INTO tbl_schedule
              (league_id, team_a, team_b, round, status, winner, is_automated, stage, stage_id, stage_group_id,
               swiss_round, published_at, result_note)
            VALUES (?, ?, ?, ?, ?, ?, 1, 'group', ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $note = $bye ? 'Bye — no opponent this round' : null;
    $stmt->bind_param('iiiisiiiiss', $leagueId, $teamA, $teamB, $round, $status, $winner, $stageId, $stageGroupId, $round, $publishedAt, $note);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Generate one Swiss round.
 *
 * Round 1 pairs the seeded list top half v bottom half. Every later round
 * groups the teams by match points, pairs equal records, floats one team down
 * from an odd group and gives the bye to the lowest team that has not had one.
 * The previous round is locked in the same call.
 *
 * @return array{errors:string[],created:int,round:int,pairs:array,bye:?int}
 */
function generateSwissRoundForStage(mysqli $conn, int $leagueId, int $stageId, ?int $roundNo = null): array
{
    $result = ['errors' => [], 'created' => 0, 'round' => 0, 'pairs' => [], 'bye' => null];

    if (!ensureTournamentEngineSchema($conn)) {
        $result['errors'][] = 'The tournament tables are not ready. Run database/migrations/041_tournament_structures.sql.';
        return $result;
    }

    $stage = function_exists('getStage') ? getStage($conn, $stageId) : null;
    if (!$stage || (int)$stage['league_id'] !== $leagueId) {
        $result['errors'][] = 'That stage does not belong to this league.';
        return $result;
    }
    if (stageFormat($stage) !== 'swiss') {
        $result['errors'][] = 'Only a Swiss stage can generate Swiss rounds.';
        return $result;
    }

    $teams = stageTeamPool($conn, $leagueId, $stageId);
    if (count($teams) < 2) {
        $result['errors'][] = 'A Swiss stage needs at least two teams.';
        return $result;
    }

    $lastRound = 0;
    $stmt = $conn->prepare("SELECT COALESCE(MAX(round),0) AS last_round FROM tbl_schedule
                             WHERE league_id = ? AND stage_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
    if ($stmt) {
        $stmt->bind_param('ii', $leagueId, $stageId);
        $stmt->execute();
        $lastRound = (int)($stmt->get_result()->fetch_assoc()['last_round'] ?? 0);
        $stmt->close();
    }

    $planned = (int)($stage['rounds_planned'] ?? 0);
    if ($planned <= 0) $planned = swissRoundCount(count($teams));
    if ($roundNo === null) $roundNo = $lastRound + 1;
    $result['round'] = $roundNo;

    if ($roundNo < 1) {
        $result['errors'][] = 'Choose a valid round number.';
        return $result;
    }
    if ($roundNo <= $lastRound) {
        $result['errors'][] = 'Round ' . $roundNo . ' already exists.';
        return $result;
    }
    if ($roundNo > $planned) {
        $result['errors'][] = 'This stage is planned for ' . $planned . ' Swiss rounds.';
        return $result;
    }
    if ($lastRound > 0 && !stageRoundComplete($conn, $leagueId, $stageId, $lastRound)) {
        $result['errors'][] = 'Finish every match of round ' . $lastRound . ' before generating the next round.';
        return $result;
    }
    if ($lastRound > 0) lockStageRound($conn, $leagueId, $stageId, $lastRound);

    if ($roundNo === 1) {
        $first = swissFirstRound(array_map(static fn($team) => (int)$team['team_id'], $teams));
        $pairs = $first['pairs'];
        $bye   = $first['bye'];
    } else {
        $standings = swissStandingsRows($conn, $leagueId, $stageId);
        if (count($standings) < 2) {
            $result['errors'][] = 'The standings are empty — assign the Swiss teams first.';
            return $result;
        }
        $pairing = swissPairings($standings);
        $pairs   = $pairing['pairs'];
        $bye     = $pairing['bye'];
    }

    if (empty($pairs) && empty($bye)) {
        $result['errors'][] = 'No pairings could be produced for round ' . $roundNo . '.';
        return $result;
    }

    $stageGroupId = null;
    if (function_exists('getStageGroups')) {
        $groups = getStageGroups($conn, $stageId);
        if (!empty($groups)) $stageGroupId = (int)$groups[0]['id'];
    }

    $created = 0;
    foreach ($pairs as $pair) {
        if ((int)$pair['b'] <= 0) { $bye = $bye ?: (int)$pair['a']; continue; }
        if (insertSwissMatch($conn, $leagueId, $stageId, $stageGroupId, $roundNo, (int)$pair['a'], (int)$pair['b']) > 0) $created++;
    }
    if (!empty($bye)) {
        if (insertSwissMatch($conn, $leagueId, $stageId, $stageGroupId, $roundNo, (int)$bye, 0, true) > 0) $created++;
    }

    $stmt = $conn->prepare("UPDATE tbl_league_stage SET rounds_planned = ?, stage_status = 'Active',
                                 activated_at = COALESCE(activated_at, NOW()) WHERE id = ?");
    if ($stmt) { $stmt->bind_param('ii', $planned, $stageId); $stmt->execute(); $stmt->close(); }

    $result['created'] = $created;
    $result['pairs']   = $pairs;
    $result['bye']     = $bye !== null ? (int)$bye : null;
    return $result;
}

/* =========================================================================
 * 7. Database: stage transitions, seeding slots and activation
 * ====================================================================== */

/** "1st", "2nd", "3rd" … "11th". */
function stageRankLabel(int $rank): string
{
    if ($rank % 100 >= 11 && $rank % 100 <= 13) return $rank . 'th';
    switch ($rank % 10) {
        case 1: return $rank . 'st';
        case 2: return $rank . 'nd';
        case 3: return $rank . 'rd';
    }
    return $rank . 'th';
}

/** The cut-off rule that feeds a stage, if one is defined. */
function stageTransitionFor(mysqli $conn, int $stageId): ?array
{
    if (!ensureTournamentEngineSchema($conn)) return null;
    $stmt = $conn->prepare("SELECT * FROM tbl_league_stage_transition WHERE to_stage_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Save (or replace) the cut-off rule that feeds one stage. */
function saveStageTransition(mysqli $conn, int $leagueId, int $fromStageId, int $toStageId, string $rule, int $advanceCount): bool
{
    if (!ensureTournamentEngineSchema($conn)) return false;
    if (!in_array($rule, ['top_per_group', 'top_overall', 'bottom_consolation'], true)) return false;
    $advanceCount = max(1, min(64, $advanceCount));

    $stmt = $conn->prepare("INSERT INTO tbl_league_stage_transition
                                (league_id, from_stage_id, to_stage_id, rule, advance_count)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                league_id = VALUES(league_id),
                                from_stage_id = VALUES(from_stage_id),
                                rule = VALUES(rule),
                                advance_count = VALUES(advance_count)");
    if (!$stmt) return false;
    $stmt->bind_param('iiisi', $leagueId, $fromStageId, $toStageId, $rule, $advanceCount);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Slot plan of a transition: one slot per team that can advance.
 *
 * top_per_group : rank 1..N of EVERY source group (Group A · 1st, Group B · 1st …)
 * top_overall   : rank 1..N of the combined table
 *
 * @param array<int,array<string,mixed>> $sourceGroups
 * @param array<int,array<int,array<string,mixed>>> $standingsByGroup
 * @return array<int,array{code:string,label:string,order:int,group_id:int,rank:int,team_id:int,team_name:string}>
 */
function stageSlotPlan(array $sourceGroups, array $standingsByGroup, string $rule = 'top_per_group', int $advanceCount = 2): array
{
    $advanceCount = max(1, min(64, $advanceCount));
    $slots = [];
    $order = 0;

    if ($rule === 'top_overall' || $rule === 'bottom_consolation') {
        $combined = [];
        foreach ($standingsByGroup as $rows) {
            foreach ($rows as $row) $combined[] = $row;
        }
        usort($combined, static function (array $a, array $b): int {
            $points = (int)($b['points'] ?? 0) <=> (int)($a['points'] ?? 0);
            if ($points !== 0) return $points;
            $difference = (int)($b['difference'] ?? 0) <=> (int)($a['difference'] ?? 0);
            if ($difference !== 0) return $difference;
            return strcmp((string)($a['team_name'] ?? ''), (string)($b['team_name'] ?? ''));
        });

        if ($rule === 'bottom_consolation') {
            // Bottom N of the combined table, best-of-the-bottom seeded first.
            $total = count($combined);
            for ($i = 0; $i < $advanceCount && $i < $total; $i++) {
                $rank = $total - $i;
                $row  = $combined[$rank - 1];
                $slots[] = [
                    'code'      => 'B-R' . ($i + 1),
                    'label'     => 'Bottom · ' . stageRankLabel($rank),
                    'order'     => ++$order,
                    'group_id'  => 0,
                    'rank'      => $rank,
                    'team_id'   => (int)($row['id'] ?? 0),
                    'team_name' => (string)($row['team_name'] ?? ''),
                ];
            }
            return $slots;
        }

        for ($rank = 1; $rank <= $advanceCount; $rank++) {
            $row = $combined[$rank - 1] ?? null;
            if (!$row) break;
            $slots[] = [
                'code'      => 'O-R' . $rank,
                'label'     => 'Overall · ' . stageRankLabel($rank),
                'order'     => ++$order,
                'group_id'  => 0,
                'rank'      => $rank,
                'team_id'   => (int)($row['id'] ?? 0),
                'team_name' => (string)($row['team_name'] ?? ''),
            ];
        }
        return $slots;
    }

    foreach (array_values($sourceGroups) as $index => $group) {
        $rows = array_values($standingsByGroup[$index] ?? []);
        for ($rank = 1; $rank <= $advanceCount; $rank++) {
            $row = $rows[$rank - 1] ?? null;
            $slots[] = [
                'code'      => 'G' . ($index + 1) . '-R' . $rank,
                'label'     => (string)($group['name'] ?? ('Group ' . ($index + 1))) . ' · ' . stageRankLabel($rank),
                'order'     => ++$order,
                'group_id'  => (int)($group['id'] ?? 0),
                'rank'      => $rank,
                'team_id'   => $row ? (int)($row['id'] ?? 0) : 0,
                'team_name' => $row ? (string)($row['team_name'] ?? '') : '',
            ];
        }
    }
    return $slots;
}

/**
 * Slot plan for a stage, using its transition rule and the source standings.
 * Without a saved rule the previous stage feeds it with "top_per_group" ×
 * qualifiers_per_group (default 2).
 *
 * @return array<int,array<string,mixed>>
 */
function stageSlotPlanFor(mysqli $conn, int $leagueId, int $targetStageId): array
{
    if (!function_exists('getLeagueStages')) return [];

    $stages = getLeagueStages($conn, $leagueId);
    $previousStageId = 0;
    foreach ($stages as $index => $stage) {
        if ((int)$stage['id'] === $targetStageId) {
            $previousStageId = (int)($stages[$index - 1]['id'] ?? 0);
            break;
        }
    }
    if ($previousStageId <= 0) return [];

    $transition = stageTransitionFor($conn, $targetStageId);
    $rule       = (string)($transition['rule'] ?? 'top_per_group');
    $advance    = $transition ? (int)$transition['advance_count'] : 2;

    $rules     = function_exists('getLeagueScoringRules') ? getLeagueScoringRules($conn, $leagueId) : [];
    $groups    = getStageGroups($conn, $previousStageId);
    $standings = [];
    foreach (array_values($groups) as $index => $group) {
        $standings[$index] = getStageGroupStandings($conn, $leagueId, (int)$group['id'], $rules);
    }
    if (empty($groups) && function_exists('getLeagueStandings')) {
        $groups    = [['id' => 0, 'name' => 'League table']];
        $standings = [getLeagueStandings($conn, $leagueId, ['rules' => $rules])];
    }
    return stageSlotPlan($groups, $standings, $rule, $advance);
}

/**
 * Persist the seeding board of a stage and mirror it into the stage group.
 *
 * @param array<string,int> $slotTeams slot code => team id (0 = leave empty)
 * @return int number of slots written
 */
function saveStageSlots(mysqli $conn, int $leagueId, int $targetStageId, array $slotTeams): int
{
    if (!ensureTournamentEngineSchema($conn)) return 0;

    $stage = getStage($conn, $targetStageId);
    if (!$stage || (int)$stage['league_id'] !== $leagueId) return 0;

    $plan = stageSlotPlanFor($conn, $leagueId, $targetStageId);
    if (empty($plan)) return 0;

    $stages = getLeagueStages($conn, $leagueId);
    $previousStageId = 0;
    foreach ($stages as $index => $row) {
        if ((int)$row['id'] === $targetStageId) {
            $previousStageId = (int)($stages[$index - 1]['id'] ?? 0);
            break;
        }
    }

    $saved = 0;
    $orderedTeams = [];
    foreach ($plan as $slot) {
        $code   = (string)$slot['code'];
        $teamId = (int)($slotTeams[$code] ?? $slot['team_id'] ?? 0);
        if ($teamId > 0 && isset($orderedTeams[$teamId])) $teamId = 0;

        $stmt = $conn->prepare("INSERT INTO tbl_league_stage_slot
                                    (stage_id, slot_code, label, slot_order, source_stage_id, source_group_id, source_rank, source_kind, team_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'group_rank', ?)
                                ON DUPLICATE KEY UPDATE
                                    label = VALUES(label),
                                    slot_order = VALUES(slot_order),
                                    source_stage_id = VALUES(source_stage_id),
                                    source_group_id = VALUES(source_group_id),
                                    source_rank = VALUES(source_rank),
                                    team_id = VALUES(team_id)");
        if (!$stmt) continue;
        $order       = (int)$slot['order'];
        $sourceGroup = (int)$slot['group_id'];
        $sourceRank  = (int)$slot['rank'];
        $bindTeam    = $teamId > 0 ? $teamId : null;
        $stmt->bind_param('issiiiii', $targetStageId, $code, $slot['label'], $order, $previousStageId, $sourceGroup, $sourceRank, $bindTeam);
        $stmt->execute();
        $stmt->close();
        $saved++;
        if ($teamId > 0) $orderedTeams[$teamId] = $order;
    }

    // Mirror into the target stage's first group (if it has one) so standings,
    // generation patterns and the public pages see the same teams.
    $groups = getStageGroups($conn, $targetStageId);
    if (!empty($groups)) {
        $groupId = (int)$groups[0]['id'];
        $conn->query('DELETE FROM tbl_league_stage_group_team WHERE stage_group_id = ' . $groupId);
        $seed = 0;
        foreach ($orderedTeams as $teamId => $order) {
            $seed++;
            $stmt = $conn->prepare("INSERT INTO tbl_league_stage_group_team (stage_group_id, team_id, seed) VALUES (?, ?, ?)");
            if (!$stmt) continue;
            $stmt->bind_param('iii', $groupId, $teamId, $seed);
            $stmt->execute();
            $stmt->close();
        }
    }
    return $saved;
}

/** Ordered team ids of a stage: saved slots first, then its group, then approved teams. */
function stageSeededTeamIds(mysqli $conn, int $leagueId, int $stageId): array
{
    $teams = [];
    if (ensureTournamentEngineSchema($conn)) {
        $stmt = $conn->prepare("SELECT team_id FROM tbl_league_stage_slot
                                 WHERE stage_id = ? AND team_id IS NOT NULL AND team_id > 0
                                 ORDER BY slot_order, id");
        if ($stmt) {
            $stmt->bind_param('i', $stageId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $teams[] = (int)$row['team_id'];
            $stmt->close();
        }
    }
    if ($teams) return $teams;

    foreach (stageTeamPool($conn, $leagueId, $stageId) as $team) $teams[] = (int)$team['team_id'];
    return $teams;
}

/** Stored seeding board of a stage, indexed by slot code. */
function stageSlots(mysqli $conn, int $stageId): array
{
    $slots = [];
    if (!ensureTournamentEngineSchema($conn)) return $slots;
    $stmt = $conn->prepare("SELECT s.*, t.team_name
                              FROM tbl_league_stage_slot s
                              LEFT JOIN tbl_team t ON t.id = s.team_id
                             WHERE s.stage_id = ?
                             ORDER BY s.slot_order, s.id");
    if (!$stmt) return $slots;
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $slots[(string)$row['slot_code']] = $row;
    $stmt->close();
    return $slots;
}

/** Activate a stage and lock the one before it. */
function activateLeagueStage(mysqli $conn, int $leagueId, int $stageId): bool
{
    if (!function_exists('getLeagueStages')) return false;
    $stages = getLeagueStages($conn, $leagueId);
    $previousId = 0;
    foreach ($stages as $index => $stage) {
        if ((int)$stage['id'] === $stageId) { $previousId = (int)($stages[$index - 1]['id'] ?? 0); break; }
    }

    $stmt = $conn->prepare("UPDATE tbl_league_stage
                               SET stage_status = 'Active', activated_at = NOW()
                             WHERE id = ? AND league_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $stageId, $leagueId);
    $stmt->execute();
    $stmt->close();

    if ($previousId > 0) {
        $stmt = $conn->prepare("UPDATE tbl_league_stage
                                   SET stage_status = 'Locked', locked_at = NOW()
                                 WHERE id = ? AND league_id = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $previousId, $leagueId);
            $stmt->execute();
            $stmt->close();
        }
    }
    return true;
}

















