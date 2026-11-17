<?php
session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../includes/schedulingHelper.php';
require_once __DIR__ . '/../../../includes/notificationHelper.php';
require_once __DIR__ . '/../../../includes/tournamentProgression.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../includes/teamCorrectionDeadline.php';
require_once __DIR__ . '/../../../config/sportRules.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
ensureStageMatchDraftColumns($conn);
enforceRequestSizeLimit(5 * 1024 * 1024); // 5MB
addApiSecurityHeaders();

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

function sendResponse($status, $msg, $data = null) {
    $response = ["status" => $status, "msg" => $msg];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
}

function activeCommittee(mysqli $conn, int $committeeId): ?array {
    $stmt = $conn->prepare("SELECT id,fname,lname FROM tbl_user WHERE id=? AND role='4' AND stat='1' LIMIT 1");
    $stmt->bind_param('i', $committeeId); $stmt->execute();
    $committee = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $committee ?: null;
}

if (!function_exists('isActiveUtilityVenue')) {
function isActiveUtilityVenue(mysqli $conn, ?string $venue): bool {
    $venue = trim((string)$venue);
    if ($venue === '') return true;
    $stmt = $conn->prepare("SELECT 1 FROM tbl_utility WHERE category='venue' AND is_active=1 AND name=? LIMIT 1");
    $stmt->bind_param('s', $venue); $stmt->execute();
    $valid = (bool)$stmt->get_result()->fetch_row(); $stmt->close();
    return $valid;
}
}

function committeeHasConflict(mysqli $conn, int $committeeId, string $date, string $time, int $excludeScheduleId = 0): bool {
    $stmt = $conn->prepare("SELECT id FROM tbl_schedule WHERE committee_user_id=? AND date=? AND time=? AND id!=? AND LOWER(status) NOT IN ('cancelled','completed') AND (is_archived=0 OR is_archived IS NULL) LIMIT 1");
    $stmt->bind_param('issi', $committeeId, $date, $time, $excludeScheduleId); $stmt->execute();
    $conflict = (bool)$stmt->get_result()->fetch_row(); $stmt->close();
    return $conflict;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(400, "Invalid request");
    return;
}

// Validate CSRF token for all scheduling mutations
requireJsonCsrf();
$userId = currentUserId();
// Resolve the league id once: some handlers (protest logging / decisions) rely
// on this value instead of re-reading the POST payload.
$leagueId = (int)($_POST['league_id'] ?? 0);

