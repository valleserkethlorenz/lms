<?php
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../includes/notificationHelper.php';
require_once __DIR__ . '/../../../includes/documentValidator.php';
require_once __DIR__ . '/../../../includes/documentAssets.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../includes/teamCorrectionDeadline.php';
require_once __DIR__ . '/../../../includes/rosterSize.php';
requireJsonRole([2]);
requireActiveAccount($conn, true);
requireJsonCsrf();

$userId = currentUserId();

$teamId    = intval($_POST['team_id'] ?? 0);
$teamName  = trim($_POST['team_name'] ?? '');

expireTeamCorrectionDeadlines($conn);

if (!$teamId || !$teamName) {
    echo json_encode(['status' => 400, 'msg' => 'Missing required fields']);
    exit;
}

// Only applications returned by the Organizer may be corrected and resubmitted.
$stmt = $conn->prepare("SELECT id, team_name, team_image, league_id, remarks, correction_due_at FROM tbl_team WHERE id = ? AND user_id = ? AND LOWER(status) = 'correction requested' AND correction_due_at>=NOW()");
$stmt->bind_param('ii', $teamId, $userId);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$team) {
    echo json_encode(['status' => 409, 'msg' => 'The correction deadline has expired or this application is no longer eligible for resubmission.']);
    exit;
}

$limitStmt = $conn->prepare("SELECT min_player,max_player FROM tbl_league WHERE id=? LIMIT 1");
$limitStmt->bind_param('i', $team['league_id']);
$limitStmt->execute();
$rosterLimits = $limitStmt->get_result()->fetch_assoc();
$limitStmt->close();
if (!$rosterLimits) {
    echo json_encode(['status'=>404,'msg'=>'League roster requirements were not found.']);
    exit;
}

// Validate documents FIRST before any DB writes
$playerNames = $_POST['player_name'] ?? [];
$playerIds   = $_POST['player_id'] ?? [];
$playerAges  = $_POST['player_age'] ?? [];
$playerRoles = $_POST['player_role'] ?? [];
$jerseys     = $_POST['jersey_number'] ?? [];
$genders     = $_POST['gender'] ?? [];
$documents   = $_FILES['player_document'] ?? [];
// Roster bounds follow includes/rosterSize.php: min is clamped to at least 1 and
// max_player = 0 means "no upper limit". Treating 0 as "exactly min" rejected
// resubmissions from leagues without a maximum, even though the correction page
// and eligibilityValidator both allow the longer roster.
$rosterBounds   = normalizeRosterBounds($rosterLimits['min_player'] ?? 0, $rosterLimits['max_player'] ?? 0);
$minimumPlayers = $rosterBounds['min'];
$maximumPlayers = $rosterBounds['max'];
$rosterCount    = is_array($playerNames) ? count($playerNames) : 0;
if (!is_array($playerNames) || $rosterCount < $minimumPlayers || ($maximumPlayers > 0 && $rosterCount > $maximumPlayers)) {
    echo json_encode(['status'=>400,'msg'=>'The roster must contain ' . rosterSizeRequirementText($minimumPlayers, $maximumPlayers) . '.']);
    exit;
}

