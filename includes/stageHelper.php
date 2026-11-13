<?php
/**
 * Stage helper — the ANP2026 tournament format
 * -------------------------------------------
 * Stage 1 "Elimination (Brackets)" : round robin inside Bracket A–D
 * Stage 2 "Crossover / Pools"      : crossover pairs + pool standings (top 4 per pool)
 * Stage 3 "Playoffs"               : QF (1v4, 2v3 per pool) -> SF -> Final
 * Stage 4 "Battle for Third"       : placement match fed by the semifinal losers
 *
 * A team belongs to a group PER STAGE (tbl_league_stage_group_team has a composite
 * primary key), which is what makes brackets -> pools possible. Matches carry
 * stage_id / stage_group_id / seed_a / seed_b (see the ANP2026 migration).
 *
 * Requires: config/scoringRules.php, standingsProvider.php
 */

require_once __DIR__ . '/../config/scoringRules.php';
require_once __DIR__ . '/standingsProvider.php';
require_once __DIR__ . '/tournamentEngine.php';

/** Stage types and their human labels. */
function stageTypeLabels(): array {
    return [
        'group'     => 'Group / Bracket stage',
        'crossover' => 'Crossover / Pool stage',
        'knockout'  => 'Knockout stage',
        'placement' => 'Placement match',
    ];
}

/* -------------------------------------------------------------------------
 * Stages
 * ---------------------------------------------------------------------- */

function getLeagueStages(mysqli $conn, int $leagueId): array {
    $stages = [];
    $stmt = $conn->prepare("SELECT * FROM tbl_league_stage WHERE league_id = ? AND is_active = 1 ORDER BY display_order, id");
    if (!$stmt) return $stages;
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $stages[] = $row;
    $stmt->close();
    return $stages;
}

