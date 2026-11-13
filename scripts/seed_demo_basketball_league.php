<?php
/**
 * Demo team seeder — approved teams for a Basketball league
 * =========================================================
 * Creates one APPROVED team per active Team Representative (role 2) in a
 * Basketball league, with a sample eligible roster. By default it deliberately
 * stops there: no stages, groups, fixtures, scheduling configuration or
 * results are written, so the organizer can test Setup, scheduling and
 * progression from a clean slate.
 *
 * With --assign-groups it also fills the groups of an existing round-robin
 * stage and saves a "top N per group" cut-off rule into the next knockout
 * stage. That is the quickest way to prepare a Groups → knockout league for
 * scheduling and progression testing (no fixtures are generated).
 *
 * Usage (from the lms/ directory):
 *   php scripts/seed_demo_basketball_league.php --league=54
 *   php scripts/seed_demo_basketball_league.php --league=54 --players=12
 *   php scripts/seed_demo_basketball_league.php --league=54 --players=0
 *   php scripts/seed_demo_basketball_league.php --league=55 --allow-draft --allow-stages --assign-groups --advance=4
 *   php scripts/seed_demo_basketball_league.php --league=54 --dry-run
 *
 * Options:
 *   --league=ID      League to seed. Default: newest published, non-archived
 *                    Basketball league that has no teams yet.
 *   --players=N      Players per new team (default 12; 0 = teams only). Clamped
 *                    to the league's max_player when that is set.
 *   --allow-stages   Permit a league that already has a stage skeleton. The
 *                    league must still have zero matches.
 *   --allow-draft    Permit a league whose publication_status is Draft.
 *   --assign-groups  Place every approved team into the groups of the first
 *                    round-robin stage (round-robin across the groups) and save
 *                    the top-N cut-off rule for the next knockout stage.
 *                    Implies --allow-stages.
 *   --advance=N      Qualifiers per group for --assign-groups. Default 4.
 *   --dry-run        Print the plan without writing anything.
 *   --help           Show this help.
 *
 * The script is safe to re-run: representatives who already have an active
 * team in the league are skipped, and it refuses to touch a league that
 * already has matches.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/leagueStatus.php';

const SEED_DEFAULT_PLAYERS = 12;

function seedOut(string $line = ''): void
{
    echo $line . PHP_EOL;
}

function seedFail(string $message, int $code = 1): void
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit($code);
}

function seedUsage(): void
{
    seedOut('Usage: php scripts/seed_demo_basketball_league.php [--league=ID] [--players=N] [--dry-run]');
    seedOut('       [--allow-stages] [--allow-draft] [--assign-groups] [--advance=N]');
    seedOut('  --league=ID      League to seed (default: newest published Basketball league without teams)');
    seedOut('  --players=N      Players per new team, default ' . SEED_DEFAULT_PLAYERS . ' (0 = teams only)');
    seedOut('  --allow-stages   Allow a league that already has a stage skeleton (must have no matches)');
    seedOut('  --allow-draft    Allow a Draft (unpublished) league');
    seedOut('  --assign-groups  Fill the round-robin stage groups and save the top-N cut-off rule');
    seedOut('  --advance=N      Qualifiers per group for --assign-groups (default 4)');
    seedOut('  --dry-run        Show the plan without writing');
    seedOut('  --help           Show this help');
}

function seedTableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res  = $conn->query("SHOW TABLES LIKE '$safe'");
    $has  = $res && $res->num_rows > 0;
    if ($res) $res->free();
    return $has;
}
/**
 * Place every approved team into the groups of the first round-robin stage.
 *
 * Teams are ordered by name and dealt round-robin across the groups (the same
 * shape as the Setup wizard's automatic fill). The top-N cut-off rule into the
 * next knockout stage is saved as well, and any stale knockout seeding board is
 * cleared so the organizer starts from the real group standings.
 *
 * Call this only while the league has no matches: it replaces the stage-group
 * membership instead of appending to it.
 *
 * @return array{groups:array<int,array{id:int,name:string,teams:array<int,string>}>,rule:?array{from:string,to:string,advance:int}}
 */