// Only players explicitly returned as "Needs Correction" may be changed.
$currentPlayers=[];
$currentStmt=$conn->prepare("SELECT id,name,age,role,jersey_number,gender,document,eligibility_status FROM tbl_player WHERE team_id=?");
$currentStmt->bind_param('i',$teamId); $currentStmt->execute();
$currentResult=$currentStmt->get_result(); while($current=$currentResult->fetch_assoc()) $currentPlayers[(int)$current['id']]=$current;
$currentStmt->close();
$submittedExistingIds=[];
foreach ($playerNames as $key=>$submittedName) {
    $pid=(int)($playerIds[$key] ?? 0);
    if ($pid<=0) continue;
    if (!isset($currentPlayers[$pid])) { echo json_encode(['status'=>403,'msg'=>'An invalid roster entry was submitted.']); exit; }
    $submittedExistingIds[$pid]=true;
    $current=$currentPlayers[$pid];
    if (strtolower((string)$current['eligibility_status']) !== 'needs correction') {
        $unchanged = trim((string)$submittedName) === trim((string)$current['name'])
            && (string)($playerAges[$key] ?? '') === (string)$current['age']
            && (string)($playerRoles[$key] ?? '') === (string)$current['role']
            && (string)($jerseys[$key] ?? '') === (string)$current['jersey_number']
            && (string)($genders[$key] ?? '') === (string)$current['gender'];
        $hasUpload=isset($documents['error'][$key]) && $documents['error'][$key]===UPLOAD_ERR_OK;
        if (!$unchanged || $hasUpload) {
            echo json_encode(['status'=>403,'msg'=>'Only players marked Needs Correction can be edited.']); exit;
        }
    }
}
foreach ($currentPlayers as $pid=>$current) {
    if (strtolower((string)$current['eligibility_status']) !== 'needs correction' && !isset($submittedExistingIds[$pid])) {
        echo json_encode(['status'=>403,'msg'=>'Players not marked Needs Correction cannot be removed.']); exit;
    }
}

// Check every player has a document (new upload OR one already on record).
//
// "On record" must be judged exactly the way app/tr/resubmit_team.php renders a
// roster row: tbl_player.document is filled in, or tbl_submitted_document holds
// an uploaded document for that player. Requiring the file to exist on this
// server was wrong on two counts:
//   * with Cloudinary enabled the column stores a secure URL, so
//     documentStoredPath() always returned null and every player without a fresh
//     upload was rejected;
//   * players that are already Eligible are locked on the correction page, so
//     the representative has no way to re-upload a missing local file.
foreach ($playerNames as $key => $name) {
    $hasNewUpload = isset($documents['error'][$key]) && $documents['error'][$key] === UPLOAD_ERR_OK;
    if ($hasNewUpload) continue;

    $pid = intval($playerIds[$key] ?? 0);
    $hasExisting = $pid > 0 && trim((string) ($currentPlayers[$pid]['document'] ?? '')) !== '';

    // Players registered through typed document requirements may only have the
    // file in tbl_submitted_document.
    if (!$hasExisting && $pid > 0) {
        $chk = $conn->prepare("SELECT 1 FROM tbl_submitted_document WHERE team_id = ? AND player_id = ? LIMIT 1");
        $chk->bind_param('ii', $teamId, $pid);
        $chk->execute();
        $hasExisting = $chk->get_result()->fetch_row() !== null;
        $chk->close();
    }

    if (!$hasExisting) {
        echo json_encode(['status' => 400, 'msg' => 'Player "' . htmlspecialchars($name) . '" has no document. Please upload a document for every player.']);
        exit;
    }
}

