<?php
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../includes/notificationHelper.php';
require_once __DIR__ . '/../../../includes/storageService.php';
require_once __DIR__ . '/../../../config/leagueStatus.php';
require_once __DIR__ . '/../../../includes/registrationGate.php';
require_once __DIR__ . '/../../../includes/stageHelper.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../config/inputValidator.php';
requireJsonRole([1]);
requireActiveAccount($conn, true);
enforceRequestSizeLimit(20 * 1024 * 1024); // 20MB (allows document uploads)
addApiSecurityHeaders();
date_default_timezone_set('Asia/Manila');


$created = date("Y-m-d H:i:s");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['regisLeague']) || isset($_POST['publishLeague']) || isset($_POST['archiveLeague']) || isset($_POST['closeRegistration']) || isset($_POST['reopenRegistration']) || isset($_POST['approved']) || isset($_POST['reject']) || isset($_POST['requestCorrection']) || isset($_POST['playerEligibility']) || isset($_POST['documentReview'])) {
        requireJsonCsrf();
    }
    if (isset($_POST['league_id']) && !organizerOwnsLeague($conn, (int)$_POST['league_id'], null, true)) {
        echo json_encode(['status' => 403, 'msg' => 'You may manage only your own leagues.']);
        exit;
    }
    if (isset($_POST['team_id']) && !organizerOwnsTeam($conn, (int)$_POST['team_id'])) {
        echo json_encode(['status' => 403, 'msg' => 'You may review only teams registered in your own leagues.']);
        exit;
    }
}

/**
 * Recompute and persist league status into tbl_league.stat.
 *
 * Returns false when the row cannot be read or the computed status cannot be
 * written, so callers that depend on `stat` (the close/reopen registration
 * endpoints) can roll their transaction back. Existing callers may ignore the
 * return value.
 */
