<?php
/**
 * Tests for includes/notificationHelper.php
 *
 * createNotification() is the single write path of the notification pipeline.
 * A notification is a side effect of a business action (approve a team, publish
 * a result, release a schedule) and every one of those actions runs inside a
 * transaction, so the helper must never throw and must never write a row it
 * cannot attribute to a real user.
 *
 * The helper only touches mysqli through prepare() / bind_param() / execute() /
 * close(), so a fake connection exercises every branch without a database.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/notificationHelper.php';

/** Records the statement createNotification() tried to run. */
final class FakeNotificationStatement
{
    public string $types = '';
    public array $params = [];
    public bool $closed = false;
    public string $error = 'fake insert error';

    private bool $executesSuccessfully;
    private bool $throws;

    public function __construct(bool $executesSuccessfully, bool $throws = false)
    {
        $this->executesSuccessfully = $executesSuccessfully;
        $this->throws = $throws;
    }

    public function bind_param(...$args): bool
    {
        $this->types = (string) array_shift($args);
        $this->params = $args;

        return true;
    }

    public function execute(): bool
    {
        if ($this->throws) {
            throw new RuntimeException('fake execute error');
        }

        return $this->executesSuccessfully;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

/** Minimal stand-in for the mysqli connection used by the helper. */
final class FakeNotificationConnection
{
    public string $error = 'fake prepare error';
    public ?string $lastSql = null;

    /** @var FakeNotificationStatement[] */
    public array $statements = [];

    /** false makes prepare() fail the way a broken connection does. */
    public ?bool $prepareResult = null;

    /** Result returned by every prepared statement. */
    public bool $executeResult = true;

    /** MySQLi reports errors by throwing since PHP 8.1. */
    public bool $throwOnPrepare = false;
    public bool $throwOnExecute = false;

    public function prepare(string $sql)
    {
        $this->lastSql = $sql;

        if ($this->throwOnPrepare) {
            throw new RuntimeException('fake prepare error');
        }

        if ($this->prepareResult === false) {
            return false;
        }

        $statement = new FakeNotificationStatement($this->executeResult, $this->throwOnExecute);
        $this->statements[] = $statement;

        return $statement;
    }
}

final class NotificationHelperTest extends TestCase
{
    private FakeNotificationConnection $conn;

    protected function setUp(): void
    {
        $this->conn = new FakeNotificationConnection();
    }

    public function testWritesOneRowWithTheExpectedBinding(): void
    {
        $created = createNotification(
            $this->conn,
            7,
            'team_approved',
            'Team Approved',
            'Your team was approved.',
            12
        );

        $this->assertTrue($created);
        $this->assertStringContainsString('INSERT INTO tbl_notification', (string) $this->conn->lastSql);
        $this->assertCount(1, $this->conn->statements);

        $statement = $this->conn->statements[0];
        $this->assertSame('isssi', $statement->types);
        $this->assertSame(
            [7, 'team_approved', 'Team Approved', 'Your team was approved.', 12],
            $statement->params
        );
        $this->assertTrue($statement->closed, 'The statement must always be closed.');
    }

    public function testSkipsTheInsertWhenTheRecipientIsMissing(): void
    {
        $this->assertFalse(createNotification($this->conn, 0, 'team_approved', 'Title', 'Message'));
        $this->assertSame([], $this->conn->statements);
        $this->assertNull($this->conn->lastSql);
    }

    public function testSkipsTheInsertWhenTheRecipientIsNotNumeric(): void
    {
        // tbl_team.user_id is a nullable varchar in older schemas: an empty or
        // textual value must not become user 0 and break the foreign key.
        $this->assertFalse(createNotification($this->conn, '', 'team_approved', 'Title', 'Message'));
        $this->assertFalse(createNotification($this->conn, 'abc', 'team_approved', 'Title', 'Message'));
        $this->assertSame([], $this->conn->statements);
    }

    public function testSkipsTheInsertWithoutAType(): void
    {
        $this->assertFalse(createNotification($this->conn, 5, '   ', 'Title', 'Message'));
        $this->assertSame([], $this->conn->statements);
    }
    public function testSkipsTheInsertWithoutATitle(): void
    {
        $this->assertFalse(createNotification($this->conn, 5, 'team_approved', "\t", 'Message'));
        $this->assertSame([], $this->conn->statements);
    }



    /**
     * @dataProvider missingRelatedIdProvider
     */
    public function testAnAbsentRelatedRecordIsStoredAsNull($relatedId): void
    {
        $this->assertTrue(createNotification($this->conn, 5, 'team_approved', 'Title', 'Message', $relatedId));

        // mysqli sends SQL NULL for a PHP null bound as 'i'.
        $this->assertNull($this->conn->statements[0]->params[4]);
    }

    public static function missingRelatedIdProvider(): array
    {
        return [
            'null' => [null],
            'zero' => [0],
            'empty string' => [''],
        ];
    }

    public function testTrimsAndTruncatesValuesToTheirColumnWidths(): void
    {
        $created = createNotification(
            $this->conn,
            '9',
            '  ' . str_repeat('t', 80) . '  ',
            str_repeat('T', 400),
            'Message'
        );

        $this->assertTrue($created);
        $params = $this->conn->statements[0]->params;

        $this->assertSame(9, $params[0], 'The recipient is cast to int.');
        $this->assertSame(50, strlen($params[1]), 'type is varchar(50).');
        $this->assertSame(255, strlen($params[2]), 'title is varchar(255).');
        $this->assertStringEndsWith('...', $params[2]);
    }

    public function testReturnsFalseInsteadOfThrowingWhenPrepareFails(): void
    {
        $this->conn->prepareResult = false;

        $this->assertFalse(createNotification($this->conn, 5, 'team_approved', 'Title', 'Message'));
        $this->assertSame([], $this->conn->statements);
    }

    public function testReturnsFalseInsteadOfThrowingWhenExecuteFails(): void
    {
        $this->conn->executeResult = false;

        $this->assertFalse(createNotification($this->conn, 5, 'team_approved', 'Title', 'Message'));
        $this->assertTrue(
            $this->conn->statements[0]->closed,
            'A failed insert must still close its statement.'
        );
    }

    /**
     * PHP 8.1+ makes mysqli throw on errors (MYSQLI_REPORT_STRICT), which is the
     * mode the app runs in: a foreign-key or column failure must not escape and
     * roll back the caller's transaction.
     */
    public function testDatabaseExceptionsAreSwallowed(): void
    {
        $this->conn->throwOnExecute = true;

        $this->assertFalse(createNotification($this->conn, 5, 'team_approved', 'Title', 'Message'));
        $this->assertTrue($this->conn->statements[0]->closed);

        $this->conn = new FakeNotificationConnection();
        $this->conn->throwOnPrepare = true;

        $this->assertFalse(createNotification($this->conn, 5, 'team_approved', 'Title', 'Message'));
    }
}
