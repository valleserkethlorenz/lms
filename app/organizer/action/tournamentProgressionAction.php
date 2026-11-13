<?php
session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../includes/schedulingHelper.php';
require_once __DIR__ . '/../../../includes/tournamentProgression.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
requireJsonCsrf();

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

function sendResponse($status, $msg, $data = null) {
    $response = ["status" => $status, "msg" => $msg];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(400, "Invalid request");
    exit;
}

// Every progression operation is scoped to the authenticated Organizer's own
// active league. Without this check any organizer could read, reset or
// overwrite another organizer's qualifiers by posting a foreign league id.
$leagueId = (int)($_POST['league_id'] ?? 0);
requireJsonLeagueOwnership($conn, $leagueId);

// Get tournament status
if (isset($_POST['getTournamentStatus'])) {
    
    $stages = getTournamentStatus($conn, $leagueId);
    $bracket = getTournamentBracket($conn, $leagueId);
    $groups = getTournamentGroups($conn, $leagueId);
    
    sendResponse(200, "Tournament status retrieved", [
        "stages" => $stages,
        "bracket" => $bracket,
        "groups" => $groups
    ]);
    exit;
}

// Check if group stage is complete
if (isset($_POST['isGroupStageComplete'])) {
    
    $isComplete = isGroupStageComplete($conn, $leagueId);
    
    sendResponse(200, "Group stage status retrieved", [
        "complete" => $isComplete
    ]);
    exit;
}

// Get tournament bracket
if (isset($_POST['getTournamentBracket'])) {
    
    $bracket = getTournamentBracket($conn, $leagueId);
    
    sendResponse(200, "Tournament bracket retrieved", [
        "bracket" => $bracket
    ]);
    exit;
}

// Get tournament groups
if (isset($_POST['getTournamentGroups'])) {
    
    $groups = getTournamentGroups($conn, $leagueId);
    
    sendResponse(200, "Tournament groups retrieved", [
        "groups" => $groups
    ]);
    exit;
}

// Suggest qualifiers based on standings
if (isset($_POST['suggestProgression'])) {
    $qualifiersPerGroup = intval($_POST['qualifiers_per_group'] ?? 1);

    $suggestions = suggestQualifiers($conn, $leagueId, $qualifiersPerGroup);
    $confirmed = getConfirmedQualifiers($conn, $leagueId);

    sendResponse(200, "Progression suggestions retrieved", [
        "suggestions" => $suggestions,
        "confirmed_qualifiers" => $confirmed,
        "confirmed_count" => count($confirmed)
    ]);
    exit;
}

// Clear all progression decisions (Reset All)
if (isset($_POST['resetProgression'])) {
    $resetCount = resetProgression($conn, $leagueId);
    auditLog($conn, 'Reset Progression', "Cleared progression decisions for $resetCount team(s) in league ID $leagueId");

    sendResponse(200, "Progression reset", [
        "reset_count" => $resetCount,
        "confirmed_count" => 0
    ]);
    exit;
}

// Confirm progression decisions (bulk)
if (isset($_POST['confirmProgression'])) {
    $teamIds = $_POST['team_ids'] ?? '';
    $userId = currentUserId();

    if (is_string($teamIds)) {
        $teamIds = array_filter(array_map('intval', explode(',', $teamIds)));
    } elseif (is_array($teamIds)) {
        $teamIds = array_map('intval', $teamIds);
    } else {
        $teamIds = [];
    }
    $teamIds = array_values(array_unique($teamIds));

    // An empty list would mean "eliminate every approved team", so reject it and
    // let the organizer use the explicit Reset All action instead.
    if (empty($teamIds)) {
        sendResponse(400, "Select at least one team to confirm as qualified.");
        exit;
    }

    // Every selected team must belong to this organizer's league and still be
    // approved; otherwise a crafted request could reach across leagues.
    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $validStmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_team WHERE id IN ($placeholders) AND league_id = ? AND LOWER(status) = 'approved'");
    if ($validStmt) {
        $validParams = array_merge($teamIds, [$leagueId]);
        $validTypes  = str_repeat('i', count($teamIds)) . 'i';
        $validStmt->bind_param($validTypes, ...$validParams);
        $validStmt->execute();
        $validCount = (int)($validStmt->get_result()->fetch_assoc()['c'] ?? 0);
        $validStmt->close();
        if ($validCount !== count($teamIds)) {
            sendResponse(400, "Select only approved teams from this league.");
            exit;
        }
    }

    bulkSetProgression($conn, $leagueId, $teamIds, $userId);
    auditLog($conn, 'Confirm Progression', "Confirmed " . count($teamIds) . " qualifier(s) for league ID $leagueId");

    $confirmed = getConfirmedQualifiers($conn, $leagueId);
    sendResponse(200, "Progression confirmed", [
        "confirmed_qualifiers" => $confirmed,
        "confirmed_count" => count($confirmed)
    ]);
    exit;
}

// Get current progression status for all teams
if (isset($_POST['getProgressionStatus'])) {

    $stmt = $conn->prepare("SELECT id, team_name, progression_status, progression_decided_by, progression_decided_at FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved' ORDER BY progression_status DESC, team_name ASC");
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $teams = [];
    while ($row = $result->fetch_assoc()) $teams[] = $row;
    $stmt->close();

    sendResponse(200, "Progression status retrieved", [
        "teams" => $teams,
        "qualified_count" => count(array_filter($teams, fn($t) => $t['progression_status'] === 'Qualified'))
    ]);
    exit;
}

sendResponse(400, "Invalid action");
exit;
?>