function recomputeAndPersistLeagueStat(mysqli $conn, int $leagueId): bool {
    $stmt = $conn->prepare(
        "SELECT id, start_date, end_date, regis_start, regis_end, max_team, registration_window_open, stat " .
        "FROM tbl_league WHERE id = ? LIMIT 1"
    );
    if (!$stmt) return false;

    $stmt->bind_param('i', $leagueId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return false;

    $computed = getLeagueStatus($row);

    // Persist computed status
    $up = $conn->prepare("UPDATE tbl_league SET `stat` = ? WHERE id = ?");
    if (!$up) return false;
    $up->bind_param('si', $computed, $leagueId);
    $ok = $up->execute();
    $up->close();

    return (bool) $ok;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = '';


    if (isset($_POST['regisLeague'])) {

        // Debug logger
        $logFile = __DIR__ . '/debug_league.log';
        $log = function($msg) use ($logFile) {
            $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        };
        $log('---- regisLeague request ----');
        $log('POST: ' . json_encode($_POST));
        if (!empty($_FILES)) {
            $files = array_map(function($f){ return $f['name']; }, $_FILES);
            $log('FILES: ' . json_encode($files));
        }

        // Normalize and validate every league field on the server. Client-side
        // required/min attributes can be bypassed and are only usability aids.
        $league_name = trim($_POST['league_name'] ?? '');
        $sport = trim($_POST['sport'] ?? '');
        $division = trim($_POST['division'] ?? '');
        $category = trim($_POST['category'] ?? '');
        // The competition structure is chosen on the Setup page after creation.
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $regis_start = trim($_POST['regis_start'] ?? '');
        $regis_end = trim($_POST['regis_end'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $descrip = trim($_POST['descrip'] ?? '');
        $add_info = trim($_POST['add_info'] ?? '');
        $reg_fee = '0';
        $max_team_raw = trim((string)($_POST['max_team'] ?? ''));
        $min_player_raw = trim((string)($_POST['min_player'] ?? ''));
        $max_player_raw = trim((string)($_POST['max_player'] ?? ''));
        $userId = currentUserId();

        $requiredText = [
            'League name'=>$league_name, 'Sport'=>$sport, 'Division'=>$division,
            'Category'=>$category, 'Venue'=>$venue,
            'About league'=>$descrip
        ];
        foreach ($requiredText as $label => $value) {
            if ($value === '') {
                echo json_encode(['status'=>400, 'msg'=>"$label is required."]);
                exit;
            }
            if (strlen($value) > 255) {
                echo json_encode(['status'=>400, 'msg'=>"$label must not exceed 255 characters."]);
                exit;
            }
        }
        // "Other information" is optional; it only has to fit the column width.
        if (strlen($add_info) > 255) {
            echo json_encode(['status'=>400, 'msg'=>'Other information must not exceed 255 characters.']);
            exit;
        }

        if (!ctype_digit($max_team_raw) || ($max_team = (int)$max_team_raw) < 2 || $max_team > 256) {
            echo json_encode(['status'=>400, 'msg'=>'Maximum teams must be a whole number from 2 to 256.']);
            exit;
        }
        if (!ctype_digit($min_player_raw) || ($min_player = (int)$min_player_raw) < 1 || $min_player > 100) {
            echo json_encode(['status'=>400, 'msg'=>'Minimum players must be a whole number from 1 to 100.']);
            exit;
        }
        if (!ctype_digit($max_player_raw) || ($max_player = (int)$max_player_raw) < 1 || $max_player > 100) {
            echo json_encode(['status'=>400, 'msg'=>'Maximum players must be a whole number from 1 to 100.']);
            exit;
        }
        if ($min_player > $max_player) {
            echo json_encode(['status'=>400, 'msg'=>'Minimum players cannot be greater than maximum players.']);
            exit;
        }
        foreach (['Registration start'=>$regis_start, 'Registration end'=>$regis_end, 'League start'=>$start_date, 'League end'=>$end_date] as $label=>$dateValue) {
            $parsed = DateTime::createFromFormat('!Y-m-d', $dateValue);
            if (!$parsed || $parsed->format('Y-m-d') !== $dateValue) {
                echo json_encode(['status'=>400, 'msg'=>"$label must be a valid date."]);
                exit;
            }
        }

        // -------------------------
        // Server-side date validation
        // Ensure: regis_start <= regis_end <= start_date <= end_date
        // -------------------------
        $rs = isset($_POST['regis_start']) ? strtotime($_POST['regis_start']) : false;
        $re = isset($_POST['regis_end']) ? strtotime($_POST['regis_end']) : false;
        $sd = isset($_POST['start_date']) ? strtotime($_POST['start_date']) : false;
        $ed = isset($_POST['end_date']) ? strtotime($_POST['end_date']) : false;

        if (!$rs || !$re || !$sd || !$ed) {
            echo json_encode(["status" => 400, "msg" => "Invalid or missing date fields."]);
            exit;
        }

        if (!($rs <= $re && $re <= $sd && $sd <= $ed)) {
            echo json_encode(["status" => 400, "msg" => "Invalid date order. Ensure registration and league dates are in chronological order."]);
            exit;
        }


        // Validate uploads from their server-detected MIME type. Browser accept=
        // attributes and client-provided filenames are not security controls.
        $imageName = '';
        $guidelineDocument = '';
        $imageTarget = null;
        $documentTargets = [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $imageMimeMap = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/avif' => 'avif'
        ];
        $documentMimeMap = [
            'application/pdf' => 'pdf', 'text/plain' => 'txt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'image/jpeg' => 'jpg', 'image/png' => 'png'
        ];

        $image = $_FILES['league_image'] ?? null;
        if (!$image || ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['status'=>400, 'msg'=>'A valid league image is required.']);
            exit;
        }
        if (($image['size'] ?? 0) <= 0 || $image['size'] > 5 * 1024 * 1024) {
            echo json_encode(['status'=>400, 'msg'=>'League image must be 5 MB or smaller.']);
            exit;
        }
        $imageMime = $finfo->file($image['tmp_name']);
        if (!isset($imageMimeMap[$imageMime])) {
            echo json_encode(['status'=>400, 'msg'=>'League image must be a valid JPG, PNG, GIF, WebP, or AVIF file.']);
            exit;
        }

        $documents = [];
        if (!empty($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
            foreach ($_FILES['documents']['name'] as $index=>$originalName) {
                $error=$_FILES['documents']['error'][$index] ?? UPLOAD_ERR_NO_FILE;
                if ($error===UPLOAD_ERR_NO_FILE || $originalName==='') continue;
                $document=['name'=>basename((string)$originalName),'tmp_name'=>$_FILES['documents']['tmp_name'][$index]??'','error'=>$error,'size'=>(int)($_FILES['documents']['size'][$index]??0)];
                if ($document['error']!==UPLOAD_ERR_OK || $document['size']<=0 || $document['size']>10*1024*1024) { echo json_encode(['status'=>400,'msg'=>'A guideline document upload failed or exceeds 10 MB.']); exit; }
                $document['mime']=$finfo->file($document['tmp_name']);
                if (!isset($documentMimeMap[$document['mime']])) { echo json_encode(['status'=>400,'msg'=>'A guideline document has an unsupported file type.']); exit; }
                $documents[]=$document;
            }
            if(count($documents)>10){echo json_encode(['status'=>400,'msg'=>'A league can have up to 10 guideline documents.']);exit;}
        }

        // =========================
        // Validate category and Admin-managed utility values.
        // =========================
        foreach (['sport'=>$sport, 'venue'=>$venue, 'category'=>$category] as $utilityCategory => $utilityValue) {
            $utilityStmt = $conn->prepare("SELECT 1 FROM tbl_utility WHERE category=? AND name=? AND is_active=1 LIMIT 1");
            $utilityStmt->bind_param('ss', $utilityCategory, $utilityValue);
            $utilityStmt->execute();
            $validUtility = (bool)$utilityStmt->get_result()->fetch_row();
            $utilityStmt->close();
            if (!$validUtility) {
                echo json_encode(["status" => 400, "msg" => "Select an active Admin-managed $utilityCategory."]);
                exit;
            }
        }

        $divisionStmt=$conn->prepare("SELECT min_age,max_age FROM tbl_utility WHERE category='division' AND name=? AND is_active=1 LIMIT 1");
        $divisionStmt->bind_param('s',$division); $divisionStmt->execute(); $divisionRule=$divisionStmt->get_result()->fetch_assoc(); $divisionStmt->close();
        if (!$divisionRule || $divisionRule['min_age'] === null || $divisionRule['max_age'] === null) { echo json_encode(['status'=>400,'msg'=>'Select an active division with a configured age bracket.']); exit; }
        $min_age=(int)$divisionRule['min_age']; $max_age=(int)$divisionRule['max_age'];

        $documentRequirements=[];
        foreach (($_POST['document_requirement'] ?? []) as $documentTypeId => $required) {
            $documentTypeId = (int)$documentTypeId;
            if ($documentTypeId <= 0 || $required !== '1') continue;
            $instructions = trim($_POST['document_instructions'][$documentTypeId] ?? '');
            if (strlen($instructions) > 500) { echo json_encode(['status'=>400,'msg'=>'Document instructions must not exceed 500 characters.']); exit; }
            // Read scope from tbl_utility (applies_to) instead of trusting POST
            $docStmt = $conn->prepare("SELECT id, applies_to FROM tbl_utility WHERE id=? AND category='document_type' AND is_active=1");
            $docStmt->bind_param('i', $documentTypeId);
            $docStmt->execute();
            $docResult = $docStmt->get_result();
            $docRow = $docResult->fetch_assoc();
            $docStmt->close();
            if (!$docRow || !$docRow['applies_to']) {
                echo json_encode(['status'=>400,'msg'=>'A selected document requirement is no longer active or has no scope configured.']);
                exit;
            }
            $documentRequirements[] = ['id'=>$documentTypeId, 'scope'=>$docRow['applies_to'], 'instructions'=>$instructions];
        }

        // =========================
        // Check for duplicates (prevent race condition)
        // =========================
        $dup_check = "SELECT id FROM tbl_league WHERE created_by = ? AND league_name = ? AND sport = ? AND start_date = ? AND end_date = ? AND (is_archived IS NULL OR is_archived = 0) LIMIT 1";
        $dup_stmt = $conn->prepare($dup_check);
        
        if (!$dup_stmt) {
            $log('Duplicate check prepare failed: ' . $conn->error);
            echo json_encode(["status" => 500, "msg" => "Server validation error."]);
            exit;
        }
        
        $dup_stmt->bind_param('issss', $userId, $league_name, $sport, $start_date, $end_date);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        
        if ($dup_result->num_rows > 0) {
            $log('Duplicate league attempt detected: ' . $league_name);
            $dup_stmt->close();
            echo json_encode(["status" => 409, "msg" => "A league with this name, sport, and dates already exists. Please use different values."]);
            exit;
        }
        $dup_stmt->close();

        // =========================
        // INSERT QUERY
        // =========================
        $sql = "INSERT INTO tbl_league (
                league_name, sport, division, category,
                start_date, end_date, regis_start, regis_end,
                venue, max_team, min_player, max_player, regis_fee,
                descrip, add_info, league_image, guideline_document, `stat`,
                min_age, max_age, min_male_players, min_female_players, created_by
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $log('Prepare failed: ' . $conn->error);
            echo json_encode(["status" => 500, "msg" => "Server prepare error."]);
            exit;
        }

        // Store validated files via Cloudinary (falls back to local if not configured).
        $imageDir = __DIR__ . '/../../../assets/uploads/';
        $documentDir = __DIR__ . '/../../../assets/documents/';
        $useCloud = StorageService::isEnabled();
        if (!$useCloud) {
            foreach ([$imageDir, $documentDir] as $directory) {
                if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                    $stmt->close();
                    echo json_encode(['status'=>500, 'msg'=>'Unable to prepare the upload directory.']);
                    exit;
                }
            }
        }
        if ($useCloud) {
            $cloudImg = StorageService::upload($image['tmp_name'], 'league_images', [
                'public_id' => 'league_' . bin2hex(random_bytes(16)),
            ]);
            $imageName = $cloudImg['secure_url'];
            $imageTarget = $cloudImg['public_id'];
        } else {
            $imageName = 'league_' . bin2hex(random_bytes(16)) . '.' . $imageMimeMap[$imageMime];
            $imageTarget = $imageDir . $imageName;
            if (!move_uploaded_file($image['tmp_name'], $imageTarget)) {
                $stmt->close();
                echo json_encode(['status'=>500, 'msg'=>'Unable to save the league image.']);
                exit;
            }
        }
        $storedDocuments=[];
        foreach($documents as $document){
            if ($useCloud) {
                $cloudDoc = StorageService::upload($document['tmp_name'], 'guideline_documents', [
                    'public_id' => 'guideline_' . bin2hex(random_bytes(16)),
                ]);
                $storedName = $cloudDoc['secure_url'];
                $documentTargets[] = $cloudDoc['public_id'];
            } else {
                $storedName='guideline_'.bin2hex(random_bytes(16)).'.'.$documentMimeMap[$document['mime']];
                $documentTarget=$documentDir.$storedName;
                if(!move_uploaded_file($document['tmp_name'],$documentTarget)){
                    if($useCloud) StorageService::delete($imageTarget); else @unlink($imageTarget);
                    foreach($documentTargets as $path) if($useCloud) StorageService::delete($path,'raw'); else @unlink($path);
                    $stmt->close();echo json_encode(['status'=>500,'msg'=>'Unable to save a guideline document.']);exit;
                }
                $documentTargets[]=$documentTarget;
            }
            $storedDocuments[]=['stored'=>$storedName,'original'=>mb_substr($document['name'],0,255)];
        }
        $guidelineDocument=$storedDocuments[0]['stored']??'';

        $status = 'Upcoming';
        $min_male = 0;
        $min_female = 0;

        // NOTE: tbl_league columns (from database/install/01_schema.sql):
        // league_name, sport, division, category,
        // start_date, end_date, regis_start, regis_end,
        // venue, max_team, max_player, regis_fee,
        // descrip, add_info, league_image, guideline_document, stat,
        // min_age, max_age, min_male_players, min_female_players, created_by
        // All fields are treated as strings except ages/male/female (ints) and max_team/max_player (varchar in schema).
        $stmt->bind_param(
            "ssssssssssssssssssiiiii",
            $league_name,
            $sport,
            $division,
            $category,
            $start_date,
            $end_date,
            $regis_start,
            $regis_end,
            $venue,
            $max_team,
            $min_player,
            $max_player,
            $reg_fee,
            $descrip,
            $add_info,
            $imageName,
            $guidelineDocument,
            $status,
            $min_age,
            $max_age,
            $min_male,
            $min_female,
            $userId
        );

        $log('Binding complete. Values: league=' . $league_name . ', sport=' . $sport . ', min_age=' . $min_age . ', max_age=' . $max_age . ', userId=' . $userId);

        $log('About to execute: bind_params set, userId=' . $userId);

        $conn->begin_transaction();
        $ok = $stmt->execute();
        if (!$ok) {
            $log('Execute failed: stmt_error=' . $stmt->error . ' conn_error=' . $conn->error);
            if ($useCloud) { StorageService::delete($imageTarget); foreach ($documentTargets as $dt) StorageService::delete($dt, 'raw'); }
            else { if ($imageTarget && is_file($imageTarget)) @unlink($imageTarget); foreach ($documentTargets as $dt) if (is_file($dt)) @unlink($dt); }
            $conn->rollback();
            echo json_encode([
                "status" => 500,
                "msg" => "Insert failed: " . $stmt->error
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }

        $log('Execute returned success');

        if ($stmt->affected_rows > 0) {
            $newLeagueId = (int)$conn->insert_id;
            $log('Created league id: ' . $newLeagueId);
            $log('Rows affected: ' . $stmt->affected_rows);
            $guidelineStmt=$conn->prepare("INSERT INTO tbl_league_guideline_document(league_id,stored_name,original_name,uploaded_by) VALUES (?,?,?,?)");
            foreach($storedDocuments as $storedDocument){$guidelineStmt->bind_param('issi',$newLeagueId,$storedDocument['stored'],$storedDocument['original'],$userId);if(!$guidelineStmt->execute()){$guidelineStmt->close();$conn->rollback();if($useCloud){StorageService::delete($imageTarget);foreach($documentTargets as $path)StorageService::delete($path,'raw');}else{@unlink($imageTarget);foreach($documentTargets as $path)@unlink($path);}echo json_encode(['status'=>500,'msg'=>'Unable to save league guideline records.']);exit;}}
            $guidelineStmt->close();
            $requirementStmt=$conn->prepare("INSERT INTO tbl_league_document_requirement(league_id,document_type_id,applies_to,is_required,instructions) VALUES (?,?,?,1,?)");
            foreach ($documentRequirements as $requirement) {
                $requirementStmt->bind_param('iiss',$newLeagueId,$requirement['id'],$requirement['scope'],$requirement['instructions']);
                if (!$requirementStmt->execute()) {
                    $requirementStmt->close(); $conn->rollback();
                    if ($useCloud) { StorageService::delete($imageTarget); foreach ($documentTargets as $dt) StorageService::delete($dt, 'raw'); }
                    else { if ($imageTarget && is_file($imageTarget)) @unlink($imageTarget); foreach ($documentTargets as $dt) if (is_file($dt)) @unlink($dt); }
                    echo json_encode(['status'=>500,'msg'=>'Unable to save league document requirements.']); exit;
                }
            }
            $requirementStmt->close();
            recomputeAndPersistLeagueStat($conn, $newLeagueId);
            auditLog($conn, 'Create League Draft', "Created draft league: $league_name (ID $newLeagueId)");
            $conn->commit();
            echo json_encode([
                "status" => 201,
                "msg" => "League saved as Draft. Publish it when it is ready for registration.",
                "league_id" => $newLeagueId
            ]);

        } else {
            $log('No rows affected. stmt_error=' . $stmt->error);
            if ($useCloud) { StorageService::delete($imageTarget); foreach ($documentTargets as $dt) StorageService::delete($dt, 'raw'); }
            else { if ($imageTarget && is_file($imageTarget)) @unlink($imageTarget); foreach ($documentTargets as $dt) if (is_file($dt)) @unlink($dt); }
            $conn->rollback();
            echo json_encode([
                "status" => 500,
                "msg" => "Insert failed: " . $stmt->error
            ]);
        }

        $stmt->close();
        $conn->close();
    }
    if (isset($_POST['schedule'])) {
        echo json_encode(['status'=>403,'msg'=>'Use the Organizer Schedule page to create validated match drafts.']);
        exit;

        $sql = "INSERT INTO tbl_schedule 
            (league_id, team_a, team_b, date, time, status)  
            VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $status = 'Upcoming';

        $stmt->bind_param(
            "iiisss",
            $_POST['league_id'],
            $_POST['team_a'],
            $_POST['team_b'],
            $_POST['date'],
            $_POST['time'],
            $status
        );

        if ($stmt->execute()) {
            echo json_encode([
                "status" => 201,
                "msg" => "Schedule created successfully"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "msg" => $stmt->error
            ]);
        }

        $stmt->close();
        $conn->close();
    }
    if (isset($_POST['publishLeague'])) {
        $id = intval($_POST['league_id']);
        $userId = currentUserId();
        $stmt = $conn->prepare("SELECT * FROM tbl_league WHERE id=? AND created_by=? AND (is_archived=0 OR is_archived IS NULL) LIMIT 1");
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $league = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$league) {
            echo json_encode(['status'=>404,'msg'=>'League not found.']); exit;
        }
        if ($league['publication_status'] === 'Published') {
            echo json_encode(['status'=>200,'msg'=>'League is already published.']); exit;
        }

        $readinessErrors = [];
        foreach (['league_name'=>'League name','sport'=>'Sport','division'=>'Division','category'=>'Category','venue'=>'Venue','descrip'=>'About league','league_image'=>'League image'] as $field=>$label) {
            if (trim((string)($league[$field] ?? '')) === '') $readinessErrors[] = "$label is missing";
        }
        if ((int)($league['max_team'] ?? 0) < 2) $readinessErrors[] = 'Maximum teams must be at least 2';
        $minimumPlayers = (int)($league['min_player'] ?? 5);
        $maximumPlayers = (int)($league['max_player'] ?? 0);
        if ($minimumPlayers < 1) $readinessErrors[] = 'Minimum players must be at least 1';
        if ($maximumPlayers < 1) $readinessErrors[] = 'Maximum players must be at least 1';
        if ($minimumPlayers > $maximumPlayers) $readinessErrors[] = 'Minimum players cannot exceed maximum players';

        $dates = [];
        foreach (['regis_start'=>'Registration start','regis_end'=>'Registration end','start_date'=>'League start','end_date'=>'League end'] as $field=>$label) {
            $value = (string)($league[$field] ?? '');
            $parsed = DateTime::createFromFormat('!Y-m-d', $value);
            if (!$parsed || $parsed->format('Y-m-d') !== $value) $readinessErrors[] = "$label is invalid";
            else $dates[$field] = $parsed;
        }
        if (count($dates) === 4 && !($dates['regis_start'] <= $dates['regis_end'] && $dates['regis_end'] <= $dates['start_date'] && $dates['start_date'] <= $dates['end_date'])) {
            $readinessErrors[] = 'Registration and league dates are not chronological';
        }
        $today = new DateTime('today', new DateTimeZone('Asia/Manila'));
        if (isset($dates['regis_end']) && $dates['regis_end'] < $today) {
            $readinessErrors[] = 'Registration has already ended';
        }
        if ((int)($league['registration_window_open'] ?? 0) !== 1) {
            $readinessErrors[] = 'Registration window is closed';
        }

        foreach (['sport','division','venue','category'] as $utilityCategory) {
            $utilityValue = (string)($league[$utilityCategory] ?? '');
            $utilityStmt = $conn->prepare("SELECT 1 FROM tbl_utility WHERE category=? AND name=? AND is_active=1 LIMIT 1");
            $utilityStmt->bind_param('ss', $utilityCategory, $utilityValue);
            $utilityStmt->execute();
            if (!$utilityStmt->get_result()->fetch_row()) $readinessErrors[] = ucfirst($utilityCategory).' is no longer active';
            $utilityStmt->close();
        }
        $imagePath = __DIR__ . '/../../../assets/uploads/' . basename((string)($league['league_image'] ?? ''));
        $leagueImgValue = (string)($league['league_image'] ?? '');
        if ($leagueImgValue === '' || (!StorageService::isCloudinary($leagueImgValue) && !is_file($imagePath))) $readinessErrors[] = 'League image file is missing';

        if ($readinessErrors) {
            echo json_encode(['status'=>422,'msg'=>'League is not ready to publish: '.implode('; ', array_unique($readinessErrors)).'.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE tbl_league SET publication_status='Published',published_at=NOW() WHERE id=? AND created_by=? AND publication_status='Draft'");
        $stmt->bind_param('ii', $id, $userId);
        if ($stmt->execute() && $stmt->affected_rows === 1) {
            recomputeAndPersistLeagueStat($conn, $id);
            // Broadcasting to every representative is best effort: the league is
            // already published, so a delivery failure must not be reported to
            // the organizer as a failed publish.
            try {
                notifyNewLeague($conn, $id, $league['league_name'], $league['regis_end']);
            } catch (Throwable $e) {
                error_log('[notification] New-league broadcast failed for league ' . $id . ': ' . $e->getMessage());
            }
            auditLog($conn, 'Publish League', "Published league: {$league['league_name']} (ID $id)");
            echo json_encode(['status'=>201,'msg'=>'League published successfully.']);
        } else {
            echo json_encode(['status'=>500,'msg'=>'Unable to publish league.']);
        }
        $stmt->close();
        exit;
    }
    if (isset($_POST['restoreLeague'])) {
        $id = intval($_POST['league_id']);
        $userId = currentUserId();

        // Enforce 30-day restore window + ownership
        $checkStmt = $conn->prepare("SELECT id, league_name, archived_at, created_by FROM tbl_league WHERE id=? AND is_archived=1 AND created_by=? AND archived_at IS NOT NULL AND archived_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $checkStmt->bind_param('ii', $id, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$checkResult) {
            echo json_encode(['status'=>403,'msg'=>'This league can no longer be restored. The 30-day restore window has expired or you do not own this league.']);
            exit;
        }

        $lname = $checkResult['league_name'];
        $actor = $_SESSION['lms_username'] ?? 'Organizer';
        $stmt = $conn->prepare("UPDATE tbl_league SET is_archived=0, archived_at=NULL, active_unique=1 WHERE id=? AND created_by=?");
        $stmt->bind_param('ii', $id, $userId);
        if ($stmt->execute()) {
            $stmt->close();
            auditLog($conn, 'Restore League', "Restored league: $lname (ID $id)");
            recomputeAndPersistLeagueStat($conn, $id);
            echo json_encode(['status'=>201,'msg'=>'League restored successfully.']);
        } else {
            $duplicate = $stmt->errno === 1062;
            echo json_encode(['status'=>$duplicate ? 409 : 500,'msg'=>$duplicate ? 'Cannot restore this league because an active league with the same name, sport, and dates already exists.' : 'Unable to restore league.']);
            $stmt->close();
        }
        exit;
    }
    if (isset($_POST['archiveLeague'])) {
        $id = intval($_POST['league_id']);
        $actor = $_SESSION['lms_username'] ?? 'Organizer';
        $actorId = intval($_SESSION['lms_id'] ?? 0);
        // Ownership is enforced again in the UPDATE itself (defence in depth).
        $stmt = $conn->prepare("UPDATE tbl_league SET is_archived=1, archived_at=NOW(), active_unique=NULL WHERE id=? AND created_by=?");
        $stmt->bind_param('ii', $id, $actorId);
        if ($stmt->execute()) {
            $stmt->close();
            $nameStmt = $conn->prepare("SELECT league_name FROM tbl_league WHERE id=? AND created_by=? LIMIT 1");
            $nameStmt->bind_param('ii', $id, $actorId);
            $nameStmt->execute();
            $nameRow = $nameStmt->get_result()->fetch_assoc();
            $nameStmt->close();
            $lname = $nameRow['league_name'] ?? "ID $id";
            $log = $conn->prepare("INSERT INTO tbl_audit_log (user_name, user_id, action, details) VALUES (?,?,?,?)");
            $detail = "Archived league: $lname"; $action = 'Archive';
            $log->bind_param('siss', $actor, $actorId, $action, $detail); $log->execute(); $log->close();
            recomputeAndPersistLeagueStat($conn, $id);
            echo json_encode(['status'=>201,'msg'=>'League moved to archive.']);

        } else {
            echo json_encode(['status'=>500,'msg'=>$stmt->error]);
            $stmt->close();
        }
        exit;
    }
    if (isset($_POST['deleteLeague'])) {
        echo json_encode(['status'=>403,'msg'=>'Permanent deletion is disabled. Archive the league instead.']);
        exit;
    }
    if (isset($_POST['addTeam'])) {
        echo json_encode(['status'=>403,'msg'=>'Team applications must be submitted by a Team Representative.']);
        exit;
        $leagueId = intval($_POST['league_id']);
        $teamName = trim($_POST['team_name'] ?? '');
        $teamRepre = trim($_POST['team_repre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $orgId = $_SESSION['lms_id'];

        if (!$teamName || !$teamRepre || !$email || !$phone) {
            echo json_encode(['status' => 400, 'msg' => 'All fields are required.']);
            exit;
        }

        // Handle team image upload
        $imageName = '';
        if (!empty($_FILES['team_image']['name'])) {
            $imgMime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['team_image']['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!$imgMime || !in_array($imgMime, $allowed)) {
                echo json_encode(['status' => 400, 'msg' => 'Team image must be JPG, PNG, or WEBP.']);
                exit;
            }
            if (StorageService::isEnabled()) {
                $cloudTeam = StorageService::upload($_FILES['team_image']['tmp_name'], 'team_images', [
                    'public_id' => time() . '_' . uniqid(),
                ]);
                $imageName = $cloudTeam['secure_url'];
            } else {
                $uploadDir = __DIR__ . '/../../../assets/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = pathinfo($_FILES['team_image']['name'], PATHINFO_EXTENSION);
                $imageName = time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['team_image']['tmp_name'], $uploadDir . $imageName);
            }
        }

        // Insert team as Approved
        $stmt = $conn->prepare("INSERT INTO tbl_team (team_name, league_id, status, team_image, team_repre, email, phone, user_id) VALUES (?, ?, 'Approved', ?, ?, ?, ?, ?)");
        $stmt->bind_param('sissssi', $teamName, $leagueId, $imageName, $teamRepre, $email, $phone, $orgId);
        if (!$stmt->execute()) {
            echo json_encode(['status' => 500, 'msg' => $stmt->error]);
            $stmt->close();
            exit;
        }
        $teamId = $stmt->insert_id;
        $stmt->close();

        // Insert players
        $playerNames    = $_POST['player_name'] ?? [];
        $playerAges     = $_POST['player_age'] ?? [];
        $playerRoles    = $_POST['player_role'] ?? [];
        $playerJerseys  = $_POST['jersey_number'] ?? [];
        $playerGenders  = $_POST['gender'] ?? [];

        $uploadPlayerDir = __DIR__ . '/../../../assets/uploads/';
        $pStmt = $conn->prepare("INSERT INTO tbl_player (team_id, name, age, role, gender, jersey_number) VALUES (?, ?, ?, ?, ?, ?)");

        for ($i = 0; $i < count($playerNames); $i++) {
            $pName   = trim($playerNames[$i] ?? '');
            $pAge    = intval($playerAges[$i] ?? 0);
            $pRole   = trim($playerRoles[$i] ?? '');
            $pJersey = intval($playerJerseys[$i] ?? 0);
            $pGender = trim($playerGenders[$i] ?? '');

            $pStmt->bind_param('isisis', $teamId, $pName, $pAge, $pRole, $pGender, $pJersey);
            $pStmt->execute();
        }
        $pStmt->close();

        echo json_encode(['status' => 201, 'msg' => "$teamName has been added and approved."]);
        // Persist computed status after team approval/addition (may affect slots)
        recomputeAndPersistLeagueStat($conn, $leagueId);
        exit;

    }
    if (isset($_POST['closeRegistration']) || isset($_POST['reopenRegistration'])) {
        $id     = (int) ($_POST['league_id'] ?? 0);
        $userId = currentUserId();
        $reopen = isset($_POST['reopenRegistration']);

        if ($id <= 0) {
            echo json_encode(['status' => 400, 'msg' => 'League not found.']);
            exit;
        }

        $conn->begin_transaction();

        // Lock the row so two clicks cannot both pass the state checks.
        $stmt = $conn->prepare(
            "SELECT id, league_name, publication_status, is_archived, start_date, end_date,
                    regis_start, regis_end, max_team, registration_window_open
               FROM tbl_league
              WHERE id = ? AND created_by = ?
              LIMIT 1
              FOR UPDATE"
        );
        if (!$stmt) {
            $conn->rollback();
            echo json_encode(['status' => 500, 'msg' => 'Registration could not be updated. Please try again.']);
            exit;
        }
        $stmt->bind_param('ii', $id, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            echo json_encode(['status' => 500, 'msg' => 'Registration could not be updated. Please try again.']);
            exit;
        }
        $league = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$league) {
            $conn->rollback();
            echo json_encode(['status' => 404, 'msg' => 'League not found.']);
            exit;
        }

        $approvedCount = countLeagueApprovedTeams($conn, $id);

        if ($reopen) {
            $state = resolveRegistrationReopenState($league, $approvedCount);
            if (!$state['allowed']) {
                $conn->rollback();
                echo json_encode(['status' => 409, 'msg' => $state['reason']]);
                exit;
            }
            $newValue = 1;
            $oldValue = 0;
            $action   = 'Reopen Registration';
            $doneMsg  = 'Registration has been reopened.';
        } else {
            $summary = resolveRegistrationWindowSummary($league, $approvedCount);
            // A full window is already closed to new teams, but the organizer may
            // still flip the flag so the state is explicit in reports. An upcoming
            // window may be cancelled before it opens; Reopen can restore it.
            if (!$summary['isOpen'] && !in_array($summary['status'], ['Full', 'Upcoming'], true)) {
                $conn->rollback();
                $msg = $summary['status'] === 'Manually closed'
                    ? 'Registration is already closed.'
                    : $summary['note'];
                echo json_encode(['status' => 409, 'msg' => $msg]);
                exit;
            }
            $newValue = 0;
            $oldValue = 1;
            $action   = 'Close Registration';
            $doneMsg  = 'Registration has been closed.';
        }

        $upd = $conn->prepare(
            "UPDATE tbl_league
                SET registration_window_open = ?
              WHERE id = ? AND created_by = ? AND registration_window_open = ?"
        );
        if (!$upd) {
            $conn->rollback();
            echo json_encode(['status' => 500, 'msg' => 'Registration could not be updated. Please try again.']);
            exit;
        }
        $upd->bind_param('iiii', $newValue, $id, $userId, $oldValue);
        if (!$upd->execute()) {
            $upd->close();
            $conn->rollback();
            echo json_encode(['status' => 500, 'msg' => 'Registration could not be updated. Please try again.']);
            exit;
        }
        if ($upd->affected_rows !== 1) {
            $upd->close();
            $conn->rollback();
            echo json_encode([
                'status' => 409,
                'msg'    => $reopen ? 'Registration is already open.' : 'Registration is already closed.',
            ]);
            exit;
        }
        $upd->close();

        if (!recomputeAndPersistLeagueStat($conn, $id)) {
            $conn->rollback();
            error_log('[league] Unable to persist status after registration change for league ' . $id);
            echo json_encode(['status' => 500, 'msg' => 'Registration could not be updated. Please try again.']);
            exit;
        }

        auditLog(
            $conn,
            $action,
            ($reopen ? 'Reopened' : 'Closed') . ' registration for league "' . $league['league_name'] . '" (ID ' . $id . ').'
        );

        $conn->commit();
        echo json_encode(['status' => 200, 'msg' => $doneMsg]);
        exit;
    }
    if (isset($_POST['approved'])) {
        $team_id = intval($_POST["team_id"]);
        
        // Get team name + the league's capacity (checked before the status flips)
        $team_sql = "SELECT t.team_name, t.league_id, l.max_team FROM tbl_team t LEFT JOIN tbl_league l ON l.id = t.league_id WHERE t.id = ?";
        $team_stmt = $conn->prepare($team_sql);
        $team_stmt->bind_param("i", $team_id);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        $team_row = $team_result->fetch_assoc();
        $team_name = $team_row['team_name'] ?? 'Team';
        $team_stmt->close();

        $eligibilityStmt=$conn->prepare("SELECT l.min_player,COUNT(p.id) AS roster_count,SUM(CASE WHEN p.eligibility_status='Eligible' THEN 1 ELSE 0 END) AS eligible_count,SUM(CASE WHEN p.eligibility_status='Pending' THEN 1 ELSE 0 END) AS pending_count,SUM(CASE WHEN p.eligibility_status='Needs Correction' THEN 1 ELSE 0 END) AS correction_count,GROUP_CONCAT(CASE WHEN p.eligibility_status='Pending' THEN p.name END ORDER BY p.name SEPARATOR ', ') AS pending_names,GROUP_CONCAT(CASE WHEN p.eligibility_status='Needs Correction' THEN p.name END ORDER BY p.name SEPARATOR ', ') AS correction_names FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id LEFT JOIN tbl_player p ON CAST(p.team_id AS UNSIGNED)=t.id WHERE t.id=? GROUP BY l.id,l.min_player");
        $eligibilityStmt->bind_param('i',$team_id); $eligibilityStmt->execute();
        $eligibility=$eligibilityStmt->get_result()->fetch_assoc(); $eligibilityStmt->close();
        if(!$eligibility || (int)$eligibility['roster_count']===0) { echo json_encode(['status'=>422,'msg'=>'This team has no roster to approve.']); exit; }
        if((int)$eligibility['pending_count']>0) {
            // Inform the organizer that the roster needs review before approval
            echo json_encode(['status'=>422,'msg'=>'The team roster still needs review.']); exit;
        }
        if((int)$eligibility['correction_count']>0) {
            $names=$eligibility['correction_names'] ?: ((int)$eligibility['correction_count'].' player(s)');
            echo json_encode(['status'=>422,'msg'=>'Cannot approve yet. '.$names.' still needs correction. Return the application so the representative can correct or replace the player. Eligible players: '.(int)$eligibility['eligible_count'].'; required minimum: '.(int)$eligibility['min_player'].'.']); exit;
        }
        if((int)$eligibility['eligible_count']<(int)$eligibility['min_player']) {
            echo json_encode(['status'=>422,'msg'=>'Cannot approve this team with only '.(int)$eligibility['eligible_count'].' eligible player(s). This league requires at least '.(int)$eligibility['min_player'].'. Return the application so ineligible players can be replaced.']); exit;
        }

        /* Slots are filled by APPROVED teams only, so a Pending application no
           longer reserves one. Approving past tbl_league.max_team would
           overflow the league, so stop here with a clear message. */
        $teamLeagueId = (int)($team_row['league_id'] ?? 0);
        $teamMaxTeams = (int)($team_row['max_team'] ?? 0);
        if ($teamLeagueId > 0 && $teamMaxTeams > 0) {
            $approvedSoFar = countLeagueApprovedTeams($conn, $teamLeagueId);
            if ($approvedSoFar >= $teamMaxTeams) {
                echo json_encode(['status'=>409,'msg'=>'All '.$teamMaxTeams.' team slots are already filled by approved teams. Reject or remove an approved team before approving another registration.']); exit;
            }
        }
        
        $sql = "UPDATE `tbl_team` SET `status`='Approved',`remarks`=NULL,`reviewed_by`=?,`reviewed_at`=NOW() WHERE `id`=? AND LOWER(`status`)='pending'";
        $stmt = $conn->prepare($sql);
        $reviewerId = currentUserId();
        $stmt->bind_param("ii", $reviewerId, $team_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows !== 1) { echo json_encode(['status'=>409,'msg'=>'This registration is no longer Pending.']); exit; }
            try {
                notifyTeamApproved($conn, $team_id, $team_name);
            } catch (Throwable $e) {
                error_log('[notification] Approval notice failed for team ' . $team_id . ': ' . $e->getMessage());
            }
            auditLog($conn, 'Approve Registration', "Approved team registration: $team_name (ID $team_id)");
            $res = [
                'status' => 201,
                'msg' => "Team approved successfully",
            ];
            // Persist computed status after team approval (may affect slots)
            $leagueIdForRecompute = null;
            $leagueStmt = $conn->prepare("SELECT league_id FROM tbl_team WHERE id = ? LIMIT 1");
            if ($leagueStmt) {
                $leagueStmt->bind_param('i', $team_id);
                $leagueStmt->execute();
                $lr = $leagueStmt->get_result()->fetch_assoc();
                $leagueIdForRecompute = intval($lr['league_id'] ?? 0);
                $leagueStmt->close();
            }
            if ($leagueIdForRecompute) {
                recomputeAndPersistLeagueStat($conn, $leagueIdForRecompute);
            }

            echo json_encode($res);
            exit;

        }
    }
    if (isset($_POST['reject'])) {
        $team_id = intval($_POST["team_id"]);
        $remarks = trim($_POST['remarks'] ?? '');

        // Get team name
        $team_sql = "SELECT team_name FROM tbl_team WHERE id = ?";
        $team_stmt = $conn->prepare($team_sql);
        $team_stmt->bind_param("i", $team_id);
        $team_stmt->execute();
        $team_result = $team_stmt->get_result();
        $team_row = $team_result->fetch_assoc();
        $team_name = $team_row['team_name'] ?? 'Team';
        $team_stmt->close();

        if ($remarks === '') { echo json_encode(['status'=>400,'msg'=>'Rejection remarks are required.']); exit; }
        $sql = "UPDATE `tbl_team` SET `status`='Rejected',`remarks`=?,`reviewed_by`=?,`reviewed_at`=NOW() WHERE `id`=? AND LOWER(`status`)='pending'";
        $stmt = $conn->prepare($sql);
        $reviewerId = currentUserId();
        $stmt->bind_param("sii", $remarks, $reviewerId, $team_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows !== 1) { echo json_encode(['status'=>409,'msg'=>'This registration is no longer Pending.']); exit; }
            notifyTeamRejected($conn, $team_id, $team_name, $remarks);
            auditLog($conn, 'Reject Registration', "Rejected team registration: $team_name (ID $team_id). Remarks: $remarks");
            // Persist computed status after team rejection (may affect slots)
            $leagueIdForRecompute = null;
            $leagueStmt = $conn->prepare("SELECT league_id FROM tbl_team WHERE id = ? LIMIT 1");
            if ($leagueStmt) {
                $leagueStmt->bind_param('i', $team_id);
                $leagueStmt->execute();
                $lr = $leagueStmt->get_result()->fetch_assoc();
                $leagueIdForRecompute = intval($lr['league_id'] ?? 0);
                $leagueStmt->close();
            }
            if ($leagueIdForRecompute) {
                recomputeAndPersistLeagueStat($conn, $leagueIdForRecompute);
            }

            echo json_encode(['status' => 201, 'msg' => 'Team rejected successfully']);
            exit;

        }
    }
    if (isset($_POST['requestCorrection'])) {
        $team_id = intval($_POST['team_id'] ?? 0);
        /* The return reasons are optional: what the representative can actually
           fix is the players flagged "Needs Correction" (plus their documents,
           the team name/logo and team-level documents — see
           app/tr/action/reapplyTeam.php), so those players are the real reason
           for returning the application. */
        $submittedReasons = $_POST['return_reasons'] ?? [];
        if (!is_array($submittedReasons)) $submittedReasons=[];
        $allowedReasons=[
            'incomplete_team_information'=>'Incomplete team information',
            'invalid_team_document'=>'Invalid team-level document',
            'roster_below_minimum'=>'Roster is below the required minimum',
            'conflicting_team_information'=>'Duplicate or conflicting team information',
            'multiple_player_corrections'=>'Multiple players require correction',
            'other'=>'Other',
        ];
        $reasonLabels=[];
        foreach(array_unique($submittedReasons) as $reason) if(isset($allowedReasons[$reason])) $reasonLabels[]=$allowedReasons[$reason];
        $otherRemarks=trim((string)($_POST['other_remarks'] ?? ''));
        if(in_array('Other',$reasonLabels,true) && $otherRemarks==='') { echo json_encode(['status'=>400,'msg'=>'Enter the other return reason.']); exit; }
        if(mb_strlen($otherRemarks)>500) { echo json_encode(['status'=>400,'msg'=>'Other remarks must not exceed 500 characters.']); exit; }
        $remarks=implode(', ',$reasonLabels);
        if($otherRemarks!=='') $remarks.=': '.$otherRemarks;
        $teamStmt = $conn->prepare("SELECT t.team_name,t.league_id,l.league_name,l.min_player FROM tbl_team t JOIN tbl_league l ON l.id=t.league_id WHERE t.id=? LIMIT 1");
        $teamStmt->bind_param('i', $team_id); $teamStmt->execute();
        $team = $teamStmt->get_result()->fetch_assoc(); $teamStmt->close();
        if (!$team) { echo json_encode(['status'=>404,'msg'=>'Registration not found.']); exit; }

        /* An application can only be returned when at least one player is marked
           "Needs Correction": that is the only roster change the representative
           is allowed to make on resubmission, so without it there is nothing to
           correct and the organizer must approve or reject instead. */
        $correctionStmt = $conn->prepare("SELECT p.name FROM tbl_player p JOIN tbl_team t ON t.id = CAST(p.team_id AS UNSIGNED) WHERE t.id = ? AND p.eligibility_status = 'Needs Correction' ORDER BY p.name");
        $correctionStmt->bind_param('i', $team_id); $correctionStmt->execute();
        $correctionResult = $correctionStmt->get_result();
        $correctionNames = [];
        while ($correctionRow = $correctionResult->fetch_assoc()) $correctionNames[] = (string) $correctionRow['name'];
        $correctionStmt->close();
        if (!$correctionNames) {
            echo json_encode(['status'=>409,'msg'=>'No player is marked "Needs Correction", so there is nothing for the representative to correct. Mark at least one player as Needs Correction before returning this application.']);
            exit;
        }
        // No reason picked (the review screen just asks for confirmation): name the players instead.
        if ($remarks === '') {
            $listed = array_slice($correctionNames, 0, 10);
            $remarks = 'Player corrections required: ' . implode(', ', $listed);
            if (count($correctionNames) > count($listed)) $remarks .= ' and ' . (count($correctionNames) - count($listed)) . ' more';
            $remarks = mb_substr($remarks . '.', 0, 500);
        }

        $reviewerId = currentUserId();
        $stmt = $conn->prepare("UPDATE tbl_team SET status='Correction Requested',remarks=?,correction_due_at=DATE_ADD(NOW(),INTERVAL 72 HOUR),correction_expired_at=NULL,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND LOWER(status)='pending'");
        $stmt->bind_param('sii', $remarks, $reviewerId, $team_id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) { $stmt->close(); echo json_encode(['status'=>409,'msg'=>'This registration is no longer Pending.']); exit; }
        $stmt->close();
        $deadline = date('M d, Y h:i A', strtotime('+72 hours'));
        notifyTeamCorrectionRequested($conn, $team_id, $team['team_name'], $remarks . ". Correction deadline: $deadline");
        auditLog($conn, 'Request Registration Correction', "Returned team registration {$team['team_name']} (ID $team_id). Remarks: $remarks");
        echo json_encode(['status'=>201,'msg'=>"Registration returned for correction. The representative has until $deadline."]);
        exit;
    }
    if (isset($_POST['playerEligibility'])) {
        $teamId=(int)($_POST['team_id'] ?? 0); $playerId=(int)($_POST['player_id'] ?? 0);
        $decision=trim((string)($_POST['decision'] ?? '')); $remarks=trim((string)($_POST['remarks'] ?? ''));
        $allowed=['Eligible','Needs Correction','Ineligible'];
        if(!$teamId || !$playerId || !in_array($decision,$allowed,true)) { echo json_encode(['status'=>400,'msg'=>'Invalid player eligibility decision.']); exit; }
        if($decision!=='Eligible' && $remarks==='') { echo json_encode(['status'=>400,'msg'=>'Remarks are required for this decision.']); exit; }
        if(mb_strlen($remarks)>500) { echo json_encode(['status'=>400,'msg'=>'Remarks must not exceed 500 characters.']); exit; }
        $playerStmt=$conn->prepare("SELECT p.name,t.team_name,t.status FROM tbl_player p JOIN tbl_team t ON t.id=CAST(p.team_id AS UNSIGNED) WHERE p.id=? AND t.id=? LIMIT 1");
        $playerStmt->bind_param('ii',$playerId,$teamId); $playerStmt->execute(); $player=$playerStmt->get_result()->fetch_assoc(); $playerStmt->close();
        if(!$player) { echo json_encode(['status'=>404,'msg'=>'Player was not found in this registration.']); exit; }
        if(strtolower($player['status'])!=='pending') { echo json_encode(['status'=>409,'msg'=>'Player decisions can only be changed while the application is Pending.']); exit; }
        $reviewer=currentUserId(); $remarksValue=$remarks!==''?$remarks:null;
        $conn->begin_transaction();
        try {
            $update=$conn->prepare("UPDATE tbl_player SET eligibility_status=?,eligibility_remarks=?,eligibility_reviewed_by=?,eligibility_reviewed_at=NOW() WHERE id=? AND team_id=?");
            $teamIdString=(string)$teamId; $update->bind_param('ssiis',$decision,$remarksValue,$reviewer,$playerId,$teamIdString); $update->execute();
            $update->close();
            auditLog($conn,'Review Player Eligibility',"Marked player {$player['name']} (ID $playerId) as $decision for team {$player['team_name']} (ID $teamId).".($remarks!==''?" Remarks: $remarks":''));
            $conn->commit();
            $message=$decision==='Needs Correction'
                ? 'Player marked for correction. Review the remaining players, then return the application.'
                : 'Player eligibility updated.';
            echo json_encode(['status'=>201,'msg'=>$message]); exit;
        } catch(Throwable $e) {
            $conn->rollback(); error_log('[PLAYER ELIGIBILITY] '.$e->getMessage());
            echo json_encode(['status'=>500,'msg'=>'The player eligibility decision could not be saved.']); exit;
        }
    }
    if (isset($_POST['documentReview'])) {
        // Rate limit: 10 document reviews per user per minute
        $reviewLimiter = new RateLimiter('document_review', 10, 60);
        $reviewIdentifier = 'user:' . currentUserId();
        if (!$reviewLimiter->check($reviewIdentifier)) {
            $retryAfter = $reviewLimiter->retryAfter($reviewIdentifier);
            echo json_encode(['status' => 429, 'msg' => 'Too many review actions. Please wait ' . $retryAfter . ' seconds.']);
            exit;
        }
        $reviewLimiter->record($reviewIdentifier);

        $documentId=(int)($_POST['document_id'] ?? 0);
        $decision=trim((string)($_POST['decision'] ?? ''));
        $remarks=trim((string)($_POST['remarks'] ?? ''));
        $allowed=['Accepted','Rejected'];
        if(!$documentId || !in_array($decision,$allowed,true)) { echo json_encode(['status'=>400,'msg'=>'Invalid document review decision.']); exit; }
        if($decision==='Rejected' && $remarks==='') { echo json_encode(['status'=>400,'msg'=>'Remarks are required when rejecting a document.']); exit; }
        if(mb_strlen($remarks)>500) { echo json_encode(['status'=>400,'msg'=>'Remarks must not exceed 500 characters.']); exit; }
        // Verify the document belongs to a team in the organizer's league
        $reviewer=(int)currentUserId();
        $docStmt=$conn->prepare("SELECT d.id,d.original_name,d.quality_status,d.review_status,t.team_name,t.status AS team_status,l.league_name FROM tbl_submitted_document d JOIN tbl_team t ON t.id=d.team_id JOIN tbl_league l ON l.id=t.league_id WHERE d.id=? AND l.created_by=? LIMIT 1");
        $docStmt->bind_param('ii',$documentId,$reviewer); $docStmt->execute();
        $doc=$docStmt->get_result()->fetch_assoc(); $docStmt->close();
        if(!$doc) { echo json_encode(['status'=>404,'msg'=>'Document not found or does not belong to your league.']); exit; }
        if(strtolower($doc['team_status'])!=='pending') { echo json_encode(['status'=>409,'msg'=>'Document review is only available while the team application is Pending.']); exit; }
        $remarksValue=$remarks!==''?$remarks:null;
        $conn->begin_transaction();
        try {
            $update=$conn->prepare("UPDATE tbl_submitted_document SET review_status=?,review_remarks=? WHERE id=?");
            $update->bind_param('ssi',$decision,$remarksValue,$documentId); $update->execute(); $update->close();
            auditLog($conn,'Review Document',"Marked document '{$doc['original_name']}' (ID $documentId) as $decision for team {$doc['team_name']}. ".($remarks!==''?" Remarks: $remarks":''));
            $conn->commit();
            echo json_encode(['status'=>201,'msg'=>"Document marked as $decision."]); exit;
        } catch(Throwable $e) {
            $conn->rollback(); error_log('[DOCUMENT REVIEW] '.$e->getMessage());
            echo json_encode(['status'=>500,'msg'=>'The document review could not be saved.']); exit;
        }
    }
}