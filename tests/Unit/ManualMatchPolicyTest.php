<?php
/**
 * Tests for the manual-fixture policy of stage formats.
 * Located in: includes/stageHelper.php
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stageHelper.php';

class ManualMatchPolicyTest extends TestCase
{
    public function testTableStagesNeedAGroup(): void
    {
        foreach (['group', 'crossover'] as $type) {
            $this->assertStringContainsString(
                'stage group',
                (string) stageManualMatchError($type, 'round_robin', false, false, null),
                "$type matches must be assigned to one stage group"
            );
            $this->assertNull(
                stageManualMatchError($type, 'round_robin', false, false, 7),
                "$type matches become legal once a group is selected"
            );
        }
    }

    public function testPlacementMatchesAreEngineManaged(): void
    {
        $this->assertStringContainsString(
            'Placement',
            (string) stageManualMatchError('placement', 'placement', false, false, null)
        );
    }

    public function testDoubleEliminationRejectsManualRows(): void
    {
        $this->assertStringContainsString(
            'losers bracket',
            (string) stageManualMatchError('knockout', 'double_elimination', false, false, null)
        );
    }

    public function testGraphAndGeneratedBracketsRejectManualRows(): void
    {
        $this->assertStringContainsString(
            'tournament graph',
            (string) stageManualMatchError('knockout', 'single_elimination', true, true, null)
        );
        $this->assertStringContainsString(
            'generated fixtures',
            (string) stageManualMatchError('knockout', 'single_elimination', false, true, null)
        );
    }

    public function testTwoTeamFinalPassesTheStagePolicy(): void
    {
        $this->assertNull(
            stageManualMatchError('knockout', 'single_elimination', false, false, null, 2),
            'A standalone two-team final is safe to enter manually'
        );
        $this->assertNull(
            stageManualMatchError('knockout', 'single_elimination', false, false, 3, 2),
            'A two-team knockout pool can carry a manually entered final'
        );
        $this->assertStringContainsString(
            'more than two teams',
            (string) stageManualMatchError('knockout', 'single_elimination', false, false, null, 4),
            'A multi-team bracket must come from the generator so its rounds advance'
        );
    }
}
