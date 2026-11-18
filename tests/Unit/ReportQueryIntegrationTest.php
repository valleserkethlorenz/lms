<?php
/**
 * Integration tests for the report query builder.
 *
 * These use the local development database inside a transaction and roll back
 * afterwards. When no database is reachable the tests are skipped, so the
 * default unit suite still works on machines without MySQL.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ReportQueryIntegrationTest extends TestCase
{
    private ?mysqli $conn = null;
    private int $userId = 4;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/reportQuery.php';

        // Never run fixture inserts against a hosted/production database.
        if (getenv('JAWSDB_URL')) {
            $this->markTestSkipped('Report integration tests run against the local development database only.');
        }

        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $name = 'lms';
        $port = 3306;

        try {
            $conn = new mysqli($host, $user, $pass, $name, $port);
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

    private function createLeague(string $suffix): int
    {
        $today = date('Y-m-d');
        $name  = 'Report Integration ' . $suffix . ' ' . bin2hex(random_bytes(4));
        $stmt  = $this->conn->prepare(
            "INSERT INTO tbl_league
                (league_image,league_name,sport,division,descrip,category,start_date,end_date,
                 regis_start,regis_end,venue,max_team,regis_fee,add_info,stat,created_by,publication_status)
             VALUES ('', ?, 'Basketball', 'Open', 'Integration test', 'Male', ?, ?,
                     ?, ?, 'Test Venue', '8', '0', '', 'Active', ?, 'Published')"
        );
        $stmt->bind_param('sssssi', $name, $today, $today, $today, $today, $this->userId);
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
             VALUES (?, '', ?, '', 'Test Representative', 'report-test@example.com', '09000000000', 'Approved', ?)"
        );
        $leagueStr = (string) $leagueId;
        $userStr   = (string) $teamUserId;
        $stmt->bind_param('sss', $name, $leagueStr, $userStr);
        $stmt->execute();
        $teamId = (int) $this->conn->insert_id;
        $stmt->close();
        return $teamId;
    }

    private function createCompletedMatch(int $leagueId, int $teamA, int $teamB, int $scoreA, int $scoreB, int $winner, string $winType): int
    {
        $status      = 'Completed';
        $winnerValue = (string) $winner;
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_schedule
                (league_id,team_a,team_b,a_score,b_score,date,status,winner,published_at,win_type,result_status)
             VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, NOW(), ?, 'Final')"
        );
        $stmt->bind_param('iiiiisss', $leagueId, $teamA, $teamB, $scoreA, $scoreB, $status, $winnerValue, $winType);
        $stmt->execute();
        $matchId = (int) $this->conn->insert_id;
        $stmt->close();
        return $matchId;
    }

    public function testDatabaseErrorsAreWrappedWithAGenericMessage(): void
    {
        try {
            reportFetchAll($this->conn, 'SELECT * FROM table_that_does_not_exist');
            $this->fail('Expected a ReportExecutionException.');
        } catch (ReportExecutionException $e) {
            $this->assertSame('Unable to generate the selected report.', $e->getMessage());
        }
    }

    public function testResultsUseTheOfficialWinnerColumnAndExcludeByes(): void
    {
        $leagueId = $this->createLeague('results');
        $teamA    = $this->createTeam($leagueId, 'Result Team A', 9101);
        $teamB    = $this->createTeam($leagueId, 'Result Team B', 9102);

        // A double forfeit: no winner, but not a draw.
        $this->createCompletedMatch($leagueId, $teamA, $teamB, 0, 0, 0, 'DoubleForfeit');
        // A bye row: completed one-team row that must not appear in results.
        $this->createCompletedMatch($leagueId, $teamA, 0, 0, 0, $teamA, 'Regular');

        $report = buildReportQuery($this->conn, $this->userId, [
            'report_type' => 'results',
            'league_id'   => $leagueId,
        ]);

        $this->assertSame(1, $report['row_count']);
        $this->assertSame('Double forfeit', $report['rows'][0]['Winner']);
        $this->assertSame('DoubleForfeit', $report['rows'][0]['Win_Type']);
    }

    public function testStandingsOrderAndPointsMatchTheCanonicalProvider(): void
    {
        $leagueId = $this->createLeague('standings');
        $teamA    = $this->createTeam($leagueId, 'Standings Winner', 9201);
        $teamB    = $this->createTeam($leagueId, 'Standings Loser', 9202);

        // Winner scores 80, loser 70; the canonical provider awards the
        // league's configured win points (default 3).
        $this->createCompletedMatch($leagueId, $teamA, $teamB, 80, 70, $teamA, 'Regular');

        $report    = buildReportQuery($this->conn, $this->userId, [
            'report_type' => 'standings',
            'league_id'   => $leagueId,
        ]);
        $canonical = getLeagueStandings($this->conn, $leagueId);

        $this->assertNotEmpty($report['rows']);
        $this->assertArrayHasKey('Points', $report['rows'][0]);
        $this->assertSame(
            array_column($canonical, 'team_name'),
            array_column($report['rows'], 'Team'),
            'The report must use the official standings order.'
        );
        $this->assertSame((int) $canonical[0]['points'], (int) $report['rows'][0]['Points']);
        $this->assertSame('Standings Winner', $report['rows'][0]['Team']);
    }
}
