<?php
/**
 * Recipient-scoping tests for includes/notificationHelper.php
 *
 * The stated rule: a Team Representative is notified only about their own
 * team's fixture and result. These tests pin the resolver that enforces it,
 * plus the cancellation notice that finally feeds the organizer.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/notificationHelper.php';
require_once __DIR__ . '/../../includes/teamCorrectionDeadline.php';

/** Minimal mysqli_result stand-in: fetch_assoc() over a fixed row list. */
final class RecipientFakeResult
{
    /** @var array<int,array<string,mixed>> */
    private array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetch_assoc(): ?array
    {
        if (empty($this->rows)) {
            return null;
        }

        return array_shift($this->rows);
    }

    public function fetch_row(): ?array
    {
        $row = $this->fetch_assoc();

        return $row === null ? null : array_values($row);
    }
}

/** Records one prepared statement and the rows it would return. */
final class RecipientFakeStatement
{
    public string $sql;
    public string $types = '';
    public array $params = [];
    public string $error = '';
    public bool $closed = false;
    public bool $isInsert;
    public int $affected_rows = 1;

    /** @var array<int,array<string,mixed>> */
    private array $rows;

    public function __construct(string $sql, array $rows)
    {
        $this->sql = $sql;
        $this->rows = $rows;
        $this->isInsert = stripos($sql, 'INSERT INTO tbl_notification') !== false;
    }

    public function bind_param(...$args): bool
    {
        $this->types = (string) array_shift($args);
        $this->params = $args;

        return true;
    }

    public function execute(): bool
    {
        return true;
    }

    public function get_result(): RecipientFakeResult
    {
        return new RecipientFakeResult($this->rows);
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

/**
 * Fake connection that routes each prepared statement to a canned row list by
 * matching a SQL substring, so no database is needed.
 */
final class RecipientFakeConnection
{
    public string $error = '';

    /** @var array<string,array<int,array<string,mixed>>> */
    public array $selectRows = [];

    /** @var RecipientFakeStatement[] */
    public array $statements = [];

    public function prepare(string $sql)
    {
        $rows = [];
        foreach ($this->selectRows as $needle => $mappedRows) {
            if (stripos($sql, (string) $needle) !== false) {
                $rows = $mappedRows;
                break;
            }
        }

        $statement = new RecipientFakeStatement($sql, $rows);
        $this->statements[] = $statement;

        return $statement;
    }

    /** @return RecipientFakeStatement[] */
    public function notificationInserts(): array
    {
        return array_values(array_filter(
            $this->statements,
            static function (RecipientFakeStatement $statement): bool {
                return $statement->isInsert;
            }
        ));
    }
}

final class NotificationRecipientsTest extends TestCase
{
    private RecipientFakeConnection $conn;

    protected function setUp(): void
    {
        $this->conn = new RecipientFakeConnection();
    }

    public function testSchedulePublicationNotifiesOnlyTheTwoTeamsInTheMatch(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_schedule'       => [['team_a' => 5, 'team_b' => 6]],
            'SELECT DISTINCT user_id' => [['user_id' => 11], ['user_id' => 12]],
        ];

        $sent = notifyScheduleUpdate($this->conn, 3, 99, 'Match in "League": Alpha vs Bravo.');

        $this->assertSame(2, $sent);
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(2, $inserts);
        $this->assertSame([11, 12], array_map(static fn($row) => (int) $row->params[0], $inserts));
        foreach ($inserts as $insert) {
            $this->assertSame('schedule_update', $insert->params[1]);
            $this->assertSame(99, (int) $insert->params[4]);
        }
    }

    public function testScheduleFromAnotherLeagueNotifiesNobody(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_schedule'       => [],
            'SELECT DISTINCT user_id' => [['user_id' => 11]],
        ];

        $this->assertSame(0, notifyScheduleUpdate($this->conn, 3, 99, 'Nope'));
        $this->assertCount(0, $this->conn->notificationInserts());
    }

    public function testTeamRepresentativesDeduplicatesTeamIdsAndSkipsEmptyOnes(): void
    {
        $this->conn->selectRows = [
            'SELECT DISTINCT user_id' => [['user_id' => 7]],
        ];

        $sent = notifyTeamRepresentatives($this->conn, [0, 5, 5, -2], 'schedule_update', 'Title', 'Message', 42);

        $this->assertSame(1, $sent);
        $lookup = $this->conn->statements[0];
        $this->assertSame([5], $lookup->params);
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertSame(7, (int) $inserts[0]->params[0]);
    }