$hasAnyUpload = !empty($documents['error']) && in_array(UPLOAD_ERR_OK, $documents['error'], true);
if ($hasAnyUpload) {
    // Only validate/move files that were actually uploaded — skip empty slots
    $filteredDocs = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
    $indexMap = []; // maps filtered index => original index
    $filteredNames = [];
    foreach ($documents['error'] as $key => $err) {
        if ($err === UPLOAD_ERR_OK) {
            $filteredIndex = count($filteredDocs['name']);
            $indexMap[$filteredIndex] = $key;
            $filteredDocs['name'][]     = $documents['name'][$key];
            $filteredDocs['type'][]     = $documents['type'][$key];
            $filteredDocs['tmp_name'][] = $documents['tmp_name'][$key];
            $filteredDocs['error'][]    = $documents['error'][$key];
            $filteredDocs['size'][]     = $documents['size'][$key];
            $filteredNames[]            = $playerNames[$key] ?? 'Player ' . ($key + 1);
        }
    }
    $filteredResult = validatePlayerDocuments($filteredDocs, $filteredNames);
    if (!empty($filteredResult['blurry'])) {
        $firstIssue=reset($filteredResult['issues']);
        echo json_encode(['status' => 400, 'msg' => documentQualityMessage(is_array($firstIssue) ? $firstIssue : [])]);
        exit;
    }
    // Re-map validated docs back to original indexes
    // Also delete old file on disk if player is uploading a replacement
    $validation = ['valid' => [], 'quality'=>[], 'quality_details'=>[]];
    foreach ($filteredResult['valid'] as $filteredIndex => $docFileName) {
        $origKey = $indexMap[$filteredIndex];
        $validation['valid'][$origKey] = $docFileName;
        $validation['quality'][$origKey] = $filteredResult['quality'][$filteredIndex] ?? 'Pending';
        $validation['quality_details'][$origKey] = $filteredResult['quality_details'][$filteredIndex] ?? '';
        // Delete old document file if it exists
        $pid = intval($playerIds[$origKey] ?? 0);
        if ($pid > 0) {
            $oldChk = $conn->prepare("SELECT document FROM tbl_player WHERE id = ? AND team_id = ?");
            $oldChk->bind_param('ii', $pid, $teamId);
            $oldChk->execute();
            $oldRow = $oldChk->get_result()->fetch_assoc();
            $oldChk->close();
            if (!empty($oldRow['document'])) {
                // Stored document, local or Cloudinary — deleteStoredDocument()
                // removes the cloud asset as well as a file on disk.
                deleteStoredDocument($oldRow['document']);
            }
        }
    }
} else {
    $validation = ['valid' => [], 'quality'=>[], 'quality_details'=>[]];
}

// Validate replacements for the league's configured team/player document types.
$replacementUploads=[];
$replacementFiles=$_FILES['replacement_documents'] ?? [];
$replacementNames=(array)($replacementFiles['name'] ?? []);
$hasReplacement=false;
foreach($replacementNames as $name) if(trim((string)$name)!=='') { $hasReplacement=true; break; }
if (!$hasReplacement && preg_match('/Invalid team-level document/i',(string)$team['remarks'])) {
    echo json_encode(['status'=>400,'msg'=>'Upload at least one replacement document before resubmitting.']); exit;
}
if ($hasReplacement) {
    $files=['name'=>[],'tmp_name'=>[],'error'=>[],'size'=>[]]; $labels=[]; $metadata=[]; $documentTypes=[];
    foreach($replacementNames as $documentId=>$name) {
        if(trim((string)$name)==='') continue;
        $documentId=(int)$documentId;
        $docStmt=$conn->prepare("SELECT d.id,d.stored_name,d.player_id,d.requirement_id,u.name AS document_name,p.eligibility_status FROM tbl_submitted_document d JOIN tbl_league_document_requirement r ON r.id=d.requirement_id JOIN tbl_utility u ON u.id=r.document_type_id LEFT JOIN tbl_player p ON p.id=d.player_id WHERE d.id=? AND d.team_id=? LIMIT 1");
        $docStmt->bind_param('ii',$documentId,$teamId); $docStmt->execute(); $metadataRow=$docStmt->get_result()->fetch_assoc(); $docStmt->close();
        if(!$metadataRow) { echo json_encode(['status'=>403,'msg'=>'An invalid replacement document was submitted.']); exit; }
        if ((int)$metadataRow['player_id'] > 0 && strtolower((string)$metadataRow['eligibility_status']) !== 'needs correction') {
            echo json_encode(['status'=>403,'msg'=>'Documents can only be replaced for players marked Needs Correction.']); exit;
        }
        $files['name'][$documentId]=$name;
        $files['tmp_name'][$documentId]=$replacementFiles['tmp_name'][$documentId] ?? '';
        $files['error'][$documentId]=$replacementFiles['error'][$documentId] ?? UPLOAD_ERR_NO_FILE;
        $files['size'][$documentId]=$replacementFiles['size'][$documentId] ?? 0;
        $metadataRow['upload_mime']=(new finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$documentId]) ?: 'application/octet-stream';
        $labels[$documentId]=$metadataRow['document_name']; $metadata[$documentId]=$metadataRow;
        $documentTypes[$documentId]=(string)$metadataRow['document_name'];
    }
    $replacementResult=validatePlayerDocuments($files,$labels,$documentTypes);
    if(!empty($replacementResult['blurry'])) {
        foreach($replacementResult['valid'] as $stored) { deleteStoredDocument($stored); }
        $firstIssue=reset($replacementResult['issues']);
        echo json_encode(['status'=>400,'msg'=>documentQualityMessage(is_array($firstIssue) ? $firstIssue : [])]); exit;
    }
    foreach($replacementResult['valid'] as $documentId=>$stored) {
        $replacementUploads[$documentId]=['stored'=>$stored,'original'=>$files['name'][$documentId],
            'mime'=>$metadata[$documentId]['upload_mime'],
            'quality'=>$replacementResult['quality'][$documentId] ?? 'Pending','old'=>$metadata[$documentId]['stored_name'],
            'unavailable'=>isset($replacementResult['unavailable'][$documentId]),
            'details'=>$replacementResult['quality_details'][$documentId] ?? '',
            'player_id'=>(int)($metadata[$documentId]['player_id'] ?? 0)];
    }
}

