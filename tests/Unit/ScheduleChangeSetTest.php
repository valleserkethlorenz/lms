<?php
/**
 * Tests for scheduleChangeSet() in includes/schedulingHelper.php
 *
 * The organizer edit form always posts date, time, venue and committee. These
 * tests pin the "did anything actually change?" decision so a committee-only
 * edit can no longer send a false "Match Rescheduled" notification.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/schedulingHelper.php';

final class ScheduleChangeSetTest extends TestCase
{
    private array $current = [
        'date'               => '2026-06-15',
        'time'               => '08:00:00',
        'venue'              => 'Main Gym',
        'team_a'             => 5,
        'team_b'             => 6,
        'committee_user_id'  => 3,
    ];

    public function testIdenticalFormPostingReportsNoChange(): void
    {
        $change = scheduleChangeSet($this->current, [
            'date'              => '2026-06-15',
            'time'              => '08:00', // form posts HH:MM, DB stores HH:MM:SS
            'venue'             => 'Main Gym',
            'team_a_id'         => 0,       // update handler keeps the stored team
            'team_b_id'         => 0,
            'committee_user_id' => 3,
        ]);

        $this->assertFalse($change['date_changed']);
        $this->assertFalse($change['time_changed']);
        $this->assertFalse($change['venue_changed']);
        $this->assertFalse($change['teams_changed']);
        $this->assertFalse($change['committee_changed']);
        $this->assertFalse($change['schedule_changed']);
    }

    public function testDateAndTimeChangesAreDetected(): void
    {
        $change = scheduleChangeSet($this->current, [
            'date'              => '2026-06-20',
            'time'              => '18:30',
            'venue'             => 'Main Gym',
            'team_a_id'         => 0,
            'team_b_id'         => 0,
            'committee_user_id' => 3,
        ]);

        $this->assertTrue($change['date_changed']);
        $this->assertTrue($change['time_changed']);
        $this->assertSame('2026-06-15', $change['old_date']);
        $this->assertSame('2026-06-20', $change['new_date']);
        $this->assertSame('08:00:00', $change['old_time']);
        $this->assertSame('18:30:00', $change['new_time']);
        $this->assertTrue($change['schedule_changed']);
    }

    public function testVenueOnlyChangeIsDetected(): void
    {
        $change = scheduleChangeSet($this->current, [
            'date'              => '2026-06-15',
            'time'              => '08:00',
            'venue'             => 'Court 2',
            'team_a_id'         => 0,
            'team_b_id'         => 0,
            'committee_user_id' => 3,
        ]);

        $this->assertTrue($change['venue_changed']);
        $this->assertSame('Main Gym', $change['old_venue']);
        $this->assertSame('Court 2', $change['new_venue']);
        $this->assertTrue($change['schedule_changed']);
    }

    public function testCommitteeChangeDoesNotCountAsScheduleChange(): void
    {
        $change = scheduleChangeSet($this->current, [
            'date'              => '2026-06-15',
            'time'              => '08:00',
            'venue'             => 'Main Gym',
            'team_a_id'         => 0,
            'team_b_id'         => 0,
            'committee_user_id' => 7,
        ]);

        $this->assertTrue($change['committee_changed']);
        $this->assertSame(7, $change['final_committee_id']);
        $this->assertFalse($change['schedule_changed']);
    }

    public function testTeamSwapReportsAddedRemovedAndKeptTeams(): void
    {
        $change = scheduleChangeSet($this->current, [
            'date'              => '2026-06-15',
            'time'              => '08:00',
            'venue'             => 'Main Gym',
            'team_a_id'         => 5,
            'team_b_id'         => 9,
            'committee_user_id' => 3,
        ]);

        $this->assertTrue($change['teams_changed']);
        $this->assertSame(5, $change['final_team_a']);
        $this->assertSame(9, $change['final_team_b']);
        $this->assertSame([9], $change['added_teams']);
        $this->assertSame([6], $change['removed_teams']);
        $this->assertSame([5], $change['kept_teams']);
        $this->assertTrue($change['schedule_changed']);
    }

    public function testEmptyPostedDateKeepsTheStoredValue(): void
    {
        $change = scheduleChangeSet($this->current, [
            'date'              => '',
            'time'              => '',
            'venue'             => 'Main Gym',
            'team_a_id'         => 0,
            'team_b_id'         => 0,
            'committee_user_id' => 3,
        ]);

        $this->assertSame('2026-06-15', $change['new_date']);
        $this->assertSame('08:00:00', $change['new_time']);
        $this->assertFalse($change['schedule_changed']);
    }
}