    public function testCancellationNotifiesOnlyTheLeagueOwner(): void
    {
        $this->conn->selectRows = [
            'JOIN tbl_league l ON l.id = t.league_id' => [['created_by' => 3]],
        ];

        $this->assertTrue(notifyTeamCancelled($this->conn, 42, 'Alpha'));
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertSame(3, (int) $inserts[0]->params[0]);
        $this->assertSame('team_cancelled', $inserts[0]->params[1]);
        $this->assertStringContainsString('Alpha', (string) $inserts[0]->params[3]);
    }

    public function testCancellationWithoutAnOwnerWritesNothing(): void
    {
        $this->conn->selectRows = [
            'JOIN tbl_league l ON l.id = t.league_id' => [['created_by' => null]],
        ];

        $this->assertFalse(notifyTeamCancelled($this->conn, 42, 'Alpha'));
        $this->assertCount(0, $this->conn->notificationInserts());
    }

    public function testNewLeagueSaysRegistrationOpensLaterWhenStartIsInTheFuture(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_user' => [['id' => 7]],
        ];
        $start = date('Y-m-d', strtotime('+10 days'));
        $end   = date('Y-m-d', strtotime('+20 days'));

        $sent = notifyNewLeague($this->conn, 3, 'Summer Cup', $end, $start);