function seedAssignStageGroups(mysqli $conn, int $leagueId, int $advancePerGroup): array
{
    $result = ['groups' => [], 'rule' => null];

    $stages = [];
    $stageStmt = $conn->prepare("SELECT id, name, stage_type, stage_format, display_order
                                 FROM tbl_league_stage
                                 WHERE league_id = ?
                                 ORDER BY display_order, id");
    $stageStmt->bind_param('i', $leagueId);
    $stageStmt->execute();
    $stageRes = $stageStmt->get_result();
    while ($row = $stageRes->fetch_assoc()) $stages[] = $row;
    $stageStmt->close();

    $groupStageIndex = null;
    foreach ($stages as $index => $stage) {
        if (!in_array((string)$stage['stage_format'], ['round_robin', 'swiss'], true)) continue;
        $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_league_stage_group WHERE stage_id = ?");
        $countStmt->bind_param('i', $stage['id']);
        $countStmt->execute();
        $groupCount = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $countStmt->close();
        if ($groupCount > 0) {
            $groupStageIndex = $index;
            break;
        }
    }

    if ($groupStageIndex === null) {
        throw new RuntimeException('--assign-groups needs a round-robin stage with at least one group.');
    }
    $groupStage = $stages[$groupStageIndex];

    $groups = [];
    $groupStmt = $conn->prepare("SELECT id, name, display_order
                                 FROM tbl_league_stage_group
                                 WHERE stage_id = ?
                                 ORDER BY display_order, id");
    $groupStmt->bind_param('i', $groupStage['id']);
    $groupStmt->execute();
    $groupRes = $groupStmt->get_result();
    while ($row = $groupRes->fetch_assoc()) $groups[] = $row;
    $groupStmt->close();
    if (!$groups) {
        throw new RuntimeException('The round-robin stage has no groups to fill.');
    }

    $teams = [];
    $teamStmt = $conn->prepare("SELECT id, team_name
                                FROM tbl_team
                                WHERE league_id = ? AND LOWER(status) = 'approved'
                                ORDER BY team_name, id");
    $teamStmt->bind_param('i', $leagueId);
    $teamStmt->execute();
    $teamRes = $teamStmt->get_result();
    while ($row = $teamRes->fetch_assoc()) $teams[] = $row;
    $teamStmt->close();
    if (!$teams) {
        throw new RuntimeException('No approved teams are available to place in the groups.');
    }

    // Fresh draw: the caller already verified that the league has no matches.
    $deleteStmt = $conn->prepare("DELETE FROM tbl_league_stage_group_team WHERE stage_group_id = ?");
    foreach ($groups as $group) {
        $deleteStmt->bind_param('i', $group['id']);
        $deleteStmt->execute();
    }
    $deleteStmt->close();

    $insertStmt = $conn->prepare("INSERT INTO tbl_league_stage_group_team (stage_group_id, team_id, seed)
                                  VALUES (?, ?, ?)");
    $seeds = [];
    foreach ($groups as $index => $group) {
        $seeds[$index] = 0;
        $result['groups'][$index] = [
            'id'    => (int)$group['id'],
            'name'  => (string)$group['name'],
            'teams' => [],
        ];
    }

    foreach ($teams as $teamIndex => $team) {
        $groupIndex = $teamIndex % count($groups);
        $seed       = ++$seeds[$groupIndex];
        $groupId    = (int)$groups[$groupIndex]['id'];
        $teamId     = (int)$team['id'];
        $insertStmt->bind_param('iii', $groupId, $teamId, $seed);
        $insertStmt->execute();
        $result['groups'][$groupIndex]['teams'][] = (string)$team['team_name'];
    }
    $insertStmt->close();

    // The next knockout stage is fed by the group standings.
    $nextStage = null;
    for ($i = $groupStageIndex + 1; $i < count($stages); $i++) {
        if (in_array((string)$stages[$i]['stage_format'], ['single_elimination', 'double_elimination'], true)) {
            $nextStage = $stages[$i];
            break;
        }
    }

    if ($nextStage && seedTableExists($conn, 'tbl_league_stage_transition')) {
        $ruleStmt = $conn->prepare("INSERT INTO tbl_league_stage_transition
                                        (league_id, from_stage_id, to_stage_id, rule, advance_count)
                                    VALUES (?, ?, ?, 'top_per_group', ?)
                                    ON DUPLICATE KEY UPDATE
                                        from_stage_id = VALUES(from_stage_id),
                                        rule = VALUES(rule),
                                        advance_count = VALUES(advance_count)");
        $fromStageId = (int)$groupStage['id'];
        $toStageId   = (int)$nextStage['id'];
        $ruleStmt->bind_param('iiii', $leagueId, $fromStageId, $toStageId, $advancePerGroup);
        $ruleStmt->execute();
        $ruleStmt->close();

        $qualifierStmt = $conn->prepare("UPDATE tbl_league_stage
                                            SET qualifiers_per_group = ?
                                          WHERE id = ? AND league_id = ?");
        $qualifierStmt->bind_param('iii', $advancePerGroup, $fromStageId, $leagueId);
        $qualifierStmt->execute();
        $qualifierStmt->close();

        // A board seeded before the group results existed would show the wrong
        // qualifiers. Clear it; the Setup promotion step rebuilds it on demand.
        if (seedTableExists($conn, 'tbl_league_stage_slot')) {
            $slotStmt = $conn->prepare("DELETE FROM tbl_league_stage_slot WHERE stage_id = ?");
            $slotStmt->bind_param('i', $toStageId);
            $slotStmt->execute();
            $slotStmt->close();
        }

        $result['rule'] = [
            'from'    => (string)$groupStage['name'],
            'to'      => (string)$nextStage['name'],
            'advance' => $advancePerGroup,
        ];
    }

    return $result;
}



$options = getopt('', [
    'league:',
    'players:',
    'dry-run',
    'allow-stages',
    'allow-draft',
    'assign-groups',
    'advance:',
    'help',
]);
if ($options === false) {
    seedFail('Could not read the command-line options.');
}
if (isset($options['help'])) {
    seedUsage();
    exit(0);
}

$leagueId        = isset($options['league']) ? (int)$options['league'] : 0;
$playersPerTeam  = isset($options['players']) ? (int)$options['players'] : SEED_DEFAULT_PLAYERS;
$dryRun          = isset($options['dry-run']);
$allowDraft      = isset($options['allow-draft']);
$assignGroups    = isset($options['assign-groups']);
$allowStages     = isset($options['allow-stages']) || $assignGroups;
$advancePerGroup = isset($options['advance']) ? (int)$options['advance'] : 4;

if ($advancePerGroup < 1 || $advancePerGroup > 64) {
    seedFail('--advance must be a number between 1 and 64.');
}

/* -------------------------------------------------------------------------
 * 1. Resolve and validate the league
 * ---------------------------------------------------------------------- */

if ($leagueId > 0) {
    $leagueStmt = $conn->prepare("SELECT * FROM tbl_league WHERE id = ? LIMIT 1");
    $leagueStmt->bind_param('i', $leagueId);
} else {
    $leagueStmt = $conn->prepare("SELECT l.*
                                  FROM tbl_league l
                                  WHERE LOWER(l.sport) = 'basketball'
                                    AND l.publication_status = 'Published'
                                    AND (l.is_archived = 0 OR l.is_archived IS NULL)
                                    AND NOT EXISTS (SELECT 1 FROM tbl_team t WHERE t.league_id = l.id)
                                  ORDER BY l.id DESC
                                  LIMIT 1");
}
$leagueStmt->execute();
$league = $leagueStmt->get_result()->fetch_assoc();
$leagueStmt->close();

if (!$league) {
    seedFail($leagueId > 0
        ? "League $leagueId was not found."
        : 'No published Basketball league without teams was found. Pass --league=ID explicitly.');
}

$leagueId = (int)$league['id'];

if (strcasecmp((string)$league['sport'], 'Basketball') !== 0) {
    seedFail('This seeder only fills Basketball leagues.');
}
if ((string)$league['publication_status'] !== 'Published' && !$allowDraft) {
    seedFail('Publish the league before seeding teams (or pass --allow-draft).');
}
if ((int)($league['is_archived'] ?? 0) === 1) {
    seedFail('This league is archived.');
}

$stageCount = 0;
if (seedTableExists($conn, 'tbl_league_stage')) {
    $stageStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_league_stage WHERE league_id = ?");
    $stageStmt->bind_param('i', $leagueId);
    $stageStmt->execute();
    $stageCount = (int)($stageStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stageStmt->close();
}

$matchStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id = ?");
$matchStmt->bind_param('i', $leagueId);
$matchStmt->execute();
$matchCount = (int)($matchStmt->get_result()->fetch_assoc()['c'] ?? 0);
$matchStmt->close();

if ($matchCount > 0) {
    seedFail('League ' . $leagueId . ' already has ' . $matchCount
        . ' match(es). This seeder only fills a league before its first match so it cannot disturb a tournament in progress.');
}
if ($stageCount > 0 && !$allowStages) {
    seedFail('League ' . $leagueId . ' already has ' . $stageCount
        . ' stage(s). Pass --allow-stages (or --assign-groups) to seed teams into an existing structure.');
}
if ($assignGroups && $stageCount === 0) {
    seedFail('--assign-groups needs an existing round-robin stage; this league has no stages.');
}

/* -------------------------------------------------------------------------
 * 2. Representatives, existing teams and the seeding plan
 * ---------------------------------------------------------------------- */

$ownerId = (int)($league['created_by'] ?? 0);
if ($ownerId <= 0) {
    seedFail('The league has no owner organizer to record as the reviewer.');
}

$reps = [];
$repStmt = $conn->prepare("SELECT id, fname, lname, email, phone
                           FROM tbl_user
                           WHERE role = '2' AND stat = '1'
                           ORDER BY id");
$repStmt->execute();
$repRes = $repStmt->get_result();
while ($row = $repRes->fetch_assoc()) $reps[] = $row;
$repStmt->close();

if (!$reps) {
    seedFail('No active Team Representative accounts (role 2) were found.');
}

$existingByUser = [];
$existingNames  = [];
$existingStmt   = $conn->prepare("SELECT user_id, team_name, status FROM tbl_team WHERE league_id = ?");
$existingStmt->bind_param('i', $leagueId);
$existingStmt->execute();
$existingRes = $existingStmt->get_result();
while ($row = $existingRes->fetch_assoc()) {
    $existingNames[strtolower((string)$row['team_name'])] = true;
    $statusKey = strtolower((string)$row['status']);
    if (!in_array($statusKey, ['rejected', 'cancelled', 'canceled'], true)) {
        $existingByUser[(string)$row['user_id']] = $row;
    }
}
$existingStmt->close();

$approvedStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
$approvedStmt->bind_param('i', $leagueId);
$approvedStmt->execute();
$approvedCount = (int)($approvedStmt->get_result()->fetch_assoc()['c'] ?? 0);
$approvedStmt->close();

$maxTeams  = (int)($league['max_team'] ?? 0);
$slotsLeft = $maxTeams > 0 ? max(0, $maxTeams - $approvedCount) : null;

$missingReps = [];
foreach ($reps as $rep) {
    if (isset($existingByUser[(string)$rep['id']])) {
        continue;
    }
    $missingReps[] = $rep;
}

$configOnly = false;
if (!$missingReps) {
    if (!$assignGroups) {
        seedOut('Nothing to do — every active representative already has a team in league ' . $leagueId . '.');
        exit(0);
    }
    $configOnly = true;
    seedOut('All active representatives already have a team. Re-applying the stage groups and cut-off rule only.');
}

if (!$configOnly) {
    if ($slotsLeft !== null && $slotsLeft <= 0) {
        seedFail('League ' . $leagueId . ' already has all ' . $maxTeams . ' team slots filled.');
    }

    if ($slotsLeft !== null && count($missingReps) > $slotsLeft) {
        seedOut('Note: only ' . $slotsLeft . ' slot(s) remain; seeding the first ' . $slotsLeft . ' representative(s).');
        $missingReps = array_slice($missingReps, 0, $slotsLeft);
    }
}

$maxPlayers = (int)($league['max_player'] ?? 0);
$minPlayers = (int)($league['min_player'] ?? 0);

if ($playersPerTeam < 0) {
    $playersPerTeam = 0;
}
if ($maxPlayers > 0 && $playersPerTeam > $maxPlayers) {
    seedOut('Note: --players clamped to the league maximum of ' . $maxPlayers . '.');
    $playersPerTeam = $maxPlayers;
}
if ($playersPerTeam > 0 && $minPlayers > 0 && $playersPerTeam < $minPlayers) {
    seedOut('Warning: the league requires at least ' . $minPlayers . ' players per team.');
}

$sampleBarangays = [
    'Barangay San Pedro',
    'Barangay San Vicente',
    'Barangay Santo Niño',
    'Barangay Santa Cruz',
    'Barangay New Pandan',
    'Barangay Gredu',
    'Barangay Cagangohan',
    'Barangay J.P. Laurel',
    'Barangay A. O. Floirendo',
    'Barangay Datu Abdul Dadia',
    'Barangay Kasilak',
    'Barangay Little Panay',
    'Barangay Mabunao',
    'Barangay Malativas',
    'Barangay Manay',
    'Barangay New Malitbog',
];

$teamPlans = [];
foreach ($missingReps as $index => $rep) {
    $firstName = trim((string)$rep['fname']);
    if ($firstName === '') {
        $firstName = 'Team ' . (int)$rep['id'];
    }

    $baseName = ucwords(strtolower($firstName)) . ' Team';
    $teamName = $baseName;
    $suffix   = 2;
    while (isset($existingNames[strtolower($teamName)])) {
        $teamName = $baseName . ' ' . $suffix;
        $suffix++;
    }
    $existingNames[strtolower($teamName)] = true;

    $teamPlans[] = [
        'rep'       => $rep,
        'team_name' => $teamName,
        'barangay'  => $sampleBarangays[$index % count($sampleBarangays)],
    ];
}

seedOut('Demo team seeder — ' . $league['league_name'] . ' (league ' . $leagueId . ')');
seedOut(str_repeat('=', 72));
seedOut('Sport: ' . $league['sport'] . ' | Division: ' . $league['division'] . ' | Category: ' . $league['category']);
seedOut('Approved teams now: ' . $approvedCount . ($maxTeams > 0 ? ' / ' . $maxTeams : ''));
seedOut('Representatives to seed: ' . count($teamPlans));
seedOut('Players per new team: ' . $playersPerTeam);
seedOut('');
seedOut(str_pad('#', 4) . str_pad('Team', 22) . str_pad('Representative', 26) . 'Players');
seedOut(str_repeat('-', 64));
foreach ($teamPlans as $index => $plan) {
    $repName = trim((string)$plan['rep']['fname'] . ' ' . (string)$plan['rep']['lname']);
    seedOut(str_pad((string)($index + 1), 4)
        . str_pad($plan['team_name'], 22)
        . str_pad($repName, 26)
        . $playersPerTeam);
}
seedOut('');

if ($dryRun) {
    seedOut('Stage groups: ' . ($assignGroups
        ? 'refresh the round-robin groups; top ' . $advancePerGroup . ' per group advance to the knockout stage'
        : 'left untouched'));
    seedOut('[DRY RUN] No rows were written.');
    exit(0);
}

/* -------------------------------------------------------------------------
 * 3. Write the teams and their sample rosters
 * ---------------------------------------------------------------------- */

$firstNames = [
    'Miguel', 'Jose', 'Carlo', 'Paolo', 'Rafael', 'Angelo', 'Marco', 'Gabriel',
    'Joshua', 'Christian', 'Daniel', 'Emmanuel', 'Francis', 'Gerald', 'Ivan',
    'Jerome', 'Kevin', 'Lorenzo', 'Mario', 'Nathaniel', 'Oliver', 'Patrick',
    'Reynaldo', 'Samuel', 'Terrence', 'Victor', 'Wendell', 'Xavier', 'Yancy',
    'Zander', 'Adrian', 'Benedict', 'Cedric', 'Dominic', 'Elias', 'Felix',
    'Harold', 'Ian', 'Joaquin', 'Kurt',
];

$surnames = [
    'Dela Cruz', 'Reyes', 'Santos', 'Garcia', 'Mendoza', 'Torres', 'Flores',
    'Rivera', 'Ramos', 'Bautista', 'Villanueva', 'Aquino', 'Castillo',
    'Domingo', 'Salvador', 'Pascual', 'Mercado', 'Lim', 'Tan', 'Gonzales',
];

/* One shuffled pool of unique name combinations keeps the 192 sample players
   distinct and varied between teams. The seed makes the output repeatable. */
$namePool = [];
foreach ($firstNames as $firstNameOption) {
    foreach ($surnames as $surnameOption) {
        $namePool[] = $firstNameOption . ' ' . $surnameOption;
    }
}
mt_srand(20261109 + $leagueId);
shuffle($namePool);

$createdTeamRows = [];
$createdPlayers  = 0;
$statusBefore    = (string)$league['stat'];
$statusAfter     = $statusBefore;
$stageSummary    = ['groups' => [], 'rule' => null];

$conn->begin_transaction();

try {
    $teamStmt = $conn->prepare("INSERT INTO tbl_team
            (team_name, team_image, league_id, bracket, team_repre, email, phone,
             status, user_id, reviewed_by, reviewed_at, barangay)
        VALUES (?, '', ?, '', ?, ?, ?, 'Approved', ?, ?, NOW(), ?)");

    $playerStmt = $conn->prepare("INSERT INTO tbl_player
            (team_id, name, age, birthdate, role, gender, jersey_number, document,
             eligibility_status, eligibility_reviewed_by, eligibility_reviewed_at, barangay)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 'Eligible', ?, NOW(), ?)");

    $nameIndex = 0;

    foreach ($teamPlans as $plan) {
        $rep       = $plan['rep'];
        $teamName  = (string)$plan['team_name'];
        $repName   = trim((string)$rep['fname'] . ' ' . (string)$rep['lname']);
        $repEmail  = (string)$rep['email'];
        $repPhone  = (string)$rep['phone'];
        $repId     = (string)$rep['id'];
        $barangay  = (string)$plan['barangay'];

        $teamStmt->bind_param(
            'sissssis',
            $teamName,
            $leagueId,
            $repName,
            $repEmail,
            $repPhone,
            $repId,
            $ownerId,
            $barangay
        );
        $teamStmt->execute();
        $teamId = (int)$conn->insert_id;

        for ($p = 0; $p < $playersPerTeam; $p++) {
            $playerName = $namePool[$nameIndex % count($namePool)];
            $age        = 18 + ($nameIndex % 13);
            $dayOffset  = ($nameIndex * 11) % 300;
            $birthdate  = (new DateTimeImmutable('today'))
                ->modify(sprintf('-%d years -%d days', $age, $dayOffset))
                ->format('Y-m-d');
            $role       = ['Guard', 'Forward', 'Center'][$p % 3];
            $gender     = 'Male';
            $jersey     = (string)($p + 1);
            $ageText    = (string)$age;
            $teamIdText = (string)$teamId;

            $playerStmt->bind_param(
                'sssssssis',
                $teamIdText,
                $playerName,
                $ageText,
                $birthdate,
                $role,
                $gender,
                $jersey,
                $ownerId,
                $barangay
            );
            $playerStmt->execute();

            $createdPlayers++;
            $nameIndex++;
        }

        $createdTeamRows[] = [
            'id'      => $teamId,
            'team'    => $teamName,
            'rep'     => $repName,
            'players' => $playersPerTeam,
        ];
    }

    $teamStmt->close();
    $playerStmt->close();

    $stageSummary = ['groups' => [], 'rule' => null];
    if ($assignGroups) {
        $stageSummary = seedAssignStageGroups($conn, $leagueId, $advancePerGroup);
    }

    /* Mimic the approval side effect: the stored status follows the live
       status once the new approved teams have filled the available slots. */
    $league['current_teams'] = $approvedCount + count($createdTeamRows);
    $statusAfter = (string)getLeagueStatus($league);

    if ($statusAfter !== $statusBefore) {
        $statStmt = $conn->prepare("UPDATE tbl_league SET stat = ? WHERE id = ?");
        $statStmt->bind_param('si', $statusAfter, $leagueId);
        $statStmt->execute();
        $statStmt->close();
    }

    $conn->commit();
} catch (Throwable $failure) {
    $conn->rollback();
    seedFail('Seeding failed: ' . $failure->getMessage());
}

seedOut('Seeded successfully.');
seedOut(str_repeat('=', 72));
seedOut('Teams created: ' . count($createdTeamRows));
seedOut('Players created: ' . $createdPlayers);
seedOut('League status: ' . $statusBefore . ' -> ' . $statusAfter);
seedOut('');
seedOut(str_pad('ID', 6) . str_pad('Team', 22) . str_pad('Representative', 26) . 'Players');
seedOut(str_repeat('-', 64));
foreach ($createdTeamRows as $row) {
    seedOut(str_pad((string)$row['id'], 6)
        . str_pad($row['team'], 22)
        . str_pad($row['rep'], 26)
        . $row['players']);
}
seedOut('');

if ($assignGroups) {
    seedOut('Stage groups refreshed:');
    foreach ($stageSummary['groups'] as $group) {
        seedOut('  ' . $group['name'] . ' (' . count($group['teams']) . ' teams): ' . implode(', ', $group['teams']));
    }
    if (!empty($stageSummary['rule'])) {
        seedOut('Cut-off rule: top ' . $stageSummary['rule']['advance'] . ' per group advance from "'
            . $stageSummary['rule']['from'] . '" to "' . $stageSummary['rule']['to'] . '".');
    } else {
        seedOut('No knockout stage follows the group stage, so no cut-off rule was saved.');
    }
    seedOut('');
    seedOut('No fixtures or results were created.');
    seedOut('Next: open Setup > Matches to preview/generate the group fixtures, then Schedule to set dates, venues, Committees and publish them.');
} else {
    seedOut('No stages, groups, fixtures or results were created.');
    seedOut('Next: open Setup to apply a competition structure, then Schedule to draw and publish matches.');
}
