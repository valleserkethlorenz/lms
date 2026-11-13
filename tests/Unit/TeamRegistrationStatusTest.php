<?php
/**
 * Tests for Team Registration Status functions
 * Located in: config/teamRegistrationStatus.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class TeamRegistrationStatusTest extends TestCase
{
    /**
     * Test approved status badge class
     */
    public function testApprovedStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('approved');
        $this->assertEquals('registration-status-approved', $result);
    }

    /**
     * Test rejected status badge class
     */
    public function testRejectedStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('rejected');
        $this->assertEquals('registration-status-rejected', $result);
    }

    /**
     * Test cancelled status badge class
     */
    public function testCancelledStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('cancelled');
        $this->assertEquals('registration-status-cancelled', $result);
    }

    /**
     * Test canceled (American spelling) status badge class
     */
    public function testCanceledStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('canceled');
        $this->assertEquals('registration-status-cancelled', $result);
    }

    /**
     * Test correction requested status badge class
     */
    public function testCorrectionRequestedStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('correction requested');
        $this->assertEquals('registration-status-correction', $result);
    }

    /**
     * Test expired status badge class
     */
    public function testExpiredStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('expired');
        $this->assertEquals('registration-status-expired', $result);
    }

    /**
     * Test pending status badge class
     */
    public function testPendingStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('pending');
        $this->assertEquals('registration-status-pending', $result);
    }

    /**
     * Test unknown status badge class
     */
    public function testUnknownStatusBadgeClass(): void
    {
        $result = getTeamRegistrationBadgeClass('unknown_status');
        $this->assertEquals('registration-status-default', $result);
    }

    /**
     * Test case insensitivity
     */
    public function testCaseInsensitivity(): void
    {
        $this->assertEquals('registration-status-approved', getTeamRegistrationBadgeClass('APPROVED'));
        $this->assertEquals('registration-status-approved', getTeamRegistrationBadgeClass('Approved'));
        $this->assertEquals('registration-status-rejected', getTeamRegistrationBadgeClass('REJECTED'));
    }

    /**
     * Test with whitespace trimming
     */
    public function testWhitespaceTrimming(): void
    {
        $this->assertEquals('registration-status-approved', getTeamRegistrationBadgeClass('  approved  '));
        $this->assertEquals('registration-status-pending', getTeamRegistrationBadgeClass(' pending '));
    }

    /**
     * Data provider for all valid statuses
     */
    public static function validStatusProvider(): array
    {
        return [
            'approved' => ['approved', 'registration-status-approved'],
            'rejected' => ['rejected', 'registration-status-rejected'],
            'cancelled' => ['cancelled', 'registration-status-cancelled'],
            'correction' => ['correction requested', 'registration-status-correction'],
            'expired' => ['expired', 'registration-status-expired'],
            'pending' => ['pending', 'registration-status-pending'],
        ];
    }

    /**
     * @dataProvider validStatusProvider
     */
    public function testAllValidStatuses(string $status, string $expectedClass): void
    {
        $result = getTeamRegistrationBadgeClass($status);
        $this->assertEquals($expectedClass, $result);
    }
}