        $this->assertSame(1, $sent);
        $this->assertStringContainsString("stat = '1'", $this->conn->statements[0]->sql);
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertStringContainsString('Registration opens on', (string) $inserts[0]->params[3]);
        $this->assertStringNotContainsString('open now', (string) $inserts[0]->params[3]);
    }

    public function testNewLeagueSaysOpenNowWhenStartHasPassed(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_user' => [['id' => 7]],
        ];
        $start = date('Y-m-d', strtotime('-1 day'));
        $end   = date('Y-m-d', strtotime('+20 days'));

        $sent = notifyNewLeague($this->conn, 3, 'Summer Cup', $end, $start);

        $this->assertSame(1, $sent);
        $inserts = $this->conn->notificationInserts();
        $this->assertStringContainsString('Registration is open now until', (string) $inserts[0]->params[3]);
    }

    public function testRegistrationClosingOnlyTargetsJoinableLeaguesAndActiveReps(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_league l'       => [[
                'id' => 3,
                'league_name' => 'Summer Cup',
                'regis_end' => date('Y-m-d', strtotime('+3 days')),
            ]],
            'FROM tbl_user u'         => [['id' => 7]],
            'FROM tbl_notification'   => [],
        ];

        $sent = notifyRegistrationClosingSoon($this->conn, 3);

        $this->assertSame(1, $sent);
        $leagueSql = $this->conn->statements[0]->sql;
        $this->assertStringContainsString("publication_status = 'Published'", $leagueSql);
        $this->assertStringContainsString('registration_window_open = 1', $leagueSql);
        $this->assertStringContainsString('regis_start <= CURDATE()', $leagueSql);
        $this->assertStringContainsString('max_team', $leagueSql);

        $userSql = $this->conn->statements[1]->sql;
        $this->assertStringContainsString("u.stat = '1'", $userSql);
        $this->assertStringContainsString("LOWER(t.status) NOT IN ('rejected', 'cancelled', 'canceled', 'expired')", $userSql);

        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertStringContainsString('Summer Cup', (string) $inserts[0]->params[3]);
    }

    public function testCorrectionExpiryNotifiesTheRepAndTheOrganizer(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_team t'        => [[
                'id' => 42,
                'team_name' => 'Alpha',
                'user_id' => 7,
                'league_id' => 3,
                'league_name' => 'Summer Cup',
                'organizer_id' => 1,
            ]],
            'FROM tbl_notification'  => [],
        ];

        $this->assertSame(1, expireTeamCorrectionDeadlines($this->conn, 3));

        $inserts = $this->conn->notificationInserts();
        $this->assertCount(2, $inserts);
        $recipients = array_map(static fn($row) => (int) $row->params[0], $inserts);
        $this->assertContains(7, $recipients);
        $this->assertContains(1, $recipients);

        $organizerMessage = '';
        foreach ($inserts as $insert) {
            $this->assertSame('team_correction_expired', $insert->params[1]);
            $this->assertSame(42, (int) $insert->params[4]);
            if ((int) $insert->params[0] === 1) {
                $organizerMessage = (string) $insert->params[3];
            }
        }
        $this->assertStringContainsString('Summer Cup', $organizerMessage);
    }

    public function testTeamRegistrationResubmissionIsLabelledCorrectly(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_league' => [[
                'created_by' => 1,
                'league_name' => 'Summer Cup',
            ]],
        ];

        $sent = notifyTeamRegistration($this->conn, 3, 'Alpha', 42, true);

        $this->assertTrue($sent);
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertSame('Team Registration Resubmitted', $inserts[0]->params[2]);
        $this->assertStringContainsString('resubmitted its corrected application', (string) $inserts[0]->params[3]);
        $this->assertStringContainsString('Summer Cup', (string) $inserts[0]->params[3]);
    }

    public function testTeamApprovedMessageUsesTheLeagueName(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_team t' => [[
                'user_id' => 7,
                'league_name' => 'Summer Cup',
            ]],
        ];

        $this->assertTrue(notifyTeamApproved($this->conn, 42, 'Alpha'));
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertStringContainsString('Summer Cup', (string) $inserts[0]->params[3]);
        $this->assertStringNotContainsString('bracket', (string) $inserts[0]->params[3]);
    }

    public function testResultReopenedMessageIncludesTheMatchContext(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_schedule s'       => [[
                'team_a' => 5,
                'team_b' => 6,
                'committee_user_id' => 4,
                'date' => '2026-06-15',
                'league_name' => 'Summer Cup',
                'team_a_name' => 'Alpha',
                'team_b_name' => 'Bravo',
            ]],
            'SELECT DISTINCT user_id'   => [['user_id' => 7]],
            'FROM tbl_notification'     => [],
        ];

        $this->assertSame(2, notifyResultReopened($this->conn, 99));
        $inserts = $this->conn->notificationInserts();
        $this->assertCount(2, $inserts);

        $messages = array_map(static fn($row) => (string) $row->params[3], $inserts);
        foreach ($messages as $message) {
            $this->assertStringContainsString('Summer Cup', $message);
            $this->assertStringContainsString('Alpha vs Bravo', $message);
        }
    }

    public function testCommitteeMatchDayReminderUsesUpcomingMatchType(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_schedule s'    => [[
                'id' => 99,
                'committee_user_id' => 4,
                'time' => '18:00:00',
                'venue' => 'Main Gym',
                'league_name' => 'Summer Cup',
                'team_a_name' => 'Alpha',
                'team_b_name' => 'Bravo',
            ]],
            'FROM tbl_notification'  => [],
        ];

        $this->assertSame(1, notifyCommitteeUpcomingMatchToday($this->conn));
        $matchSql = $this->conn->statements[0]->sql;
        $this->assertStringContainsString('s.committee_user_id', $matchSql);
        $this->assertStringContainsString('s.published_at IS NOT NULL', $matchSql);

        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertSame(4, (int) $inserts[0]->params[0]);
        $this->assertSame('upcoming_match', $inserts[0]->params[1]);
        $this->assertStringContainsString('officiating today', (string) $inserts[0]->params[3]);
        $this->assertStringContainsString('Summer Cup', (string) $inserts[0]->params[3]);
    }

    public function testCommitteePendingResultUsesResultReminderType(): void
    {
        $this->conn->selectRows = [
            'FROM tbl_schedule s'    => [[
                'id' => 100,
                'committee_user_id' => 4,
                'date' => '2026-06-15',
                'time' => '18:00:00',
                'venue' => 'Main Gym',
                'league_name' => 'Summer Cup',
                'team_a_name' => 'Alpha',
                'team_b_name' => 'Bravo',
            ]],
            'FROM tbl_notification'  => [],
        ];

        $this->assertSame(1, notifyCommitteePendingResults($this->conn));
        $matchSql = $this->conn->statements[0]->sql;
        $this->assertStringContainsString('s.result_submitted_by IS NULL', $matchSql);
        $this->assertStringContainsString('DATE_SUB(NOW(), INTERVAL 3 HOUR)', $matchSql);
        $this->assertStringContainsString('DATE_SUB(CURDATE(), INTERVAL 7 DAY)', $matchSql);

        $inserts = $this->conn->notificationInserts();
        $this->assertCount(1, $inserts);
        $this->assertSame('result_reminder', $inserts[0]->params[1]);
        $this->assertSame('Result Needed', $inserts[0]->params[2]);
        $this->assertStringContainsString('has not been submitted', (string) $inserts[0]->params[3]);
    }
}