// Every schedule operation is scoped to the authenticated Organizer's league.
if ($leagueId > 0 && !organizerOwnsLeague($conn, $leagueId)) {
    sendResponse(403, 'You may manage schedules only for your own active leagues.');
    return;
}
if (isset($_POST['schedule_id']) && !organizerOwnsSchedule($conn, (int)$_POST['schedule_id'])) {
    sendResponse(403, 'You may manage matches only in your own leagues.');
    return;
}
if (isset($_POST['team_id']) && !organizerOwnsTeam($conn, (int)$_POST['team_id'])) {
    sendResponse(403, 'You may view matches only for teams in your own leagues.');
    return;
}
// Protest decisions may be submitted without a league_id; resolve ownership
// through the protest record itself so it cannot cross organizer boundaries.
// An empty optional protest reference (0) is ignored rather than rejected.
$protestIdParam = (int)($_POST['protest_id'] ?? 0);
if ($protestIdParam > 0) {
    $protestOwnerStmt = $conn->prepare(
        "SELECT 1 FROM tbl_result_protest p JOIN tbl_league l ON l.id = p.league_id WHERE p.id = ? AND l.created_by = ? LIMIT 1"
    );
    $protestOwnerStmt->bind_param('ii', $protestIdParam, $userId);
    $protestOwnerStmt->execute();
    $ownsProtest = (bool)$protestOwnerStmt->get_result()->fetch_row();
    $protestOwnerStmt->close();
    if (!$ownsProtest) {
        sendResponse(403, 'You may decide protests only in your own leagues.');
        return;
    }
}

    // Auto-generate a round-robin schedule (leagues without stages)
    if (isset($_POST['autoSchedule'])) {
        $leagueId = intval($_POST['league_id']);
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $venue = $_POST['venue'] ?? null;
        if (!isActiveUtilityVenue($conn, $venue)) { sendResponse(400, 'Select an active venue from Utilities.'); return; }

        // Verify prerequisites
        $lgStmt = $conn->prepare("SELECT stat, regis_end, max_team, registration_window_open FROM tbl_league WHERE id = ?");
        $lgStmt->bind_param('i', $leagueId);
        $lgStmt->execute();
        $lg = $lgStmt->get_result()->fetch_assoc();
        $lgStmt->close();

        if (!$lg) {
            sendResponse(404, "League not found");
            return;
        }
        if (leagueHasOpenCorrections($conn, $leagueId)) {
            sendResponse(409, 'Schedule generation is locked while a returned application is still within its correction period.');
            return;
        }

        $approvedCountStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
        $approvedCountStmt->bind_param('i', $leagueId);
        $approvedCountStmt->execute();
        $approvedCount = $approvedCountStmt->get_result()->fetch_assoc()['cnt'];
        $approvedCountStmt->close();

        $isSlotsFilled       = $approvedCount >= intval($lg['max_team']);
        $isRegDatePassed     = date('Y-m-d') > $lg['regis_end'];
        $isRegManuallyClosed = intval($lg['registration_window_open']) === 0;

        if (!$isSlotsFilled && !$isRegDatePassed && !$isRegManuallyClosed) {
            sendResponse(400, "Schedule generation is locked. Fill all team slots, wait for registration to end, or manually close registration.");
            return;
        }
        if (strtolower($lg['stat']) !== 'ongoing') {
            sendResponse(400, "League must be Ongoing to generate a schedule.");
            return;
        }

        $teams = getApprovedTeams($conn, $leagueId);
        if (count($teams) < 2) {
            sendResponse(400, "At least 2 approved teams required for scheduling");
            return;
        }

        $matches = generateRoundRobinMatches($teams);
        if (isset($matches['error'])) {
            sendResponse(400, $matches['error']);
            return;
        }

        // Auto-schedule with conflict detection
        $scheduledMatches = autoScheduleMatches($conn, $leagueId, $matches, $startDate, $venue);
        // Save to database
        $result = saveScheduledMatches($conn, $leagueId, $scheduledMatches, true);

        if (isset($result['error'])) {
            sendResponse(500, "Failed to save generated schedule: " . $result['error']);
            return;
        }

        foreach ($result['schedule_ids'] ?? [] as $scheduleId) {
            auditLog($conn, 'Create Match Draft', "Generated round robin match draft ID $scheduleId");
        }

        sendResponse(201, "Round robin match drafts generated successfully", [
            "saved" => $result['saved'],
            "conflicts" => $result['conflicts'],
            "total" => $result['total'],
            "matches" => $scheduledMatches
        ]);
        return;
    }

    // Get league schedule
    if (isset($_POST['getSchedule'])) {
        $leagueId = intval($_POST['league_id']);
        $status = $_POST['status'] ?? null;

        // The organizer board lists the auto-completed BYE rows of a bracket as
        // well, so every generated slot is visible; match counters ignore them.
        $schedule = getLeagueSchedule($conn, $leagueId, $status, false, true);

        sendResponse(200, "Schedule retrieved", ["schedule" => $schedule]);
        return;
    }

    if (isset($_POST['publishMatch'])) {
        $scheduleId=(int)$_POST['schedule_id'];
        $stmt=$conn->prepare("SELECT s.*,l.league_name,ta.team_name AS team_a_name,tb.team_name AS team_b_name FROM tbl_schedule s JOIN tbl_league l ON l.id=s.league_id JOIN tbl_team ta ON ta.id=s.team_a JOIN tbl_team tb ON tb.id=s.team_b WHERE s.id=? FOR UPDATE");
        $conn->begin_transaction(); $stmt->bind_param('i',$scheduleId); $stmt->execute(); $match=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if(!$match){ $conn->rollback(); sendResponse(404,'Match not found.'); return; }
        if($match['published_at']){ $conn->rollback(); sendResponse(200,'Match is already published.'); return; }
        $eligibleStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_team WHERE id IN (?,?) AND league_id=? AND LOWER(status)='approved' AND progression_status<>'Eliminated'");
        $eligibleStmt->bind_param('iii',$match['team_a'],$match['team_b'],$match['league_id']); $eligibleStmt->execute(); $eligibleCount=(int)$eligibleStmt->get_result()->fetch_assoc()['c']; $eligibleStmt->close();
        if($eligibleCount!==2){ $conn->rollback(); sendResponse(422,'This Draft includes an eliminated or ineligible team and cannot be published.'); return; }
        if(!$match['date'] || !$match['time'] || !trim((string)$match['venue']) || !(int)$match['committee_user_id']) { $conn->rollback(); sendResponse(422,'Set the date, time, venue, and Committee before publishing.'); return; }
        if(!activeCommittee($conn,(int)$match['committee_user_id'])){ $conn->rollback(); sendResponse(422,'The assigned Committee is inactive.'); return; }
        $conflicts=array_merge(checkScheduleConflicts($conn,(int)$match['team_a'],$match['date'],$match['time'],60,$scheduleId),checkScheduleConflicts($conn,(int)$match['team_b'],$match['date'],$match['time'],60,$scheduleId),checkVenueConflicts($conn,$match['venue'],$match['date'],$match['time'],60,$scheduleId));
        if(committeeHasConflict($conn,(int)$match['committee_user_id'],$match['date'],$match['time'],$scheduleId)) $conflicts[]='Committee conflict';
        if($conflicts){ $conn->rollback(); sendResponse(409,'Resolve scheduling conflicts before publishing.',['conflicts'=>$conflicts]); return; }
        $up=$conn->prepare("UPDATE tbl_schedule SET published_at=NOW(),status='Scheduled',conflict_detected=0,conflict_reason=NULL WHERE id=? AND published_at IS NULL"); $up->bind_param('i',$scheduleId); $up->execute();
        if($up->affected_rows!==1){ $up->close(); $conn->rollback(); sendResponse(409,'Match publication state changed. Refresh and try again.'); return; } $up->close();
        $message="Match published: {$match['team_a_name']} vs {$match['team_b_name']} on {$match['date']} at {$match['time']}";
        // Delivery is best effort: a notification must never roll back the
        // publication the organizer already committed to.
        try {
            notifyScheduleUpdate($conn,(int)$match['league_id'],$scheduleId,$message);
            createNotification($conn,(int)$match['committee_user_id'],'committee_assignment','Match Assignment',$message,$scheduleId);
        } catch (Throwable $e) {
            error_log('[notification] Match publication notice failed for schedule ' . $scheduleId . ': ' . $e->getMessage());
        }
        auditLog($conn,'Publish Match Schedule',"Published match ID $scheduleId and assigned Committee user ID {$match['committee_user_id']}");
        $conn->commit(); sendResponse(201,'Match schedule published successfully.'); return;
    }

    // Create manual match
    if (isset($_POST['createMatch'])) {
        $leagueId = intval($_POST['league_id']);

        // Verify prerequisites
        $lgStmt = $conn->prepare("SELECT stat, regis_end, max_team, registration_window_open FROM tbl_league WHERE id = ?");
        $lgStmt->bind_param('i', $leagueId);
        $lgStmt->execute();
        $lg = $lgStmt->get_result()->fetch_assoc();
        $lgStmt->close();

        if (!$lg) {
            sendResponse(404, "League not found");
            return;
        }
        if (leagueHasOpenCorrections($conn, $leagueId)) {
            sendResponse(409, 'Schedule creation is locked while a returned application is still within its correction period.');
            return;
        }

        $approvedCountStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
        $approvedCountStmt->bind_param('i', $leagueId);
        $approvedCountStmt->execute();
        $approvedCount = $approvedCountStmt->get_result()->fetch_assoc()['cnt'];
        $approvedCountStmt->close();

        $isSlotsFilled       = $approvedCount >= intval($lg['max_team']);
        $isRegDatePassed     = date('Y-m-d') > $lg['regis_end'];
        $isRegManuallyClosed = intval($lg['registration_window_open']) === 0;

        if (!$isSlotsFilled && !$isRegDatePassed && !$isRegManuallyClosed) {
            sendResponse(400, "Schedule creation is locked. Fill all team slots, wait for registration to end, or manually close registration.");
            return;
        }

        $teamAId = intval($_POST['team_a_id']);
        $teamBId = intval($_POST['team_b_id']);
        $date = $_POST['date'];
        $time = $_POST['time'];
        $venue = $_POST['venue'] ?? null;
        if (!isActiveUtilityVenue($conn, $venue)) { sendResponse(400, 'Select an active venue from Utilities.'); return; }
        $committeeUserId = intval($_POST['committee_user_id'] ?? 0);
        $leagueGroupId = intval($_POST['league_group_id'] ?? 0);
        $round = intval($_POST['round'] ?? 1);
        
        // Validate teams are different
        if ($teamAId === $teamBId) {
            sendResponse(400, "Teams must be different");
            return;
        }
        $teamStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_team WHERE id IN (?,?) AND league_id=? AND LOWER(status)='approved' AND progression_status<>'Eliminated'");
        $teamStmt->bind_param('iii',$teamAId,$teamBId,$leagueId); $teamStmt->execute(); $validTeams=(int)$teamStmt->get_result()->fetch_assoc()['c']; $teamStmt->close();
        if($validTeams!==2){ sendResponse(400,'Select two approved, non-eliminated teams from this league.'); return; }
        $groupDbId=null;
        if($leagueGroupId>0){
            $groupStmt=$conn->prepare("SELECT 1 FROM tbl_league_group g JOIN tbl_league_group_team ga ON ga.group_id=g.id AND ga.team_id=? JOIN tbl_league_group_team gb ON gb.group_id=g.id AND gb.team_id=? WHERE g.id=? AND g.league_id=?");
            $groupStmt->bind_param('iiii',$teamAId,$teamBId,$leagueGroupId,$leagueId); $groupStmt->execute(); $validGroup=(bool)$groupStmt->get_result()->fetch_row(); $groupStmt->close();
            if(!$validGroup){ sendResponse(400,'Both teams must be assigned to the selected group.'); return; }
            $groupDbId=$leagueGroupId;
        }
        if ($committeeUserId > 0 && !activeCommittee($conn,$committeeUserId)) {
            sendResponse(400, 'The selected Committee is not active.');
            return;
        }
        
        // Check for conflicts
        $teamConflicts = checkScheduleConflicts($conn, $teamAId, $date, $time);
        $teamBConflicts = checkScheduleConflicts($conn, $teamBId, $date, $time);
        $venueConflicts = $venue ? checkVenueConflicts($conn, $venue, $date, $time) : [];
        $committeeConflicts = [];
        if ($committeeUserId > 0 && committeeHasConflict($conn,$committeeUserId,$date,$time)) $committeeConflicts[] = 'Committee is assigned to another match at this date and time.';
        
        $allConflicts = array_merge($teamConflicts, $teamBConflicts, $venueConflicts, $committeeConflicts);
        
        $status = 'Draft';
        $conflictReason = count($allConflicts) > 0 ? json_encode($allConflicts) : null;
        
        // Optional stage / bracket tagging (ANP2026 format). The stage is derived
        // from the chosen stage group so the two can never disagree.
        $stageGroupInput = intval($_POST['stage_group_id'] ?? 0);
        $stageId = null; $stageGroupId = null; $stageTag = null; $bracketTag = null;
        if ($stageGroupInput > 0) {
            $sgStmt = $conn->prepare("SELECT s.id AS stage_id, s.stage_type, s.league_id
                                      FROM tbl_league_stage_group g JOIN tbl_league_stage s ON s.id = g.stage_id
                                      WHERE g.id = ? LIMIT 1");
            $sgStmt->bind_param('i', $stageGroupInput); $sgStmt->execute();
            $sgRow = $sgStmt->get_result()->fetch_assoc(); $sgStmt->close();
            if (!$sgRow || (int)$sgRow['league_id'] !== $leagueId) {
                sendResponse(400, 'The selected stage group does not belong to this league.');
                return;
            }
            $stageId      = (int)$sgRow['stage_id'];
            $stageGroupId = $stageGroupInput;
            $stageTag     = $sgRow['stage_type'];
            $bracketTag   = in_array($sgRow['stage_type'], ['knockout', 'placement'], true) ? 'knockout' : null;
        }

        $sql = "INSERT INTO tbl_schedule (league_id, league_group_id, stage_id, stage_group_id, team_a, team_b, date, time, venue, round, status, is_automated, conflict_detected, conflict_reason, committee_user_id, stage, bracket)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            sendResponse(500, "Database error: " . $conn->error);
            return;
        }
        
        $isAutomated = 0;
        $hasConflict = count($allConflicts) > 0 ? 1 : 0;
        
        $committeeDbId = $committeeUserId > 0 ? $committeeUserId : null;
        $stmt->bind_param(
            "iiiiiisssisiisiss",
            $leagueId,
            $groupDbId,
            $stageId,
            $stageGroupId,
            $teamAId,
            $teamBId,
            $date,
            $time,
            $venue,
            $round,
            $status,
            $isAutomated,
            $hasConflict,
            $conflictReason,
            $committeeDbId,
            $stageTag,
            $bracketTag
        );
        
        if ($stmt->execute()) {
            $scheduleId = $stmt->insert_id;
            $stmt->close();
            
            auditLog($conn, 'Create Match Draft', "Created match draft ID $scheduleId in league ID $leagueId");
            
            sendResponse(201, "Match saved as Draft", [
                "schedule_id" => $scheduleId,
                "conflicts" => $allConflicts,
                "has_conflict" => count($allConflicts) > 0
            ]);
        } else {
            sendResponse(500, "Failed to create match");
        }
        return;
    }

    // Update match status
    if (isset($_POST['updateMatchStatus'])) {
        $scheduleId = intval($_POST['schedule_id']);
        $status = $_POST['status'];
        
        $sql = "UPDATE tbl_schedule SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            sendResponse(500, "Database error: " . $conn->error);
            return;
        }
        
        $stmt->bind_param("si", $status, $scheduleId);
        
        if ($stmt->execute()) {
            $stmt->close();
            sendResponse(200, "Match status updated successfully");
        } else {
            sendResponse(500, "Failed to update match status");
        }
        return;
    }

    // Update match
    if (isset($_POST['updateMatch'])) {
        $scheduleId = intval($_POST['schedule_id']);
        $teamAId = intval($_POST['team_a_id'] ?? 0);
        $teamBId = intval($_POST['team_b_id'] ?? 0);
        $date = $_POST['date'] ?? null;
        $time = $_POST['time'] ?? null;
        $venue = $_POST['venue'] ?? null;
        if ($venue !== null && !isActiveUtilityVenue($conn, $venue)) { sendResponse(400, 'Select an active venue from Utilities.'); return; }
        $committeeUserId = intval($_POST['committee_user_id'] ?? 0);

        if ($committeeUserId > 0 && !activeCommittee($conn, $committeeUserId)) {
            sendResponse(400, 'The selected Committee is not active.');
            return;
        }

        $statusStmt = $conn->prepare("SELECT status, date, time, committee_user_id, published_at FROM tbl_schedule WHERE id = ?");
        if (!$statusStmt) {
            sendResponse(500, "Database error: " . $conn->error);
            return;
        }

        $statusStmt->bind_param("i", $scheduleId);
        $statusStmt->execute();
        $statusRow = $statusStmt->get_result()->fetch_assoc();
        $statusStmt->close();

        if (!$statusRow) {
            sendResponse(404, "Match not found");
            return;
        }

        if (strtolower($statusRow['status']) === 'completed') {
            sendResponse(400, "Completed matches cannot be rescheduled.");
            return;
        }

        $effectiveDate = $date ?: $statusRow['date'];
        $effectiveTime = $time ?: $statusRow['time'];
        if ($committeeUserId > 0 && committeeHasConflict($conn, $committeeUserId, $effectiveDate, $effectiveTime, $scheduleId)) {
            sendResponse(409, 'The selected Committee is already assigned to another match at this date and time.');
            return;
        }
        
        $updates = [];
        $params = [];
        $types = "";
        
        if ($teamAId > 0) {
            $updates[] = "team_a = ?";
            $params[] = $teamAId;
            $types .= "i";
        }
        
        if ($teamBId > 0) {
            $updates[] = "team_b = ?";
            $params[] = $teamBId;
            $types .= "i";
        }

        if ($teamAId > 0 || $teamBId > 0) {
            $candidateIds=array_values(array_filter([$teamAId,$teamBId],fn($teamId)=>$teamId>0));
            foreach($candidateIds as $candidateId){
                $teamCheck=$conn->prepare("SELECT 1 FROM tbl_team t JOIN tbl_schedule s ON s.league_id=t.league_id WHERE t.id=? AND s.id=? AND LOWER(t.status)='approved' AND t.progression_status<>'Eliminated'");
                $teamCheck->bind_param('ii',$candidateId,$scheduleId); $teamCheck->execute(); $validCandidate=(bool)$teamCheck->get_result()->fetch_row(); $teamCheck->close();
                if(!$validCandidate){ sendResponse(400,'An eliminated or invalid team cannot be assigned to this match.'); return; }
            }
        }
        
        if ($date) {
            $updates[] = "date = ?";
            $params[] = $date;
            $types .= "s";
        }
        
        if ($time) {
            $updates[] = "time = ?";
            $params[] = $time;
            $types .= "s";
        }
        
        if ($venue !== null) {
            $updates[] = "venue = ?";
            $params[] = $venue;
            $types .= "s";
        }


        $updates[] = "committee_user_id = ?";
        $params[] = $committeeUserId > 0 ? $committeeUserId : null;
        $types .= "i";
        
        if (empty($updates)) {
            sendResponse(400, "No fields to update");
            return;
        }
        
        $params[] = $scheduleId;
        $types .= "i";
        
        $sql = "UPDATE tbl_schedule SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            sendResponse(500, "Database error: " . $conn->error);
            return;
        }
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $stmt->close();
            if ($statusRow['published_at'] && $committeeUserId > 0 && (int)$statusRow['committee_user_id'] !== $committeeUserId) {
                createNotification($conn, $committeeUserId, 'committee_assignment', 'Match Assignment', 'You were assigned to an updated match schedule.', $scheduleId);
                auditLog($conn, 'Assign Committee', "Assigned Committee user ID $committeeUserId to match ID $scheduleId");
            }
            // Notify the two teams involved that their match was rescheduled
            if ($statusRow['published_at'] && ($date || $time || $venue !== null)) {
                $rStmt = $conn->prepare("SELECT team_a, team_b, date, time, venue FROM tbl_schedule WHERE id = ?");
                $rStmt->bind_param('i', $scheduleId);
                $rStmt->execute();
                $rRow = $rStmt->get_result()->fetch_assoc();
                $rStmt->close();
                if ($rRow) {
                    notifyMatchRescheduled($conn, $scheduleId, $rRow['team_a'], $rRow['team_b'], $rRow['date'], $rRow['time'], $rRow['venue']);
                }
            }
            auditLog($conn, 'Update Match Schedule', "Updated match ID $scheduleId");
            sendResponse(200, "Match updated successfully");
        } else {
            sendResponse(500, "Failed to update match");
        }
        return;
    }

    // Official result entry belongs exclusively to the assigned Committee.
    if (isset($_POST['updateResult'])) {
        sendResponse(403, "Only the Committee assigned to this match may submit its official result.");
        return;
    }

    // Resolve conflict
    if (isset($_POST['resolveConflict'])) {
        $scheduleId = intval($_POST['schedule_id']);
        $newDate = $_POST['new_date'];
        $newTime = $_POST['new_time'];
        $newVenue = $_POST['new_venue'] ?? null;
        
        if (resolveConflict($conn, $scheduleId, $newDate, $newTime, $newVenue)) {
            sendResponse(200, "Conflict resolved successfully");
        } else {
            sendResponse(500, "Failed to resolve conflict");
        }
        return;
    }

    // Archive a match so its history remains recoverable.
    if (isset($_POST['deleteMatch'])) {
        $scheduleId = intval($_POST['schedule_id']);
        if (!organizerOwnsSchedule($conn, $scheduleId)) {
            sendResponse(403, "You may archive matches only in your own leagues.");
            return;
        }
        $sql = "UPDATE tbl_schedule SET is_archived=1, archived_at=NOW(), status='Cancelled' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            sendResponse(500, "Database error: " . $conn->error);
            return;
        }
        
        $stmt->bind_param("i", $scheduleId);
        
        if ($stmt->execute()) {
            $stmt->close();
            sendResponse(200, "Match deleted successfully");
        } else {
            sendResponse(500, "Failed to delete match");
        }
        return;
    }

    // Get team upcoming matches
    if (isset($_POST['getTeamMatches'])) {
        $teamId = intval($_POST['team_id']);
        $limit = intval($_POST['limit'] ?? 5);
        
        $matches = getTeamUpcomingMatches($conn, $teamId, $limit);
        
        sendResponse(200, "Team matches retrieved", ["matches" => $matches]);
        return;
    }

    // Preview the seeded matches of a stage (crossover / QF / full playoffs)
    if (isset($_POST['previewStageMatches'])) {
        $leagueId = intval($_POST['league_id']);
        $stageId  = intval($_POST['stage_id']);
        $pattern  = (string)($_POST['pattern'] ?? '');
        if (!array_key_exists($pattern, stagePatternLabels())) { sendResponse(400, 'Unknown draw pattern.'); return; }
        $stage = getStage($conn, $stageId);
        if (!$stage || (int)$stage['league_id'] !== $leagueId) { sendResponse(404, 'Stage not found for this league.'); return; }

        [$matched, $errors] = previewStageMatches($conn, $leagueId, $stageId, $pattern);
        $rows = array_map(function (array $m) use ($conn) {
            $group = !empty($m['stage_group_id']) ? getStageGroup($conn, (int)$m['stage_group_id']) : null;
            return [
                'label'   => $m['label'],
                'seed_a'  => $m['seed_a'],
                'seed_b'  => $m['seed_b'],
                'team_a'  => $m['team_a_name'],
                'team_b'  => $m['team_b_name'],
                'group'   => $group['name'] ?? ($m['placement'] ?? false ? 'Battle for Third' : ''),
                'round'   => (int)$m['round'],
            ];
        }, $matched);

        sendResponse(200, 'Draw preview ready', ['matches' => $rows, 'errors' => $errors, 'total' => count($rows)]);
        return;
    }

    // Generate the seeded matches of a stage
    if (isset($_POST['generateStageMatches'])) {
        $leagueId = intval($_POST['league_id']);
        $stageId  = intval($_POST['stage_id']);
        $pattern  = (string)($_POST['pattern'] ?? '');
        if (!array_key_exists($pattern, stagePatternLabels())) { sendResponse(400, 'Unknown draw pattern.'); return; }
        $stage = getStage($conn, $stageId);
        if (!$stage || (int)$stage['league_id'] !== $leagueId) { sendResponse(404, 'Stage not found for this league.'); return; }

        /* A double-elimination stage always needs the graph engine: the legacy
           patterns only know single-elimination brackets. */
        if (stageFormat($stage) === 'double_elimination') {
            $teams  = stageSeededTeamIds($conn, $leagueId, $stageId);
            $result = createBracketForStage($conn, $leagueId, $stageId, $teams, 'double_elimination');
            if (!empty($result['errors'])) { sendResponse(400, implode(' ', $result['errors'])); return; }
            auditLog($conn, 'Generate Stage Bracket', "Generated a double-elimination bracket ({$result['created']} matches) for stage \"{$stage['name']}\"");
            sendResponse(201, "{$result['created']} bracket match(es) generated for {$stage['name']} (winners + losers + grand final).", [
                'created'   => $result['created'],
                'byes'      => $result['byes'],
                'match_ids' => $result['ids'],
            ]);
            return;
        }

        // The structure presets create their stage groups empty; seed the first
        // stage with the approved teams so the first draw needs no manual work.
        if ($pattern === 'round_robin_groups') {
            seedStageGroupsFromApprovedTeams($conn, $leagueId, $stageId);
        }

        $result = generateStageMatches($conn, $leagueId, $stageId, $pattern);
        if (!empty($result['errors'])) { sendResponse(400, implode(' ', $result['errors'])); return; }
        if ((int)$result['created'] === 0) { sendResponse(400, 'Nothing to generate — check the stage standings first.'); return; }

        auditLog($conn, 'Generate Stage Bracket', "Generated {$result['created']} {$pattern} match(es) for stage \"{$stage['name']}\" in league ID $leagueId");
        sendResponse(201, "{$result['created']} match(es) generated for {$stage['name']} as Drafts with no date, venue or Committee. Set those on Schedule, then publish.", [
            'created'   => $result['created'],
            'match_ids' => $result['ids'],
        ]);
        return;
    }

    /* ---------------------------------------------------------------------
     * Format engine (single & double elimination brackets, Swiss rounds)
     * ------------------------------------------------------------------ */

    // Draw the whole bracket graph of a knockout stage (winners + losers + final).
    if (isset($_POST['generateFormatBracket'])) {
        $stageId = intval($_POST['stage_id'] ?? 0);
        $stage   = getStage($conn, $stageId);
        if (!$stage || (int)$stage['league_id'] !== $leagueId) { sendResponse(404, 'Stage not found for this league.'); return; }

        $format = stageFormat($stage);
        if (!stageFormatUsesBracket($format)) { sendResponse(400, 'This stage is not a bracket stage.'); return; }

        $existing = 0;
        $check = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id = ? AND stage_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
        if ($check) {
            $check->bind_param('ii', $leagueId, $stageId);
            $check->execute();
            $existing = (int)($check->get_result()->fetch_assoc()['c'] ?? 0);
            $check->close();
        }
        if ($existing > 0) { sendResponse(409, 'This stage already has fixtures. Archive them before drawing the bracket again.'); return; }

        $teams = stageSeededTeamIds($conn, $leagueId, $stageId);
        $result = createBracketForStage($conn, $leagueId, $stageId, $teams, $format);
        if (!empty($result['errors'])) { sendResponse(400, implode(' ', $result['errors'])); return; }

        auditLog($conn, 'Generate Bracket', "Drew a $format bracket ({$result['created']} matches) for stage \"{$stage['name']}\" in league ID $leagueId");
        sendResponse(201, "{$result['created']} bracket match(es) generated for {$stage['name']} ({$result['byes']} bye row/s resolved).", [
            'created'   => $result['created'],
            'byes'      => $result['byes'],
            'match_ids' => $result['ids'],
        ]);
        return;
    }

    // Generate the next Swiss round (locks the previous one).
    if (isset($_POST['generateSwissRound'])) {
        $stageId = intval($_POST['stage_id'] ?? 0);
        $stage   = getStage($conn, $stageId);
        if (!$stage || (int)$stage['league_id'] !== $leagueId) { sendResponse(404, 'Stage not found for this league.'); return; }
        if (stageFormat($stage) !== 'swiss') { sendResponse(400, 'This stage is not a Swiss stage.'); return; }

        $roundNo = isset($_POST['round']) ? (int)$_POST['round'] : null;
        $result  = generateSwissRoundForStage($conn, $leagueId, $stageId, $roundNo);
        if (!empty($result['errors'])) { sendResponse(409, implode(' ', $result['errors'])); return; }

        auditLog($conn, 'Generate Swiss Round', "Generated Swiss round {$result['round']} ({$result['created']} fixtures) for stage \"{$stage['name']}\"");
        sendResponse(201, "Swiss round {$result['round']} generated — {$result['created']} fixture(s).", [
            'round'   => $result['round'],
            'created' => $result['created'],
            'bye'     => $result['bye'],
            'pairs'   => $result['pairs'],
        ]);
        return;
    }

    // Lock one stage round (closes it for score entry / regeneration).
    if (isset($_POST['lockStageRound'])) {
        $stageId = intval($_POST['stage_id'] ?? 0);
        $round   = intval($_POST['round'] ?? 0);
        $stage   = getStage($conn, $stageId);
        if (!$stage || (int)$stage['league_id'] !== $leagueId) { sendResponse(404, 'Stage not found for this league.'); return; }
        if ($round <= 0) { sendResponse(400, 'Choose the round to lock.'); return; }

        $locked = lockStageRound($conn, $leagueId, $stageId, $round);
        auditLog($conn, 'Lock Stage Round', "Locked round $round of stage \"{$stage['name']}\" ($locked row/s)");
        sendResponse(200, $locked > 0 ? "$locked match(es) locked." : 'That round was already locked.', ['locked' => $locked]);
        return;
    }


    // Log a protest / reopen a completed result (Volleyball §7.2: 30 minutes;
    // ANP2026 basketball guidelines: 24 hours — configured per sport profile)
    if (isset($_POST['protestResult'])) {
        $scheduleId = intval($_POST['schedule_id'] ?? 0);
        $reason     = trim((string)($_POST['reason'] ?? ''));
        $party      = (string)($_POST['party'] ?? 'team');
        $decision   = (string)($_POST['decision'] ?? 'Open');

        if ($reason === '') { sendResponse(400, 'A protest reason is required.'); return; }
        if (mb_strlen($reason) > 1000) $reason = mb_substr($reason, 0, 1000);
        if (!in_array($party, ['team', 'committee', 'organizer'], true)) $party = 'team';
        if (!in_array($decision, ['Open', 'Upheld', 'Denied'], true)) $decision = 'Open';

        $mStmt = $conn->prepare("SELECT id, status, published_at, updated_at FROM tbl_schedule WHERE id = ? AND league_id = ? LIMIT 1");
        $mStmt->bind_param('ii', $scheduleId, $leagueId);
        $mStmt->execute();
        $mrow = $mStmt->get_result()->fetch_assoc();
        $mStmt->close();
        if (!$mrow) { sendResponse(404, 'Match not found in this league.'); return; }
        if (strtolower((string)$mrow['status']) !== 'completed') { sendResponse(409, 'Only a completed match can be protested.'); return; }

        // The manual sets the filing deadline (30 minutes for Volleyball §7.2,
        // 24 hours for the ANP2026 basketball guidelines); only the organizer
        // may act after the window closed.
        $protestProfile = function_exists('getLeagueSportProfile') ? getLeagueSportProfile($conn, $leagueId) : null;
        $windowMinutes  = (int)($protestProfile['protest_window_minutes'] ?? 1440);
        if ($windowMinutes <= 0) $windowMinutes = 1440;
        $windowLabel = $windowMinutes >= 1440
            ? round($windowMinutes / 1440, 2) . '-day'
            : $windowMinutes . '-minute';
        $resultAt = $mrow['published_at'] ?: $mrow['updated_at'];
        $withinWindow = $resultAt ? (strtotime((string)$resultAt) >= time() - ($windowMinutes * 60)) : true;
        if (!$withinWindow && $party !== 'organizer') {
            sendResponse(409, 'The ' . $windowLabel . ' protest window has closed. Only the organizer may reopen this result.');
            return;
        }

        $conn->begin_transaction();
        $decidedBy    = $decision === 'Open' ? null : $userId;
        $decidedAt    = $decision === 'Open' ? null : date('Y-m-d H:i:s');
        $decisionNote = $decision === 'Open' ? null : ($party === 'organizer' ? 'Reopened by the organizer' : 'Decision recorded by the organizer');

        $ins = $conn->prepare("INSERT INTO tbl_result_protest (schedule_id, league_id, raised_by, party, reason, status, decided_by, decided_at, decision_note) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->bind_param('iiisssiss', $scheduleId, $leagueId, $userId, $party, $reason, $decision, $decidedBy, $decidedAt, $decisionNote);
        $ins->execute();
        $protestId = (int)$conn->insert_id;
        $ins->close();

        $reversal = ['teams' => [], 'removed_matches' => []];
        if ($decision === 'Upheld') {
            $reversal = reverseProgression($conn, $leagueId, $scheduleId);
            $upd = $conn->prepare("UPDATE tbl_schedule SET status='Scheduled', winner=0, result_submitted_by=NULL, result_status='UnderReview', result_reopened_by=?, result_reopened_at=NOW() WHERE id=? AND league_id=?");
            $upd->bind_param('iii', $userId, $scheduleId, $leagueId);
            $upd->execute();
            $upd->close();
        } else {
            $newStatus = $decision === 'Denied' ? 'Final' : 'Protested';
            $upd = $conn->prepare("UPDATE tbl_schedule SET result_status=? WHERE id=? AND league_id=?");
            $upd->bind_param('sii', $newStatus, $scheduleId, $leagueId);
            $upd->execute();
            $upd->close();
        }
        $conn->commit();

        auditLog($conn, 'Result Protest', "Protest #$protestId on match ID $scheduleId ($party, $decision) in league ID $leagueId");
        $msg = $decision === 'Upheld'
            ? 'Protest upheld — result reopened for correction (' . count($reversal['teams']) . ' team status(es) restored, ' . count($reversal['removed_matches']) . ' bracket row(s) removed).'
            : ($decision === 'Denied' ? 'Protest denied — the official result stands.' : 'Protest logged as open.');
        sendResponse(201, $msg, ['protest_id' => $protestId, 'reversal' => $reversal]);
        return;
    }

    // Decide a protest that was logged as open
    if (isset($_POST['decideProtest'])) {
        $protestId = intval($_POST['protest_id'] ?? 0);
        $decision  = (string)($_POST['decision'] ?? '');
        $note      = trim((string)($_POST['decision_note'] ?? ''));
        if (!in_array($decision, ['Upheld', 'Denied'], true)) { sendResponse(400, 'Choose Upheld or Denied.'); return; }
        if (mb_strlen($note) > 1000) $note = mb_substr($note, 0, 1000);

        // Ownership was already verified from the protest record above; resolve
        // the league from the protest so callers are not required to resend it.
        $pStmt = $conn->prepare("SELECT * FROM tbl_result_protest WHERE id = ? LIMIT 1");
        $pStmt->bind_param('i', $protestId);
        $pStmt->execute();
        $protest = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();
        if (!$protest) { sendResponse(404, 'Protest not found in this league.'); return; }
        $leagueId = (int)$protest['league_id'];

        $scheduleId = (int)$protest['schedule_id'];
        $conn->begin_transaction();
        $noteValue = $note !== '' ? $note : null;
        $upd = $conn->prepare("UPDATE tbl_result_protest SET status=?, decided_by=?, decided_at=NOW(), decision_note=? WHERE id=? AND league_id=?");
        $upd->bind_param('siisi', $decision, $userId, $noteValue, $protestId, $leagueId);
        $upd->execute();
        $upd->close();

        $reversal = ['teams' => [], 'removed_matches' => []];
        if ($decision === 'Upheld') {
            $reversal = reverseProgression($conn, $leagueId, $scheduleId);
            $reopen = $conn->prepare("UPDATE tbl_schedule SET status='Scheduled', winner=0, result_submitted_by=NULL, result_status='UnderReview', result_reopened_by=?, result_reopened_at=NOW() WHERE id=? AND league_id=?");
            $reopen->bind_param('iii', $userId, $scheduleId, $leagueId);
            $reopen->execute();
            $reopen->close();
        } else {
            $restore = $conn->prepare("UPDATE tbl_schedule SET result_status='Final' WHERE id=? AND league_id=?");
            $restore->bind_param('ii', $scheduleId, $leagueId);
            $restore->execute();
            $restore->close();
        }
        $conn->commit();

        auditLog($conn, 'Decide Protest', "Protest #$protestId $decision for match ID $scheduleId (league ID $leagueId)");
        sendResponse(201, $decision === 'Upheld'
            ? 'Protest upheld — the match is reopened for a corrected result.'
            : 'Protest denied — the official result stands.',
            ['reversal' => $reversal]);
        return;
    }

    // Get tournament progression summary
    if (isset($_POST['getProgressionSummary'])) {
        $leagueId = intval($_POST['league_id']);
        $summary = getProgressionSummary($conn, $leagueId);
        if (!$summary) {
            sendResponse(404, "League not found");
            return;
        }
        sendResponse(200, "Progression summary retrieved", ["summary" => $summary]);
        return;
    }
    
sendResponse(400, "Invalid action");
?>