function getStage(mysqli $conn, int $stageId): ?array {
    $stmt = $conn->prepare("SELECT * FROM tbl_league_stage WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $stage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $stage ?: null;
}

/** @param string $type one of group|crossover|knockout|placement */
function createStage(mysqli $conn, int $leagueId, string $name, string $type = 'group', ?int $order = null, ?int $qualifiersPerGroup = null, bool $carriesPrevious = false, ?string $format = null): int {
    if (!array_key_exists($type, stageTypeLabels())) $type = 'group';
    if ($order === null) {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(display_order),0) + 1 AS next_order FROM tbl_league_stage WHERE league_id = ?");
        $stmt->bind_param('i', $leagueId); $stmt->execute();
        $order = (int)($stmt->get_result()->fetch_assoc()['next_order'] ?? 1);
        $stmt->close();
    }
    $carry = $carriesPrevious ? 1 : 0;
    $qpg   = ($qualifiersPerGroup !== null && $qualifiersPerGroup > 0) ? $qualifiersPerGroup : null;
    $format = $format !== null && array_key_exists($format, tournamentFormats())
        ? $format
        : stageFormatForType($type);

    /* stage_format / stage_status arrive with migration 041. Older deployments
       fall back to the plain insert so creating a stage never fails. */
    $stmt = $conn->prepare("INSERT INTO tbl_league_stage
                                (league_id,name,stage_type,stage_format,stage_status,display_order,qualifiers_per_group,carries_previous_results)
                            VALUES (?,?,?,?,'Active',?,?,?)");
    if ($stmt) {
        $stmt->bind_param('isssiii', $leagueId, $name, $type, $format, $order, $qpg, $carry);
    } else {
        $stmt = $conn->prepare("INSERT INTO tbl_league_stage (league_id,name,stage_type,display_order,qualifiers_per_group,carries_previous_results) VALUES (?,?,?,?,?,?)");
        if (!$stmt) return 0;
        $stmt->bind_param('issiii', $leagueId, $name, $type, $order, $qpg, $carry);
    }
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function updateStage(mysqli $conn, int $stageId, string $name, string $type, int $order, ?int $qualifiersPerGroup, bool $carriesPrevious): bool {
    if (!array_key_exists($type, stageTypeLabels())) $type = 'group';
    $carry = $carriesPrevious ? 1 : 0;
    $qpg   = ($qualifiersPerGroup !== null && $qualifiersPerGroup > 0) ? $qualifiersPerGroup : null;
    $stmt = $conn->prepare("UPDATE tbl_league_stage SET name=?, stage_type=?, display_order=?, qualifiers_per_group=?, carries_previous_results=? WHERE id=?");
    $stmt->bind_param('ssiiii', $name, $type, $order, $qpg, $carry, $stageId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** Delete a stage of a league. Its groups, source links and team assignments
 *  cascade via the foreign keys on tbl_league_stage_group. */
function deleteStage(mysqli $conn, int $leagueId, int $stageId): bool {
    $stmt = $conn->prepare("DELETE FROM tbl_league_stage WHERE id = ? AND league_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('ii', $stageId, $leagueId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

/* -------------------------------------------------------------------------
 * Stage groups
 * ---------------------------------------------------------------------- */

function getStageGroups(mysqli $conn, int $stageId): array {
    $groups = [];
    $stmt = $conn->prepare("SELECT * FROM tbl_league_stage_group WHERE stage_id = ? ORDER BY display_order, id");
    if (!$stmt) return $groups;
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $groups[] = $row;
    $stmt->close();
    return $groups;
}

function getStageGroup(mysqli $conn, int $groupId): ?array {
    $stmt = $conn->prepare("SELECT g.*, s.league_id, s.stage_type, s.stage_format, s.stage_status,
                                   s.carries_previous_results, s.name AS stage_name
                            FROM tbl_league_stage_group g JOIN tbl_league_stage s ON s.id = g.stage_id WHERE g.id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $group ?: null;
}

function createStageGroup(mysqli $conn, int $stageId, string $name, ?int $order = null): int {
    if ($order === null) {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(display_order),0) + 1 AS next_order FROM tbl_league_stage_group WHERE stage_id = ?");
        $stmt->bind_param('i', $stageId); $stmt->execute();
        $order = (int)($stmt->get_result()->fetch_assoc()['next_order'] ?? 1);
        $stmt->close();
    }
    $stmt = $conn->prepare("INSERT INTO tbl_league_stage_group (stage_id,name,display_order) VALUES (?,?,?)");
    $stmt->bind_param('isi', $stageId, $name, $order);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function deleteStageGroup(mysqli $conn, int $stageId, int $groupId): bool {
    $stmt = $conn->prepare("DELETE FROM tbl_league_stage_group WHERE id = ? AND stage_id = ?");
    $stmt->bind_param('ii', $groupId, $stageId);
    $ok = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

/**
 * Create a stage and its groups in one step (the Setup wizard's "one stage at a
 * time" flow — the organizer should never have to add groups one by one).
 *
 * @param array<int,string> $groupNames names in display order; may be empty.
 * @return int the new stage id
 */
function createStageWithGroups(mysqli $conn, int $leagueId, string $name, string $type, array $groupNames = [], ?int $qualifiersPerGroup = null, bool $carriesPrevious = false, ?string $format = null): int {
    $stageId = createStage($conn, $leagueId, $name, $type, null, $qualifiersPerGroup, $carriesPrevious, $format);
    $order = 1;
    foreach (array_values($groupNames) as $groupName) {
        $clean = trim((string)$groupName);
        if ($clean === '') continue;
        createStageGroup($conn, $stageId, mb_substr($clean, 0, 80, 'UTF-8'), $order++);
    }
    return $stageId;
}

/** A pool declares which earlier groups feed it (enables carry-over standings). */
function setStageGroupSources(mysqli $conn, int $groupId, array $sourceGroupIds): void {
    $stmt = $conn->prepare("DELETE FROM tbl_league_stage_group_source WHERE stage_group_id = ?");
    $stmt->bind_param('i', $groupId); $stmt->execute(); $stmt->close();

    $stmt = $conn->prepare("INSERT IGNORE INTO tbl_league_stage_group_source (stage_group_id, source_group_id) VALUES (?,?)");
    foreach (array_unique(array_map('intval', $sourceGroupIds)) as $sourceId) {
        if ($sourceId <= 0 || $sourceId === $groupId) continue;
        $stmt->bind_param('ii', $groupId, $sourceId);
        $stmt->execute();
    }
    $stmt->close();
}

function getStageGroupSources(mysqli $conn, int $groupId): array {
    $ids = [];
    $stmt = $conn->prepare("SELECT source_group_id FROM tbl_league_stage_group_source WHERE stage_group_id = ? ORDER BY source_group_id");
    if (!$stmt) return $ids;
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $ids[] = (int)$row['source_group_id'];
    $stmt->close();
    return $ids;
}

/* -------------------------------------------------------------------------
 * Membership (a team belongs to a group PER STAGE)
 * ---------------------------------------------------------------------- */

function getStageGroupTeams(mysqli $conn, int $groupId): array {
    $teams = [];
    $stmt = $conn->prepare("SELECT sgt.team_id, sgt.seed, t.team_name, t.team_image, t.progression_status
                            FROM tbl_league_stage_group_team sgt
                            JOIN tbl_team t ON t.id = sgt.team_id
                            WHERE sgt.stage_group_id = ?
                            ORDER BY sgt.seed IS NULL, sgt.seed, t.team_name");
    if (!$stmt) return $teams;
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $teams[] = $row;
    $stmt->close();
    return $teams;
}

function assignStageTeam(mysqli $conn, int $groupId, int $teamId, ?int $seed = null): void {
    $stmt = $conn->prepare("INSERT INTO tbl_league_stage_group_team (stage_group_id,team_id,seed) VALUES (?,?,?)
                            ON DUPLICATE KEY UPDATE seed=VALUES(seed), assigned_at=NOW()");
    $stmt->bind_param('iii', $groupId, $teamId, $seed);
    $stmt->execute();
    $stmt->close();
}

/**
 * Seed the empty groups of the FIRST stage with every approved team.
 *
 * The structure presets create their stage groups empty. On the first draw this
 * fills them round-robin by team name (A, B, C, D, A, …) so the organizer can
 * generate a schedule immediately and still rearrange the groups by hand. It
 * only runs when every group of the stage is empty and the stage is the first
 * one, so later stages are never seeded with the whole field.
 *
 * @return int number of teams assigned
 */
function seedStageGroupsFromApprovedTeams(mysqli $conn, int $leagueId, int $stageId): int {
    $groups = getStageGroups($conn, $stageId);
    if (empty($groups)) return 0;
    foreach ($groups as $group) {
        if (!empty(getStageGroupTeams($conn, (int)$group['id']))) return 0;
    }

    $stages = getLeagueStages($conn, $leagueId);
    if (empty($stages) || (int)$stages[0]['id'] !== $stageId) return 0;

    $stmt = $conn->prepare("SELECT id FROM tbl_team
                            WHERE league_id = ? AND LOWER(status) = 'approved'
                              AND progression_status <> 'Eliminated'
                            ORDER BY team_name");
    if (!$stmt) return 0;
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $res = $stmt->get_result();

    $teamIds = [];
    while ($row = $res->fetch_assoc()) $teamIds[] = (int)$row['id'];
    $stmt->close();

    $groupCount = count($groups);
    $assigned = 0;
    foreach ($teamIds as $index => $teamId) {
        assignStageTeam($conn, (int)$groups[$index % $groupCount]['id'], $teamId);
        $assigned++;
    }
    return $assigned;
}

function unassignStageTeam(mysqli $conn, int $groupId, int $teamId): void {
    $stmt = $conn->prepare("DELETE FROM tbl_league_stage_group_team WHERE stage_group_id = ? AND team_id = ?");
    $stmt->bind_param('ii', $groupId, $teamId);
    $stmt->execute();
    $stmt->close();
}

function clearStageGroupTeams(mysqli $conn, int $groupId): void {
    $stmt = $conn->prepare("DELETE FROM tbl_league_stage_group_team WHERE stage_group_id = ?");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $stmt->close();
}

/** Letter used for seed labels: index 0 -> A, 1 -> B, ... */
function stageGroupLetter(int $index): string {
    $letter = '';
    $index++;
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26);
    }
    return $letter;
}

/* -------------------------------------------------------------------------
 * Seeded single-elimination bracket (stage pattern "seeded_bracket")
 * ---------------------------------------------------------------------- */

/** Bracket slot order for a seeded bracket of $size slots (1-based seeds). */
function seededBracketOrder(int $size): array {
    if ($size < 2) return [];
    $order = [1, 2];
    for ($roundSize = 4; $roundSize <= $size; $roundSize *= 2) {
        $next = [];
        foreach ($order as $seed) {
            $next[] = $seed;
            $next[] = $roundSize + 1 - $seed;
        }
        $order = $next;
    }
    return $order;
}

/**
 * Round-1 pairings of a seeded bracket.
 *
 * Each pair is two 1-based seed numbers; 0 marks a bye slot (the real seed
 * advances by playing a one-team "match", which the engine treats as completed).
 *
 * @return array<int, array{0:int,1:int}>
 */
function seededBracketPairs(int $teamCount): array {
    if ($teamCount < 2) return [];
    $size = 1;
    while ($size < $teamCount) $size *= 2;

    $order = seededBracketOrder($size);
    $pairs = [];
    for ($i = 0; $i < $size; $i += 2) {
        $a = (int)$order[$i];
        $b = (int)$order[$i + 1];
        $pairs[] = [($a <= $teamCount) ? $a : 0, ($b <= $teamCount) ? $b : 0];
    }
    return $pairs;
}

/**
 * Number of rows in every round. Round 1 includes the bye rows, so the sum of
 * the advancers always feeds the next round exactly.
 *
 * @return int[] e.g. 6 teams -> [4, 2, 1]
 */
function seededBracketRoundSizes(int $teamCount): array {
    if ($teamCount < 2) return [];
    $slots = 1;
    while ($slots < $teamCount) $slots *= 2;

    $sizes = [];
    for ($matches = intdiv($slots, 2); $matches >= 1; $matches = intdiv($matches, 2)) {
        $sizes[] = $matches;
    }
    return $sizes;
}

/* -------------------------------------------------------------------------
 * Stage rounds: creation, completion and filling
 * ---------------------------------------------------------------------- */

/** All rows of one round inside a stage (ordered for bracket filling). */
function getStageRoundMatches(mysqli $conn, int $leagueId, int $stageId, int $round): array {
    $rows = [];
    $stmt = $conn->prepare("SELECT id, team_a, team_b, winner, status, stage_group_id, seed_a, seed_b, round
                            FROM tbl_schedule
                            WHERE league_id = ? AND stage_id = ? AND round = ?
                              AND (is_archived = 0 OR is_archived IS NULL)
                            ORDER BY id ASC");
    if (!$stmt) return $rows;
    $stmt->bind_param('iii', $leagueId, $stageId, $round);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

/**
 * Is a stage round finished? Placeholder rows (no teams yet) are ignored so a
 * bracket can never get stuck behind a match that has not been filled in.
 */
function isStageRoundComplete(mysqli $conn, int $leagueId, int $stageId, int $round): bool {
    $playable = 0; $completed = 0;
    foreach (getStageRoundMatches($conn, $leagueId, $stageId, $round) as $row) {
        if ((int)$row['team_a'] <= 0 || (int)$row['team_b'] <= 0) continue;
        $playable++;
        if (strtolower((string)$row['status']) === 'completed') $completed++;
    }
    return $playable > 0 && $playable === $completed;
}

/** Ordered winners of a stage round (regular, forfeit or walkover wins). */
function getStageRoundWinners(mysqli $conn, int $leagueId, int $stageId, int $round): array {
    $winners = [];
    foreach (getStageRoundMatches($conn, $leagueId, $stageId, $round) as $row) {
        if (strtolower((string)$row['status']) !== 'completed') continue;
        $winner = (int)$row['winner'];
        if ($winner > 0) $winners[] = $winner;
    }
    return $winners;
}

/** Ordered losers of a stage round (used for the Battle for Third). */
function getStageRoundLosers(mysqli $conn, int $leagueId, int $stageId, int $round): array {
    $losers = [];
    foreach (getStageRoundMatches($conn, $leagueId, $stageId, $round) as $row) {
        if (strtolower((string)$row['status']) !== 'completed') continue;
        $winner = (int)$row['winner'];
        if ($winner <= 0) continue;
        $teamA = (int)$row['team_a']; $teamB = (int)$row['team_b'];
        $loser = ($winner === $teamA) ? $teamB : (($winner === $teamB) ? $teamA : 0);
        if ($loser > 0) $losers[] = $loser;
    }
    return $losers;
}

/**
 * Make the legacy date/time columns nullable so a generated bracket match can be
 * saved as an unscheduled Draft. The organizer fills the schedule later.
 *
 * Migration 040 also does this. The runtime check keeps older installations
 * working without a manual migration step; it runs at most once per request.
 */
function ensureStageMatchDraftColumns(mysqli $conn): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $columns = [];
    if ($result = $conn->query("SHOW COLUMNS FROM tbl_schedule WHERE Field IN ('date','time')")) {
        while ($row = $result->fetch_assoc()) {
            $columns[(string)$row['Field']] = strtoupper((string)($row['Null'] ?? '')) === 'YES';
        }
        $result->free();
    }

    $changes = [];
    if (array_key_exists('date', $columns) && !$columns['date']) {
        $changes[] = "MODIFY `date` DATE NULL DEFAULT NULL";
    }
    if (array_key_exists('time', $columns) && !$columns['time']) {
        $changes[] = "MODIFY `time` TIME NULL DEFAULT NULL";
    }
    if ($changes) $conn->query("ALTER TABLE tbl_schedule " . implode(', ', $changes));
}

/**
 * Insert one bracket match (draft state, no date yet — the organizer publishes it).
 * Keeps the legacy bracket/stage columns in sync for the older pages.
 */
function createStageMatch(mysqli $conn, int $leagueId, int $stageId, ?int $stageGroupId, int $round, int $teamA, int $teamB, ?string $seedA, ?string $seedB, string $stageTag, ?int $bestOf = null): int {
    ensureStageMatchDraftColumns($conn);
    $bracket = in_array($stageTag, ['knockout', 'placement'], true) ? 'knockout' : null;
    $sql = "INSERT INTO tbl_schedule (league_id, team_a, team_b, round, status, is_automated, bracket, stage, stage_id, stage_group_id, seed_a, seed_b, best_of, venue, committee_user_id)
            VALUES (?, ?, ?, ?, 'Draft', 1, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('iiiissiissi', $leagueId, $teamA, $teamB, $round, $bracket, $stageTag, $stageId, $stageGroupId, $seedA, $seedB, $bestOf);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

/** Seeded generation patterns offered to the organizer. */
function stagePatternLabels(): array {
    return [
        'crossover_cross'     => 'Crossover — A1 v D2, A2 v D1, B1 v C2, B2 v C1',
        'pool_qf'             => 'Quarterfinals — 1st v 4th and 2nd v 3rd per pool',
        'playoff_bracket'     => 'Full playoffs — QF, semifinals, final and Battle for Third',
        'round_robin_groups'  => 'Round robin inside each pool (single round robin)',
        'seeded_bracket'      => 'Seeded bracket — 1 v N, 2 v N-1 … with byes',
        'semis_final_bronze'  => 'Finals (F1) — semifinals A1vB2 / B1vA2, then championship (BO5) and bronze match',
        'direct_final_bronze' => 'Finals (F2) — pool winners for the title (BO5), runners-up for bronze',
    ];
}

/** First (or newly created) placement stage of a league. */
function ensurePlacementStage(mysqli $conn, int $leagueId): ?array {
    foreach (getLeagueStages($conn, $leagueId) as $stage) {
        if ($stage['stage_type'] === 'placement') return $stage;
    }
    $stageId = createStage($conn, $leagueId, 'Battle for Third', 'placement');
    return getStage($conn, $stageId);
}

/**
 * Build the seeded pairs for a pattern WITHOUT writing anything, so the organizer
 * can confirm the matchups first.
 *
 * @return array{0:array,1:array} [matches, errors]
 */
function previewStageMatches(mysqli $conn, int $leagueId, int $stageId, string $pattern): array {
    $matches = []; $errors = [];
    $stage = getStage($conn, $stageId);
    if (!$stage || (int)$stage['league_id'] !== $leagueId) return [[], ['Stage not found for this league.']];

    $groups = getStageGroups($conn, $stageId);
    $rules  = getLeagueScoringRules($conn, $leagueId);
    $standings = [];
    foreach ($groups as $index => $group) {
        $standings[$index] = getStageGroupStandings($conn, $leagueId, (int)$group['id'], $rules);
    }

    /* Seeded single-elimination bracket: one group of teams, or the approved
       teams when this is the only/first stage (the Single elimination preset). */
    if ($pattern === 'seeded_bracket') {
        if (($stage['stage_type'] ?? '') !== 'knockout') {
            return [[], ['The seeded bracket is only available on a knockout stage.']];
        }
        if (count($groups) > 1) {
            return [[], ['The seeded bracket expects one group or no groups.']];
        }

        $teams = [];
        $groupId = null;
        if (count($groups) === 1) {
            $groupId = (int)$groups[0]['id'];
            foreach (getStageGroupTeams($conn, $groupId) as $member) {
                $teams[] = ['team_id' => (int)$member['team_id'], 'team_name' => $member['team_name']];
            }
        } else {
            $stages = getLeagueStages($conn, $leagueId);
            if (empty($stages) || (int)$stages[0]['id'] !== $stageId) {
                return [[], ['Assign teams to this stage before generating a seeded bracket.']];
            }
            $stmt = $conn->prepare("SELECT id AS team_id, team_name
                                    FROM tbl_team
                                    WHERE league_id = ? AND LOWER(status) = 'approved'
                                      AND progression_status <> 'Eliminated'
                                    ORDER BY team_name");
            if ($stmt) {
                $stmt->bind_param('i', $leagueId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $teams[] = ['team_id' => (int)$row['team_id'], 'team_name' => $row['team_name']];
                }
                $stmt->close();
            }
        }

        $teamCount = count($teams);
        if ($teamCount < 2) return [[], ['The seeded bracket needs at least two teams.']];
        if ($teamCount > 128) return [[], ['The seeded bracket supports up to 128 teams.']];

        $seededRoundSizes = seededBracketRoundSizes($teamCount);
        $seededRoundCount = count($seededRoundSizes);
        $seededRoundName = static function (int $roundIndex) use ($seededRoundCount): string {
            $fromEnd = $seededRoundCount - $roundIndex;
            if ($fromEnd === 1) return 'Final';
            if ($fromEnd === 2) return 'Semifinal';
            if ($fromEnd === 3) return 'Quarterfinal';
            if ($fromEnd === 4) return 'Round of 16';
            return 'Round of ' . (2 ** $fromEnd);
        };

        foreach (seededBracketPairs($teamCount) as $pair) {
            [$a, $b] = $pair;
            $teamA = $a > 0 ? $teams[$a - 1] : null;
            $teamB = $b > 0 ? $teams[$b - 1] : null;

            // A bye is stored as a one-team match that the engine treats as
            // already completed. The present team is normalised to team_a.
            $bye = !$teamA || !$teamB;
            if (!$teamA && $teamB) { $teamA = $teamB; $teamB = null; $a = $b; $b = 0; }

            $matches[] = [
                'stage_group_id' => $bye ? null : $groupId,
                'round'          => 1,
                'team_a_id'      => $teamA ? (int)$teamA['team_id'] : 0,
                'team_b_id'      => $teamB ? (int)$teamB['team_id'] : 0,
                'team_a_name'    => $teamA['team_name'] ?? 'BYE',
                'team_b_name'    => $teamB['team_name'] ?? 'BYE',
                'seed_a'         => $a > 0 ? (string)$a : 'BYE',
                'seed_b'         => $b > 0 ? (string)$b : 'BYE',
                'label'          => $seededRoundName(0),
                'bye'            => $bye,
            ];
        }

        foreach ($seededRoundSizes as $roundIndex => $matchCount) {
            if ($roundIndex === 0) continue;
            for ($i = 0; $i < $matchCount; $i++) {
                $matches[] = [
                    'stage_group_id' => null,
                    'round'          => $roundIndex + 1,
                    'team_a_id'      => 0,
                    'team_b_id'      => 0,
                    'team_a_name'    => 'TBD',
                    'team_b_name'    => 'TBD',
                    'seed_a'         => 'W R' . $roundIndex,
                    'seed_b'         => 'W R' . $roundIndex,
                    'label'          => $seededRoundName($roundIndex),
                ];
            }
        }

        return [$matches, $errors];
    }

    $pairUp = function (int $ga, int $ra, int $gb, int $rb, string $label) use (&$matches, &$errors, $groups, $standings): void {
        $a = $standings[$ga][$ra] ?? null;
        $b = $standings[$gb][$rb] ?? null;
        $seedA = stageGroupLetter($ga) . ($ra + 1);
        $seedB = stageGroupLetter($gb) . ($rb + 1);
        if (!$a || !$b) { $errors[] = "Not enough ranked teams for $label ($seedA v $seedB)."; return; }
        $matches[] = [
            'stage_group_id' => (int)$groups[$ga]['id'],
            'round'          => 1,
            'team_a_id'      => (int)$a['id'],
            'team_b_id'      => (int)$b['id'],
            'team_a_name'    => $a['team_name'],
            'team_b_name'    => $b['team_name'],
            'seed_a'         => $seedA,
            'seed_b'         => $seedB,
            'label'          => $label,
        ];
    };

    if ($pattern === 'crossover_cross') {
        // Two ways to use this pattern:
        //  (a) on a POOL stage whose pools declare sources (Pool 1 <- Bracket A + D,
        //      Pool 2 <- Bracket B + C): reads the bracket standings (A1 v D2, A2 v D1,
        //      B1 v C2, B2 v C1) and tags each match to the pool that owns it, so the
        //      crossover games count in the pool table;
        //  (b) on a stage that itself has four groups — pairs them A/D and B/C directly.
        $poolSources = [];
        $orderedSources = [];
        if (!empty($groups)) {
            foreach ($groups as $group) {
                $poolSources[(int)$group['id']] = getStageGroupSources($conn, (int)$group['id']);
            }
            $poolGroupIds = array_keys(array_filter($poolSources, fn($sources) => !empty($sources)));
            if (!empty($poolGroupIds)) {
                $ph = implode(',', array_fill(0, count($poolGroupIds), '?'));
                $stmt = $conn->prepare("SELECT DISTINCT g.id, g.name, g.display_order
                                        FROM tbl_league_stage_group g
                                        JOIN tbl_league_stage_group_source src ON src.source_group_id = g.id
                                        WHERE src.stage_group_id IN ($ph)
                                        ORDER BY g.display_order, g.id");
                if ($stmt) {
                    $stmt->bind_param(str_repeat('i', count($poolGroupIds)), ...$poolGroupIds);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) $orderedSources[] = $row;
                    $stmt->close();
                }
            }
        }

        if (!empty($orderedSources)) {
            if (count($orderedSources) !== 4) {
                return [[], ['The crossover needs exactly four source brackets (A, B, C and D).']];
            }
            $sourceStandings = [];
            foreach ($orderedSources as $index => $source) {
                $sourceStandings[$index] = getStageGroupStandings($conn, $leagueId, (int)$source['id'], $rules);
            }
            $poolFor = function (int $sourceA, int $sourceB) use ($poolSources): ?int {
                foreach ($poolSources as $poolGroupId => $sources) {
                    if (in_array($sourceA, $sources, true) && in_array($sourceB, $sources, true)) return (int)$poolGroupId;
                }
                return null;
            };

            // bracketAIdx, rankAIdx, bracketBIdx, rankBIdx  ->  A1 v D2, A2 v D1, B1 v C2, B2 v C1
            foreach ([[0, 0, 3, 1], [0, 1, 3, 0], [1, 0, 2, 1], [1, 1, 2, 0]] as [$ba, $ra, $bb, $rb]) {
                $a = $sourceStandings[$ba][$ra] ?? null;
                $b = $sourceStandings[$bb][$rb] ?? null;
                $seedA = stageGroupLetter($ba) . ($ra + 1);
                $seedB = stageGroupLetter($bb) . ($rb + 1);
                if (!$a || !$b) { $errors[] = "Not enough ranked teams for crossover $seedA v $seedB."; continue; }
                $matches[] = [
                    'stage_group_id' => $poolFor((int)$orderedSources[$ba]['id'], (int)$orderedSources[$bb]['id']) ?? (int)$groups[0]['id'],
                    'round'          => 1,
                    'team_a_id'      => (int)$a['id'],
                    'team_b_id'      => (int)$b['id'],
                    'team_a_name'    => $a['team_name'],
                    'team_b_name'    => $b['team_name'],
                    'seed_a'         => $seedA,
                    'seed_b'         => $seedB,
                    'label'          => "Crossover $seedA v $seedB",
                ];
            }
            return [$matches, $errors];
        }

        if (count($groups) < 4) {
            return [[], ['The crossover pattern needs four groups (Bracket A, B, C and D).']];
        }
        $pairUp(0, 0, 3, 1, 'Crossover');   // A1 v D2
        $pairUp(0, 1, 3, 0, 'Crossover');   // A2 v D1
        $pairUp(1, 0, 2, 1, 'Crossover');   // B1 v C2
        $pairUp(1, 1, 2, 0, 'Crossover');   // B2 v C1
        return [$matches, $errors];
    }

    /* Single round robin inside each group (Volleyball: Pool A + Pool B, §6.1) */
    if ($pattern === 'round_robin_groups') {
        foreach ($groups as $index => $group) {
            $teams = getStageGroupTeams($conn, (int)$group['id']);
            if (count($teams) < 2) {
                $errors[] = $group['name'] . ' needs at least two teams for a round robin.';
                continue;
            }
            $teamCount = count($teams);
            for ($i = 0; $i < $teamCount; $i++) {
                for ($j = $i + 1; $j < $teamCount; $j++) {
                    $matches[] = [
                        'stage_group_id' => (int)$group['id'],
                        'round'          => 1,
                        'team_a_id'      => (int)$teams[$i]['team_id'],
                        'team_b_id'      => (int)$teams[$j]['team_id'],
                        'team_a_name'    => $teams[$i]['team_name'],
                        'team_b_name'    => $teams[$j]['team_name'],
                        'seed_a'         => stageGroupLetter($index) . ($i + 1),
                        'seed_b'         => stageGroupLetter($index) . ($j + 1),
                        'label'          => $group['name'] . ' round robin',
                    ];
                }
            }
        }
        return [$matches, $errors];
    }

    /* Finals patterns (Volleyball §6.1) — F1 semifinals, F2 straight final.
       The stage has two groups; when each group declares its source pool the
       seeding is taken from that pool's final table. */
    if ($pattern === 'semis_final_bronze' || $pattern === 'direct_final_bronze') {
        $profile      = getLeagueSportProfile($conn, $leagueId);
        $finalBestOf  = sportProfileSeriesLength($profile, true);   // Volleyball: BO5
        $normalBestOf = sportProfileSeriesLength($profile, false);  // Volleyball: BO3

        $poolRanks = [];
        foreach ($groups as $index => $group) {
            $sources = getStageGroupSources($conn, (int)$group['id']);
            $poolRanks[$index] = !empty($sources)
                ? getStageGroupStandings($conn, $leagueId, (int)$sources[0], $rules)
                : $standings[$index];
        }
        if (count($poolRanks) < 2) {
            return [[], ['The finals pattern needs two pools — create two groups on this stage (pointing at their source pools).']];
        }
        $poolA = $poolRanks[0]; $poolB = $poolRanks[1];
        if (count($poolA) < 2 || count($poolB) < 2) {
            return [[], ['Each pool needs at least two ranked teams (1st and 2nd).']];
        }
        $groupAId = (int)$groups[0]['id'];
        $groupBId = (int)$groups[1]['id'];

        if ($pattern === 'direct_final_bronze') {
            // F2: A1 v B1 for the title, A2 v B2 for bronze.
            $matches[] = [
                'stage_group_id' => null, 'round' => 1,
                'team_a_id' => (int)$poolA[0]['id'], 'team_b_id' => (int)$poolB[0]['id'],
                'team_a_name' => $poolA[0]['team_name'], 'team_b_name' => $poolB[0]['team_name'],
                'seed_a' => 'A1', 'seed_b' => 'B1', 'best_of' => $finalBestOf,
                'label' => 'Championship Game',
            ];
            $matches[] = [
                'stage_group_id' => null, 'round' => 1,
                'team_a_id' => (int)$poolA[1]['id'], 'team_b_id' => (int)$poolB[1]['id'],
                'team_a_name' => $poolA[1]['team_name'], 'team_b_name' => $poolB[1]['team_name'],
                'seed_a' => 'A2', 'seed_b' => 'B2', 'best_of' => $normalBestOf,
                'label' => 'Battle for Third', 'placement' => true,
            ];
            return [$matches, $errors];
        }

        // F1: semifinals (A1 v B2, B1 v A2) -> championship + bronze.
        $matches[] = [
            'stage_group_id' => $groupAId, 'round' => 1,
            'team_a_id' => (int)$poolA[0]['id'], 'team_b_id' => (int)$poolB[1]['id'],
            'team_a_name' => $poolA[0]['team_name'], 'team_b_name' => $poolB[1]['team_name'],
            'seed_a' => 'A1', 'seed_b' => 'B2', 'best_of' => $normalBestOf,
            'label' => 'Semifinal 1',
        ];
        $matches[] = [
            'stage_group_id' => $groupBId, 'round' => 1,
            'team_a_id' => (int)$poolB[0]['id'], 'team_b_id' => (int)$poolA[1]['id'],
            'team_a_name' => $poolB[0]['team_name'], 'team_b_name' => $poolA[1]['team_name'],
            'seed_a' => 'B1', 'seed_b' => 'A2', 'best_of' => $normalBestOf,
            'label' => 'Semifinal 2',
        ];
        $matches[] = [
            'stage_group_id' => null, 'round' => 2,
            'team_a_id' => 0, 'team_b_id' => 0,
            'team_a_name' => 'TBD', 'team_b_name' => 'TBD',
            'seed_a' => 'W SF1', 'seed_b' => 'W SF2', 'best_of' => $finalBestOf,
            'label' => 'Championship Game',
        ];
        $matches[] = [
            'stage_group_id' => null, 'round' => 2,
            'team_a_id' => 0, 'team_b_id' => 0,
            'team_a_name' => 'TBD', 'team_b_name' => 'TBD',
            'seed_a' => 'L SF1', 'seed_b' => 'L SF2', 'best_of' => $normalBestOf,
            'label' => 'Battle for Third', 'placement' => true,
        ];
        return [$matches, $errors];
    }

    // pool_qf and playoff_bracket both start with 1 v 4 and 2 v 3 in every pool
    foreach ($groups as $index => $group) {
        $rows = $standings[$index];
        if (count($rows) < 4) {
            $errors[] = $group['name'] . ' needs at least four ranked teams (1st v 4th, 2nd v 3rd).';
            continue;
        }
        $pairUp($index, 0, $index, 3, $group['name'] . ' QF');
        $pairUp($index, 1, $index, 2, $group['name'] . ' QF');
    }
    if ($pattern === 'pool_qf' || !empty($errors)) return [$matches, $errors];

    // Semifinals: one per pool, fed by that pool's two quarterfinals
    foreach ($groups as $index => $group) {
        $matches[] = [
            'stage_group_id' => (int)$group['id'],
            'round'          => 2,
            'team_a_id'      => 0,
            'team_b_id'      => 0,
            'team_a_name'    => 'TBD',
            'team_b_name'    => 'TBD',
            'seed_a'         => 'W ' . $group['name'] . ' QF1',
            'seed_b'         => 'W ' . $group['name'] . ' QF2',
            'label'          => $group['name'] . ' Semifinal',
        ];
    }
    // Championship game (cross-pool, stage level)
    $matches[] = [
        'stage_group_id' => null,
        'round'          => 3,
        'team_a_id'      => 0, 'team_b_id' => 0,
        'team_a_name'    => 'TBD', 'team_b_name' => 'TBD',
        'seed_a'         => 'W SF1', 'seed_b' => 'W SF2',
        'label'          => 'Championship Game',
    ];
    // Battle for Third (placement stage, fed by the semifinal losers)
    $matches[] = [
        'stage_group_id' => null,
        'round'          => 1,
        'team_a_id'      => 0, 'team_b_id' => 0,
        'team_a_name'    => 'TBD', 'team_b_name' => 'TBD',
        'seed_a'         => 'L SF1', 'seed_b' => 'L SF2',
        'label'          => 'Battle for Third',
        'placement'      => true,
    ];
    return [$matches, $errors];
}


/**
 * Which matches count toward a stage group's table.
 * A pool that declares sources (e.g. Pool 1 <- Bracket A + Bracket D) also counts
 * the matches played in those source groups when the stage carries results over.
 *
 * @return array{0:string,1:array} [filterSql, params]
 */
function stageGroupMatchScope(mysqli $conn, array $group): array {
    $groupId = (int)$group['id'];
    $scopes  = ['s.stage_group_id = ?'];
    $params  = [$groupId];

    if ((int)($group['carries_previous_results'] ?? 0) === 1) {
        $sources = getStageGroupSources($conn, $groupId);
        if ($sources) {
            $scopes[] = 's.stage_group_id IN (' . implode(',', array_fill(0, count($sources), '?')) . ')';
            foreach ($sources as $sourceId) $params[] = $sourceId;
        }
    }
    return ['(' . implode(' OR ', $scopes) . ')', $params];
}

/**
 * Standings for one stage group, ordered with the league's scoring rules.
 *
 * @return array<int,array> rows with played/wins/draws/losses/forfeit counts,
 *                          points_for, points_against, points, difference, quotient, rank
 */
function getStageGroupStandings(mysqli $conn, int $leagueId, int $groupId, ?array $rules = null): array {
    $group = getStageGroup($conn, $groupId);
    if (!$group || (int)$group['league_id'] !== $leagueId) return [];
    if ($rules === null) $rules = getLeagueScoringRules($conn, $leagueId);

    [$scopeSql, $scopeParams] = stageGroupMatchScope($conn, $group);

    // Membership + seeds for this group
    $seeds = [];
    $tStmt = $conn->prepare("SELECT team_id, seed FROM tbl_league_stage_group_team WHERE stage_group_id = ?");
    if (!$tStmt) return [];
    $tStmt->bind_param('i', $groupId);
    $tStmt->execute();
    $tRes = $tStmt->get_result();
    while ($t = $tRes->fetch_assoc()) $seeds[(int)$t['team_id']] = $t['seed'];
    $tStmt->close();
    if (empty($seeds)) return [];

    // One canonical provider: points, ratios and tiebreaks are identical to the
    // organizer / public standings.
    $rows = getLeagueStandings($conn, $leagueId, [
        'rules'        => $rules,
        'scope_sql'    => $scopeSql,
        'scope_params' => $scopeParams,
        'team_ids'     => array_keys($seeds),
    ]);
    foreach ($rows as &$row) $row['seed'] = $seeds[(int)$row['id']] ?? null;
    unset($row);

    // A freshly promoted pool has no matches yet: honour the promotion seeds
    // (top 1, top 2, …) instead of falling back to alphabetical order, so the
    // knockout patterns pair 1 v 4 / 2 v 3 correctly.
    $noMatches = true;
    foreach ($rows as $row) {
        if ((int)($row['played'] ?? 0) > 0) { $noMatches = false; break; }
    }
    if ($noMatches) {
        usort($rows, function (array $a, array $b): int {
            $seedA = $a['seed'] !== null ? (int)$a['seed'] : PHP_INT_MAX;
            $seedB = $b['seed'] !== null ? (int)$b['seed'] : PHP_INT_MAX;
            if ($seedA !== $seedB) return $seedA <=> $seedB;
            return strcasecmp((string)$a['team_name'], (string)$b['team_name']);
        });
        foreach ($rows as $index => &$row) $row['rank'] = $index + 1;
        unset($row);
    }

    return $rows;
}

/**
 * Standings across several stage groups at once (used when a pool is fed by more
 * than one bracket, e.g. Pool 1 <- Bracket A + Bracket D).
 *
 * @param int[] $groupIds
 */
function getMultiGroupStandings(mysqli $conn, int $leagueId, array $groupIds, ?array $rules = null): array {
    $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
    if (empty($groupIds)) return [];
    if ($rules === null) $rules = getLeagueScoringRules($conn, $leagueId);

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $scopeSql     = "s.stage_group_id IN ($placeholders)";

    $teamIds = [];
    $tStmt = $conn->prepare("SELECT team_id FROM tbl_league_stage_group_team WHERE stage_group_id IN ($placeholders)");
    if (!$tStmt) return [];
    $types = str_repeat('i', count($groupIds));
    $tStmt->bind_param($types, ...$groupIds);
    $tStmt->execute();
    $tRes = $tStmt->get_result();
    while ($t = $tRes->fetch_assoc()) $teamIds[] = (int)$t['team_id'];
    $tStmt->close();
    if (empty($teamIds)) return [];

    return getLeagueStandings($conn, $leagueId, [
        'rules'        => $rules,
        'scope_sql'    => $scopeSql,
        'scope_params' => $groupIds,
        'team_ids'     => $teamIds,
    ]);
}

/**
 * Carry qualifiers from a source stage into a target stage and mark the rest eliminated.
 *
 * $targetMapping maps each TARGET group to the SOURCE groups that feed it, e.g.
 * [[0,3],[1,2]] builds two pools: Pool 1 <- Bracket A + Bracket D, Pool 2 <- Bracket B + Bracket C
 * (exactly the ANP2026 crossover). Omit it to get a straight Pool A <- Bracket A mapping.
 *
 * @return array summary rows per target group
 */
function promoteStageQualifiers(mysqli $conn, int $leagueId, int $sourceStageId, int $targetStageId, int $perGroup, bool $eliminateRest = true, ?array $targetMapping = null): array {
    $summary = [];
    $perGroup = max(1, $perGroup);
    $sourceGroups = getStageGroups($conn, $sourceStageId);
    $targetGroups = getStageGroups($conn, $targetStageId);
    $rules = getLeagueScoringRules($conn, $leagueId);

    $mapping = [];
    if (is_array($targetMapping) && !empty($targetMapping)) {
        foreach (array_values($targetMapping) as $targetIndex => $sourceIndexes) {
            $mapping[] = ['target' => $targetIndex, 'sources' => array_map('intval', (array)$sourceIndexes)];
        }
    } else {
        foreach (array_keys($sourceGroups) as $i) $mapping[] = ['target' => $i, 'sources' => [$i]];
    }

    foreach ($mapping as $entry) {
        $sourceIndexes = array_values(array_filter($entry['sources'], fn($i) => isset($sourceGroups[$i])));
        if (empty($sourceIndexes)) continue;
        $sourceGroupIds = array_map(fn($i) => (int)$sourceGroups[$i]['id'], $sourceIndexes);

        // Qualifiers are taken as "top N of EACH source group" (ANP2026: top 2 of every
        // bracket feed a pool of four), never "top N of the combined table".
        $promoted = [];
        $perSourceStandings = [];
        $sourceTotals = 0;
        foreach ($sourceIndexes as $sourceIndex) {
            $rows = getStageGroupStandings($conn, $leagueId, (int)$sourceGroups[$sourceIndex]['id'], $rules);
            $perSourceStandings[$sourceIndex] = $rows;
            $sourceTotals += count($rows);
            foreach (array_slice($rows, 0, $perGroup) as $rank => $row) {
                $promoted[] = ['row' => $row, 'source' => $sourceIndex, 'rank' => $rank + 1];
            }
        }
        usort($promoted, fn($a, $b) => [$a['rank'], $a['source']] <=> [$b['rank'], $b['source']]);

        $targetIndex = (int)$entry['target'];
        $target = $targetGroups[$targetIndex] ?? null;
        if (!$target) {
            $newId = createStageGroup($conn, $targetStageId, stageGroupLetter($targetIndex) . ' Pool', $targetIndex + 1);
            $target = getStageGroup($conn, $newId);
            if ($target) $targetGroups[$targetIndex] = $target;
        }
        if (!$target) continue;

        clearStageGroupTeams($conn, (int)$target['id']);
        $promotedNames = [];
        foreach ($promoted as $position => $item) {
            assignStageTeam($conn, (int)$target['id'], (int)$item['row']['id'], $position + 1);
            $promotedNames[] = $item['row']['team_name'];
        }

        if ($eliminateRest) {
            foreach ($perSourceStandings as $sourceIndex => $rows) {
                foreach (array_slice($rows, $perGroup) as $row) {
                    $stmt = $conn->prepare("UPDATE tbl_team SET progression_status='Eliminated', progression_decided_at=NOW() WHERE id = ?");
                    $stmt->bind_param('i', $row['id']); $stmt->execute(); $stmt->close();
                }
            }
        }
        setStageGroupSources($conn, (int)$target['id'], $sourceGroupIds);

        $summary[] = [
            'source'   => implode(' + ', array_map(fn($i) => $sourceGroups[$i]['name'], $sourceIndexes)),
            'target'   => $target['name'],
            'promoted' => $promotedNames,
            'cut'      => max(0, $sourceTotals - count($promoted)),
        ];
    }
    return $summary;
}

/**
 * Next active stage after $stageId (by display order).
 */
function nextLeagueStage(mysqli $conn, int $leagueId, int $stageId): ?array {
    $current = getStage($conn, $stageId);
    if (!$current) return null;

    $stmt = $conn->prepare("SELECT * FROM tbl_league_stage
                            WHERE league_id = ? AND is_active = 1 AND display_order > ?
                            ORDER BY display_order, id LIMIT 1");
    if (!$stmt) return null;
    $order = (int)$current['display_order'];
    $stmt->bind_param('ii', $leagueId, $order);
    $stmt->execute();
    $stage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $stage ?: null;
}

/**
 * Auto-promote the qualifiers of a completed group/crossover stage.
 *
 * Driven by the stage's `qualifiers_per_group` (set by the structure presets or
 * by the organizer when adding a stage). It runs only once: if the next stage
 * already has teams, the organizer has taken over and nothing is changed.
 *
 * @return array|null promotion summary, or null when auto-promotion did not run
 */
function autoPromoteStageQualifiers(mysqli $conn, int $leagueId, int $sourceStageId): ?array {
    $source = getStage($conn, $sourceStageId);
    if (!$source || (int)$source['league_id'] !== $leagueId) return null;
    if (!in_array($source['stage_type'], ['group', 'crossover'], true)) return null;

    $perGroup = (int)($source['qualifiers_per_group'] ?? 0);
    if ($perGroup < 1) return null;

    $target = nextLeagueStage($conn, $leagueId, $sourceStageId);
    if (!$target) return null;

    // Manual assignments in the target stage always win.
    $targetGroups = getStageGroups($conn, (int)$target['id']);
    foreach ($targetGroups as $targetGroup) {
        if (!empty(getStageGroupTeams($conn, (int)$targetGroup['id']))) return null;
    }

    // Pools can declare their sources (Pool 1 <- Bracket A + D). Translate the
    // source ids into source-group indexes for promoteStageQualifiers().
    $sourceIndexes = [];
    foreach (getStageGroups($conn, $sourceStageId) as $index => $sourceGroup) {
        $sourceIndexes[(int)$sourceGroup['id']] = $index;
    }

    $mapping = null;
    if (!empty($targetGroups)) {
        $candidate = [];
        $hasSources = false;
        foreach (array_keys($targetGroups) as $targetIndex => $targetGroupIndex) {
            $declared = getStageGroupSources($conn, (int)$targetGroups[$targetGroupIndex]['id']);
            if (empty($declared)) {
                $candidate[] = [$targetIndex];
                continue;
            }
            $hasSources = true;
            $indexes = [];
            foreach ($declared as $sourceId) {
                if (isset($sourceIndexes[(int)$sourceId])) $indexes[] = $sourceIndexes[(int)$sourceId];
            }
            $candidate[] = $indexes ?: [$targetIndex];
        }
        if ($hasSources) $mapping = $candidate;
    }

    $summary = promoteStageQualifiers(
        $conn,
        $leagueId,
        $sourceStageId,
        (int)$target['id'],
        $perGroup,
        true,
        $mapping
    );

    if ($summary && function_exists('auditLog')) {
        $detail = [];
        foreach ($summary as $row) {
            $detail[] = $row['target'] . ': ' . count($row['promoted']) . ' promoted';
        }
        auditLog($conn, 'Auto-promote Stage Qualifiers',
            "Stage ID $sourceStageId → {$target['name']} (ID {$target['id']}): " . implode('; ', $detail));
    }

    return $summary ?: null;
}

/* -------------------------------------------------------------------------
 * Writing generated matches
 * ---------------------------------------------------------------------- */

/**
 * Write the matches produced by previewStageMatches() as drafts.
 *
 * @return array{errors:array,created:int,ids:array,matches:array}
 */
function generateStageMatches(mysqli $conn, int $leagueId, int $stageId, string $pattern): array {
    // Never append a second draw to a stage that already has fixtures: pressing
    // Generate twice used to duplicate every round and leave the earlier
    // placeholders behind. Archived rows do not count, so a stage can still be
    // re-drawn after its old fixtures are archived.
    $existingStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule
                                    WHERE league_id = ? AND stage_id = ?
                                      AND (is_archived = 0 OR is_archived IS NULL)");
    if ($existingStmt) {
        $existingStmt->bind_param('ii', $leagueId, $stageId);
        $existingStmt->execute();
        $existingCount = (int)($existingStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $existingStmt->close();
        if ($existingCount > 0) {
            return ['errors' => ['This stage already has matches. Archive or delete them before generating again.'], 'created' => 0, 'ids' => [], 'matches' => []];
        }
    }

    [$matches, $errors] = previewStageMatches($conn, $leagueId, $stageId, $pattern);
    if (!empty($errors)) return ['errors' => $errors, 'created' => 0, 'ids' => [], 'matches' => $matches];

    $stage = getStage($conn, $stageId);
    $tag   = in_array($stage['stage_type'] ?? 'group', ['knockout', 'placement'], true) ? $stage['stage_type'] : 'group';

    $ids = [];
    foreach ($matches as $match) {
        $targetStageId = $stageId;
        $groupForMatch = $match['stage_group_id'] ?? null;
        $round         = (int)$match['round'];
        $matchTag      = $tag;

        if (!empty($match['placement'])) {
            $placement = ensurePlacementStage($conn, $leagueId);
            if (!$placement) continue;
            $targetStageId = (int)$placement['id'];
            $groupForMatch = null;
            $round         = 1;
            $matchTag      = 'placement';
        }
        $id = createStageMatch($conn, $leagueId, $targetStageId, $groupForMatch, $round,
                               (int)$match['team_a_id'], (int)$match['team_b_id'],
                               $match['seed_a'], $match['seed_b'], $matchTag,
                               isset($match['best_of']) ? (int)$match['best_of'] : null);

        // A bye is a completed one-team row: the present team advances and the
        // row never blocks the round (isStageRoundComplete ignores team_b = 0).
        if ($id > 0 && !empty($match['bye'])) {
            $byeWinner = (int)$match['team_a_id'] ?: (int)$match['team_b_id'];
            $byeStmt = $conn->prepare("UPDATE tbl_schedule
                                       SET status = 'Completed', winner = ?, a_score = 0, b_score = 0,
                                           published_at = NOW(), stage_group_id = NULL
                                       WHERE id = ?");
            if ($byeStmt) {
                $byeStmt->bind_param('ii', $byeWinner, $id);
                $byeStmt->execute();
                $byeStmt->close();
            }
        }

        if ($id > 0) $ids[] = $id;
    }

    // Byes are already completed rows; put them (and any completed fixtures)
    // straight into their next-round slots so the draw is accurate immediately.
    if ($tag === 'knockout' && stageFormat($stage) !== 'double_elimination') {
        syncLegacyBracketStage($conn, $leagueId, $stageId);
    }

    return ['errors' => [], 'created' => count($ids), 'ids' => $ids, 'matches' => $matches];
}

/** Fill the placeholder rows of a stage round with the winners of the previous round. */
function fillStageRoundFromWinners(mysqli $conn, int $leagueId, int $stageId, int $round, array $winners, int $sourceScheduleId = 0): int {
    $placeholders = [];
    foreach (getStageRoundMatches($conn, $leagueId, $stageId, $round) as $row) {
        if ((int)$row['team_a'] === 0 && (int)$row['team_b'] === 0) $placeholders[] = (int)$row['id'];
    }
    $filled = 0; $index = 0;
    $filledIds = [];
    for ($i = 0; $i + 1 < count($winners) && $index < count($placeholders); $i += 2, $index++) {
        $teamA = (int)$winners[$i]; $teamB = (int)$winners[$i + 1]; $matchId = $placeholders[$index];
        $stmt = $conn->prepare("UPDATE tbl_schedule SET team_a = ?, team_b = ? WHERE id = ?");
        $stmt->bind_param('iii', $teamA, $teamB, $matchId);
        $stmt->execute();
        $stmt->close();
        $filled++;
        $filledIds[] = $matchId;
    }
    // Record which placeholders were filled so a corrected result can empty them again.
    if ($sourceScheduleId > 0 && $filledIds && function_exists('logProgression')) {
        logProgression($conn, $leagueId, $sourceScheduleId, 'fill_matches', null, null, null, $filledIds);
    }
    return $filled;
}

/**
 * Put one legacy knockout winner into its exact next-round slot.
 *
 * Legacy seeded brackets have no node_key graph, but their rows are written in
 * bracket order: match i of round r feeds match floor(i / 2) of round r + 1.
 * Even i fills slot a, odd i fills slot b.
 *
 * @return int 1 when the target row changed, 0 otherwise
 */
function fillNextRoundSlotFromWinner(mysqli $conn, int $leagueId, int $stageId, int $round, int $sourceScheduleId, int $winnerId): int {
    if ($winnerId <= 0 || $sourceScheduleId <= 0) return 0;

    $sourceIndex = null;
    foreach (getStageRoundMatches($conn, $leagueId, $stageId, $round) as $index => $row) {
        if ((int)$row['id'] === $sourceScheduleId) {
            $sourceIndex = $index;
            break;
        }
    }
    if ($sourceIndex === null) return 0;

    $nextRows = getStageRoundMatches($conn, $leagueId, $stageId, $round + 1);
    $targetIndex = intdiv($sourceIndex, 2);
    if (!isset($nextRows[$targetIndex])) return 0;

    $target = $nextRows[$targetIndex];
    // Never rewrite a next-round match that has already been played.
    if (strtolower((string)$target['status']) === 'completed') return 0;

    $slot = ($sourceIndex % 2 === 0) ? 'team_a' : 'team_b';
    if ((int)$target[$slot] === $winnerId) return 0;

    $matchId = (int)$target['id'];
    $stmt = $conn->prepare("UPDATE tbl_schedule SET $slot = ? WHERE id = ? AND league_id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param('iii', $winnerId, $matchId, $leagueId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0 ? 1 : 0;
    $stmt->close();

    if ($changed && function_exists('logProgression')) {
        logProgression($conn, $leagueId, $sourceScheduleId, 'fill_matches', null, null, null, [$matchId]);
    }
    return $changed;
}

/**
 * Fill every available next-round slot from the completed matches of one round.
 *
 * @return int number of next-round rows changed
 */
function syncStageRoundAdvancement(mysqli $conn, int $leagueId, int $stageId, int $round): int {
    $writes = 0;
    foreach (getStageRoundMatches($conn, $leagueId, $stageId, $round) as $row) {
        if (strtolower((string)$row['status']) !== 'completed') continue;
        $winnerId = (int)$row['winner'];
        if ($winnerId <= 0) continue;
        $writes += fillNextRoundSlotFromWinner($conn, $leagueId, $stageId, $round, (int)$row['id'], $winnerId);
    }
    return $writes;
}

/**
 * Re-sync every round of a legacy knockout stage. Used after a bracket draw and
 * after a result, so byes / earlier winners cannot leave next-round slots stale.
 *
 * @return int number of next-round rows changed
 */
function syncLegacyBracketStage(mysqli $conn, int $leagueId, int $stageId): int {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(round), 0) AS max_round
                            FROM tbl_schedule
                            WHERE league_id = ? AND stage_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
    if (!$stmt) return 0;
    $stmt->bind_param('ii', $leagueId, $stageId);
    $stmt->execute();
    $maxRound = (int)($stmt->get_result()->fetch_assoc()['max_round'] ?? 0);
    $stmt->close();

    $writes = 0;
    for ($round = 1; $round < $maxRound; $round++) {
        $writes += syncStageRoundAdvancement($conn, $leagueId, $stageId, $round);
    }
    return $writes;
}

/** Fill the single Battle for Third row with the semifinal losers. */
function fillPlacementFromLosers(mysqli $conn, int $leagueId, int $placementStageId, array $losers): int {
    $loserA = (int)($losers[0] ?? 0);
    $loserB = (int)($losers[1] ?? 0);
    if ($loserA <= 0 || $loserB <= 0) return 0;

    $stmt = $conn->prepare("SELECT id FROM tbl_schedule WHERE league_id = ? AND stage_id = ? AND team_a = 0 AND team_b = 0 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY id ASC LIMIT 1");
    if (!$stmt) return 0;
    $stmt->bind_param('ii', $leagueId, $placementStageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return 0;

    $matchId = (int)$row['id'];
    $stmt = $conn->prepare("UPDATE tbl_schedule SET team_a = ?, team_b = ? WHERE id = ?");
    $stmt->bind_param('iii', $loserA, $loserB, $matchId);
    $stmt->execute();
    $stmt->close();
    return 1;
}