// Handle team image upload
$imageName = $team['team_image'];
if (!empty($_FILES['team_image']['name'])) {
    if (StorageService::isEnabled()) {
        $ext = strtolower(pathinfo($_FILES['team_image']['name'], PATHINFO_EXTENSION));
        $cloudTeam = StorageService::upload($_FILES['team_image']['tmp_name'], 'team_images', [
            'public_id' => time() . '_' . bin2hex(random_bytes(4)),
        ]);
        $imageName = $cloudTeam['secure_url'];
    } else {
        $uploadDir = '../../../assets/uploads/';
        $imageName = time() . '_' . basename($_FILES['team_image']['name']);
        move_uploaded_file($_FILES['team_image']['tmp_name'], $uploadDir . $imageName);
    }
}

// Update team
$flags=[];
foreach($validation['quality'] as $key=>$quality) {
    $flag=documentQualityFlag('Document for '.($playerNames[$key] ?? 'player'),$quality,$validation['quality_details'][$key] ?? 'Manual review is recommended.',isset($validation['unavailable'][$key]));
    if ($flag!==null) $flags[]=$flag;
}
foreach($replacementUploads as $replacement) {
    $flag=documentQualityFlag('A replacement document',$replacement['quality'] ?? 'Pending',$replacement['details'] ?? 'Manual review is recommended.',!empty($replacement['unavailable']));
    if ($flag!==null) $flags[]=$flag;
}
$flagsJson=$flags ? json_encode(['document_quality'=>$flags], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;
$upd = $conn->prepare("UPDATE tbl_team SET team_name = ?, team_image = ?, status = 'Pending', remarks = NULL, correction_due_at=NULL, correction_expired_at=NULL, preliminary_flags=? WHERE id = ? AND LOWER(status)='correction requested' AND correction_due_at>=NOW()");
$upd->bind_param('sssi', $teamName, $imageName, $flagsJson, $teamId);
if (!$upd->execute()) {
    echo json_encode(['status' => 500, 'msg' => 'Failed to update team']);
    exit;
}
$updatedRows = $upd->affected_rows;
$upd->close();
if ($updatedRows !== 1) {
    expireTeamCorrectionDeadlines($conn);
    echo json_encode(['status'=>409,'msg'=>'The correction deadline expired before the application could be submitted.']);
    exit;
}

// Delete players that were removed (existing IDs not in submitted list)
$submittedIds = array_filter(array_map('intval', $playerIds));
if (!empty($submittedIds)) {
    $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
    $types = str_repeat('i', count($submittedIds));
    $delStmt = $conn->prepare("DELETE FROM tbl_player WHERE team_id = ? AND LOWER(eligibility_status)='needs correction' AND id NOT IN ($placeholders)");
    $params = array_merge([$teamId], $submittedIds);
    $delStmt->bind_param('i' . $types, ...$params);
    $delStmt->execute();
    $delStmt->close();
} else {
    $delStmt = $conn->prepare("DELETE FROM tbl_player WHERE team_id=? AND LOWER(eligibility_status)='needs correction'");
    $delStmt->bind_param('i',$teamId); $delStmt->execute(); $delStmt->close();
}

foreach ($playerNames as $key => $name) {
    $name    = trim($name);
    $age     = (string)($playerAges[$key] ?? '');
    $role    = $playerRoles[$key] ?? '';
    $jersey  = (string)($jerseys[$key] ?? '');
    $gender  = $genders[$key] ?? '';
    $pid     = intval($playerIds[$key] ?? 0);

    // Use already-validated and moved document
    $docName = $validation['valid'][$key] ?? null;

    if ($pid > 0) {
        if (strtolower((string)($currentPlayers[$pid]['eligibility_status'] ?? '')) !== 'needs correction') continue;
        // Update existing player
        if ($docName) {
            $ps = $conn->prepare("UPDATE tbl_player SET name=?, age=?, role=?, jersey_number=?, gender=?, document=? WHERE id=? AND team_id=?");
            $ps->bind_param('ssssssii', $name, $age, $role, $jersey, $gender, $docName, $pid, $teamId);
        } else {
            $ps = $conn->prepare("UPDATE tbl_player SET name=?, age=?, role=?, jersey_number=?, gender=? WHERE id=? AND team_id=?");
            $ps->bind_param('sssssii', $name, $age, $role, $jersey, $gender, $pid, $teamId);
        }
        $ps->execute();
        $ps->close();
    } else {
        // Insert new player
        $teamIdStr = (string)$teamId;
        $ps = $conn->prepare("INSERT INTO tbl_player (team_id, name, age, role, gender, jersey_number, document) VALUES (?,?,?,?,?,?,?)");
        $ps->bind_param('sssssss', $teamIdStr, $name, $age, $role, $gender, $jersey, $docName);
        $ps->execute();
        $ps->close();
    }
}

// Only corrected players return to review; previously eligible players stay eligible.
$resetEligibility = $conn->prepare("UPDATE tbl_player SET eligibility_status='Pending',eligibility_remarks=NULL,eligibility_reviewed_by=NULL,eligibility_reviewed_at=NULL WHERE team_id=? AND LOWER(eligibility_status)='needs correction'");
$resetEligibility->bind_param('i', $teamId);
$resetEligibility->execute();
$resetEligibility->close();

foreach($replacementUploads as $documentId=>$replacement) {
    $replaceStmt=$conn->prepare("UPDATE tbl_submitted_document SET stored_name=?,original_name=?,mime_type=?,quality_status=?,review_status='Pending',review_remarks=NULL WHERE id=? AND team_id=?");
    $replaceStmt->bind_param('ssssii',$replacement['stored'],$replacement['original'],$replacement['mime'],$replacement['quality'],$documentId,$teamId);
    $replaceStmt->execute(); $replaceStmt->close();
    if($replacement['player_id']>0) {
        $syncStmt=$conn->prepare("UPDATE tbl_player SET document=? WHERE id=? AND team_id=? AND document=?");
        $syncStmt->bind_param('siis',$replacement['stored'],$replacement['player_id'],$teamId,$replacement['old']); $syncStmt->execute(); $syncStmt->close();
    }
    if(!empty($replacement['old'])) deleteStoredDocument($replacement['old']);
}

// Notify organizer (best effort: a notification must never turn a successful
// resubmission into an error response).
try {
    notifyTeamRegistration($conn, $team['league_id'], $teamName, $teamId, true);
} catch (Throwable $e) {
    error_log('[notification] Resubmission notice failed for team ' . $teamId . ': ' . $e->getMessage());
}
auditLog($conn,'Resubmit Registration',"Corrected and resubmitted team registration: $teamName (ID $teamId)");

echo json_encode(['status' => 200, 'msg' => 'Re-application submitted successfully']);
