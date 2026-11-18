<?php
/**
 * Integration coverage for includes/standingsSections.php.
 *
 * These tests use the local MySQL database inside a transaction and roll back
 * afterwards, following the same pattern as ReportQueryIntegrationTest.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class StandingsSectionsIntegrationTest extends TestCase
{
    private ?mysqli $conn = null;
    private int $userId = 4;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/standingsSections.php';

        if (getenv('JAWSDB_URL')) {
            $this->markTestSkipped('Standings integration tests run against the local development database only.');
        }

        try {
            $conn = new mysqli('localhost', 'root', '', 'lms', 3306);
            if ($conn->connect_errno) {
                $this->markTestSkipped('Local LMS database is not available: ' . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');
            $conn->query("SET time_zone = '+08:00'");
            if (!$conn->begin_transaction()) {
                $this->markTestSkipped('Could not start a database transaction.');
            }
            $this->conn = $conn;
        } catch (Throwable $e) {
            $this->markTestSkipped('Local LMS database is not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn instanceof mysqli) {
            $this->conn->rollback();
            $this->conn->close();
        }
        $this->conn = null;
    }

    private function createLeague(string $suffix, int $groupingEnabled = 0): int
    {
        $today = date('Y-m-d');
        $name  = 'Standings Sections ' . $suffix . ' ' . bin2hex(random_bytes(4));
        $stmt  = $this->conn->prepare(
            "INSERT INTO tbl_league
                (league_image,league_name,sport,division,descrip,category,start_date,end_date,
                 regis_start,regis_end,venue,max_team,regis_fee,add_info,stat,created_by,publication_status,grouping_enabled)
             VALUES ('', ?, 'Basketball', 'Open', 'Integration test', 'Male', ?, ?,
                     ?, ?, 'Test Venue', '8', '0', '', 'Active', ?, 'Published', ?)"
        );
        $stmt->bind_param('sssssii', $name, $today, $today, $today, $today, $this->userId, $groupingEnabled);
        $stmt->execute();
        $leagueId = (int) $this->conn->insert_id;
        $stmt->close();
        return $leagueId;
    }

    private function createTeam(int $leagueId, string $name, int $teamUserId): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_team
                (team_name,team_image,league_id,bracket,team_repre,email,phone,status,user_id)
             VALUES (?, '', ?, '', 'Test Representative', 'standings-test@example.com', '09000000000', 'Approved', ?)"
        );
        $leagueStr = (string) $leagueId;
        $userStr   = (string) $teamUserId;
        $stmt->bind_param('sss', $name, $leagueStr, $userStr);
        $stmt->execute();
        $teamId = (int) $this->conn->insert_id;
        $stmt->close();
        return $teamId;
    }

    private function createStage(int $leagueId, string $name, string $type, string $format, int $order = 1, int $qualifiers = 0): int
    {
        $stageStatus = 'Active';
        $carry = 0;
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_league_stage
                (league_id,name,stage_type,stage_format,stage_status,display_order,qualifiers_per_group,carries_previous_results,is_active)
             VALUES (?,?,?,?,?,?,?,?,1)"
        );
        $stmt->bind_param('issssiii', $leagueId, $name, $type, $format, $stageStatus, $order, $qualifiers, $carry);
        $stmt->execute();
        $stageId = (int) $this->conn->insert_id;
        $stmt->close();
        return $stageId;
    }

    private function createStageGroup(int $stageId, string $name, int $order): int
    {
        $stmt = $this->conn->prepare("INSERT INTO tbl_league_stage_group (stage_id,name,display_order) VALUES (?,?,?)");
        $stmt->bind_param('isi', $stageId, $name, $order);
        $stmt->execute();
        $groupId = (int) $this->conn->insert_id;
        $stmt->close();
        return $groupId;
    }

    private function assignStageTeam(int $groupId, int $teamId, ?int $seed = null): void
    {
        $stmt = $this->conn->prepare("INSERT INTO tbl_league_stage_group_team (stage_group_id,team_id,seed) VALUES (?,?,?)");
        $stmt->bind_param('iii', $groupId, $teamId, $seed);
        $stmt->execute();
        $stmt->close();
    }

    private function createStageTableMatch(int $leagueId, int $stageId, int $groupId, int $teamA, int $teamB, int $scoreA, int $scoreB, int $winner): void
    {
        $status  = 'Completed';
        $winType = 'Regular';
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_schedule
                (league_id,stage_id,stage_group_id,team_a,team_b,a_score,b_score,date,status,winner,published_at,win_type,result_status,round)
             VALUES (?,?,?,?,?,?,?,CURDATE(),?,?,NOW(),?,'Final',1)"
        );
        $stmt->bind_param('iiiiiiisis', $leagueId, $stageId, $groupId, $teamA, $teamB, $scoreA, $scoreB, $status, $winner, $winType);
        $stmt->execute();
        $stmt->close();
    }

    private function createBracketMatch(
        int $leagueId,
        int $stageId,
        int $teamA,
        int $teamB,
        int $scoreA,
        int $scoreB,
        int $winner,
        string $status,
        ?string $publishedAt,
        int $round,
        string $nodeKey,
        string $seedA,
        string $seedB
    ): void {
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_schedule
                (league_id,stage_id,team_a,team_b,a_score,b_score,date,status,winner,published_at,
                 win_type,result_status,round,bracket,bracket_side,node_key,seed_a,seed_b)
             VALUES (?,?,?,?,?,?,CURDATE(),?,?,?, 'Regular','Final',?, 'knockout','winners',?,?,?)"
        );
        $stmt->bind_param(
            'iiiiiisisisss',
            $leagueId, $stageId, $teamA, $teamB, $scoreA, $scoreB,
            $status, $winner, $publishedAt, $round, $nodeKey, $seedA, $seedB
        );
        $stmt->execute();
        $stmt->close();
    }

    public function testRoundRobinStageGroupsBecomeSeparateTables(): void
    {
        $leagueId = $this->createLeague('groups');
        $teamA = $this->createTeam($leagueId, 'Group A One', 9301);
        $teamB = $this->createTeam($leagueId, 'Group A Two', 9302);
        $teamC = $this->createTeam($leagueId, 'Group B One', 9303);
        $teamD = $this->createTeam($leagueId, 'Group B Two', 9304);

        $stageId = $this->createStage($leagueId, 'Group Stage', 'group', 'round_robin', 1, 2);
        $groupA  = $this->createStageGroup($stageId, 'Group A', 1);
        $groupB  = $this->createStageGroup($stageId, 'Group B', 2);
        $this->assignStageTeam($groupA, $teamA, 1);
        $this->assignStageTeam($groupA, $teamB, 2);
        $this->assignStageTeam($groupB, $teamC, 1);
        $this->assignStageTeam($groupB, $teamD, 2);

        $this->createStageTableMatch($leagueId, $stageId, $groupA, $teamA, $teamB, 80, 70, $teamA);
        $this->createStageTableMatch($leagueId, $stageId, $groupB, $teamC, $teamD, 60, 50, $teamC);

        $rules = getLeagueScoringRules($this->conn, $leagueId);
        $data  = getLeagueStandingsSections($this->conn, $leagueId);

        $this->assertSame('stages', $data['mode']);
        $this->assertCount(1, $data['sections']);
        $section = $data['sections'][0];
        $this->assertSame('table', $section['kind']);
        $this->assertSame('Group Stage', $section['stage_name']);
        $this->assertCount(2, $section['tables']);
        $this->assertSame('Complete', $section['stage_status']);
        $this->assertSame(2, $section['match_total']);

        $tableA = $section['tables'][0];
        $tableB = $section['tables'][1];
        $this->assertCount(2, $tableA['rows']);
        $this->assertCount(2, $tableB['rows']);
        $this->assertSame('Group A One', $tableA['rows'][0]['team_name']);
        $this->assertSame((int)$rules['win'], (int)$tableA['rows'][0]['points']);
        $this->assertSame((int)$rules['loss'], (int)$tableA['rows'][1]['points']);
        $this->assertSame('Group B One', $tableB['rows'][0]['team_name']);
    }

    private function createLegacyMatch(int $leagueId, int $teamA, int $teamB, int $scoreA, int $scoreB, int $winner): void
    {
        $winType = 'Regular';
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_schedule
                (league_id,team_a,team_b,a_score,b_score,date,status,winner,published_at,win_type,result_status)
             VALUES (?,?,?,?,?,CURDATE(),'Completed',?,NOW(),?,'Final')"
        );
        $stmt->bind_param('iiiiiis', $leagueId, $teamA, $teamB, $scoreA, $scoreB, $winner, $winType);
        $stmt->execute();
        $stmt->close();
    }

    public function testBracketStageKeepsTbdFinalInProgress(): void
    {
        $leagueId = $this->createLeague('bracket');
        $teamA = $this->createTeam($leagueId, 'Bracket Team A', 9311);
        $teamB = $this->createTeam($leagueId, 'Bracket Team B', 9312);
        $teamC = $this->createTeam($leagueId, 'Bracket Team C', 9313);
        $teamD = $this->createTeam($leagueId, 'Bracket Team D', 9314);
        $stageId = $this->createStage($leagueId, 'Knockout', 'knockout', 'single_elimination', 1, 0);
        $published = date('Y-m-d H:i:s');

        $this->createBracketMatch($leagueId, $stageId, $teamA, $teamB, 80, 70, $teamA, 'Completed', $published, 1, 'W-R1-M1', '1', '4');
        $this->createBracketMatch($leagueId, $stageId, $teamC, $teamD, 60, 50, $teamC, 'Completed', $published, 1, 'W-R1-M2', '3', '2');
        $this->createBracketMatch($leagueId, $stageId, 0, 0, 0, 0, 0, 'Draft', null, 2, 'W-R2-M1', 'W SF1', 'W SF2');

        $data = getLeagueStandingsSections($this->conn, $leagueId);

        $this->assertSame('stages', $data['mode']);
        $this->assertCount(1, $data['sections']);
        $section = $data['sections'][0];
        $this->assertSame('bracket', $section['kind']);
        $this->assertCount(1, $section['sides']);
        $this->assertCount(2, $section['sides'][0]['rounds']);
        $this->assertSame('Semifinal', $section['sides'][0]['rounds'][0]['label']);
        $this->assertSame('Final', $section['sides'][0]['rounds'][1]['label']);
        $this->assertSame('Active', $section['stage_status'], 'A draft/TBD final must keep the bracket in progress');
        $this->assertSame(3, $section['match_total']);
        $this->assertSame(2, $section['match_completed']);

        $final = $section['sides'][0]['rounds'][1]['matches'][0];
        $this->assertFalse((bool)$final['is_published']);
        $this->assertSame(0, (int)$final['winner']);
    }

    public function testLeagueWithoutStagesFallsBackToLegacyTable(): void
    {
        $leagueId = $this->createLeague('legacy', 0);
        $teamA = $this->createTeam($leagueId, 'Legacy Winner', 9321);
        $teamB = $this->createTeam($leagueId, 'Legacy Loser', 9322);
        $this->createLegacyMatch($leagueId, $teamA, $teamB, 75, 65, $teamA);

        $data = getLeagueStandingsSections($this->conn, $leagueId);

        $this->assertSame('legacy', $data['mode']);
        $this->assertCount(1, $data['sections']);
        $section = $data['sections'][0];
        $this->assertSame('table', $section['kind']);
        $this->assertSame('League Rankings', $section['stage_name']);
        $this->assertCount(1, $section['tables']);
        $this->assertCount(2, $section['tables'][0]['rows']);
        $this->assertSame('Legacy Winner', $section['tables'][0]['rows'][0]['team_name']);
    }
}
