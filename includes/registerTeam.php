<?php
include '../config/dbconnect.php';
require_once '../config/auth_guard.php';
require_once '../config/rateLimiter.php';
include '../includes/documentValidator.php';
include '../includes/eligibilityValidator.php';
include '../includes/notificationHelper.php';

class RegistrationValidationException extends RuntimeException
{
    public function __construct(string $message, public int $responseStatus = 400, public array $fieldErrors = [])
    {
        parent::__construct($message);
    }
}

function cleanupRegistrationFiles(array $paths): void
{
    foreach (array_unique($paths) as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    // A request bigger than post_max_size reaches PHP with an empty $_POST and
    // $_FILES. Say so plainly instead of falling through to the CSRF guard,
    // which would answer "your form session expired" for a size problem.
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        http_response_code(413);
        header('Content-Type: application/json');
        $postLimit = trim((string) ini_get('post_max_size'));
        echo json_encode([
            'status' => 413,
            'msg'    => 'The upload is too large for the server' . ($postLimit !== '' ? ' (limit ' . $postLimit . ')' : '')
                        . '. Remove a few documents or take smaller photos, then try again.',
        ]);
        exit;
    }

    $uploadedFiles = [];
    $transactionStarted = false;
    try {
        requireJsonRole([2]);
        requireActiveAccount($conn, true);
        requireJsonCsrf();

        // Rate limit: 5 registration attempts per user per 5-minute window
        $registrationLimiter = new RateLimiter('team_registration', 5, 300);
        $registrationIdentifier = 'user:' . (currentUserId() ?: 'anon');
        if (!$registrationLimiter->check($registrationIdentifier)) {
            $retryAfter = $registrationLimiter->retryAfter($registrationIdentifier);
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['status' => 429, 'msg' => 'Too many registration attempts. Please try again in ' . $retryAfter . ' seconds.']);
            exit;
        }
        $registrationLimiter->record($registrationIdentifier);

        $leagueId = intval($_POST['id'] ?? 0);

        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $userId = currentUserId() ?: null;

        if (!$userId) {
            echo json_encode(["status" => 401, "msg" => "User not authenticated"]);
            exit;
        }

        // Everything this request needs from the session has been read. Release
        // the session lock before the heavy work so the page's notification poll
        // (and the representative's other tabs) do not queue behind this upload.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $leagueDocumentRequirements=[];
        $requirementStmt=$conn->prepare("SELECT r.id,r.applies_to,r.is_required,u.name FROM tbl_league_document_requirement r JOIN tbl_utility u ON u.id=r.document_type_id WHERE r.league_id=? ORDER BY r.id");
        $requirementStmt->bind_param('i',$leagueId); $requirementStmt->execute(); $requirementResult=$requirementStmt->get_result();
        while($requirement=$requirementResult->fetch_assoc()) $leagueDocumentRequirements[]=$requirement;
        $requirementStmt->close();

        $availabilityStmt=$conn->prepare("SELECT id,publication_status,is_archived,registration_window_open,start_date,end_date,regis_start,regis_end,min_player,max_player,max_team FROM tbl_league WHERE id=? LIMIT 1");
        $availabilityStmt->bind_param('i',$leagueId); $availabilityStmt->execute(); $availability=$availabilityStmt->get_result()->fetch_assoc(); $availabilityStmt->close();
        if (!$availability || $availability['publication_status']!=='Published' || (int)$availability['is_archived']===1) {
            echo json_encode(['status'=>403,'state'=>'closed','msg'=>'This league is not currently available for registration.']); exit;
        }

        // The registration window, the league's live status and the organizer's
        // manual close switch are all resolved by the shared gate
        // (includes/registrationGate.php) — the same rules the league hero
        // button and app/tr/register_team.php use. A date-only regis_end is
        // inclusive of the whole closing day, so the POST can no longer reject
        // an application on the last day of a window the UI still shows as open.
        require_once __DIR__ . '/../config/leagueStatus.php';
        $leagueLiveStatus = getLeagueStatus($availability);
        error_log(sprintf(
            '[LMS][Registration] league_id=%d user_id=%s computed_status=%s registration_window_open=%s today=%s',
            $leagueId,
            $userId ?? 'null',
            $leagueLiveStatus,
            $availability['registration_window_open'] ?? 'null',
            date('Y-m-d')
        ));

        $windowCheck = validateRegistrationWindow($availability, time());
        if (!$windowCheck['valid']) {
            echo json_encode([
                'status' => 403,
                'state'  => $windowCheck['state'] ?? 'closed',
                'msg'    => $windowCheck['message'],
            ]);
            exit;
        }

        $today = date('Y-m-d');
        if (!empty($availability['end_date']) && $today > $availability['end_date']) {
            echo json_encode(['status'=>403,'state'=>'closed','msg'=>'This league has already ended.']); exit;
        }

        // Check duplicate registration
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_team WHERE league_id = ? AND user_id = ? AND LOWER(status) NOT IN ('cancelled','canceled','expired')");
        $checkStmt->bind_param("ii", $leagueId, $userId);
        $checkStmt->execute();
        $checkRow = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($checkRow['count'] > 0) {
            echo json_encode([
                "status" => 400,
                "msg"    => "You have already registered a team in this league. A team representative can only register one team per league."
            ]);
            exit;
        }

        $rawTeamName = $_POST['team_name'] ?? '';
        if (!is_scalar($rawTeamName)) {
            throw new RegistrationValidationException('The team name is invalid.');
        }
        $normalizedTeamName = preg_replace('/\s+/', ' ', (string)$rawTeamName);
        if ($normalizedTeamName === null) {
            throw new RegistrationValidationException('The team name is invalid.');
        }
        $teamName = trim($normalizedTeamName);
        if (mb_strlen($teamName) < 2 || mb_strlen($teamName) > 100) {
            throw new RegistrationValidationException('Team name must contain 2 to 100 characters.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/u', $teamName)) {
            throw new RegistrationValidationException('Team name contains unsupported characters.');
        }

        $teamNameStmt = $conn->prepare("SELECT COUNT(*) AS count FROM tbl_team WHERE league_id=? AND LOWER(TRIM(team_name))=LOWER(?) AND LOWER(status) NOT IN ('cancelled','canceled','expired')");
        $teamNameStmt->bind_param('is', $leagueId, $teamName);
        $teamNameStmt->execute();
        $teamNameExists = (int)($teamNameStmt->get_result()->fetch_assoc()['count'] ?? 0);
        $teamNameStmt->close();
        if ($teamNameExists > 0) {
            throw new RegistrationValidationException('A team with this name already has an active application in this league.');
        }

        $rawPlayerNames = $_POST['player_name'] ?? [];
        $rawPlayerBirthdates = $_POST['player_birthdate'] ?? [];
        $rawPlayerRoles = $_POST['player_role'] ?? [];
        $rawPlayerGenders = $_POST['gender'] ?? [];
        $rawPlayerJerseys = $_POST['jersey_number'] ?? [];
        if (!is_array($rawPlayerNames) || !is_array($rawPlayerBirthdates) || !is_array($rawPlayerRoles) || !is_array($rawPlayerGenders) || !is_array($rawPlayerJerseys)) {
            throw new RegistrationValidationException('The roster data is invalid. Refresh the page and try again.');
        }

        $playerNames = array_values($rawPlayerNames);
        $playerBirthdates = array_values($rawPlayerBirthdates);
        $playerAges = [];
        $playerRoles = array_values($rawPlayerRoles);
        $playerGenders = array_values($rawPlayerGenders);
        $playerJerseys = array_values($rawPlayerJerseys);
        $playerCount = count($playerNames);
        $minimumPlayers = max(1, (int)($availability['min_player'] ?? 5));
        $configuredMaximum = (int)($availability['max_player'] ?? 0);
        $maximumPlayers = $configuredMaximum > 0 ? max($minimumPlayers, $configuredMaximum) : 0;
        if (count($playerBirthdates) !== $playerCount || count($playerRoles) !== $playerCount || count($playerGenders) !== $playerCount || count($playerJerseys) !== $playerCount) {
            throw new RegistrationValidationException('The roster is incomplete. Refresh the page and try again.');
        }
        if ($playerCount < $minimumPlayers || ($maximumPlayers > 0 && $playerCount > $maximumPlayers)) {
            $range = $maximumPlayers > 0 && $maximumPlayers === $minimumPlayers
                ? "exactly {$minimumPlayers}"
                : ($maximumPlayers > 0 ? "{$minimumPlayers} to {$maximumPlayers}" : "at least {$minimumPlayers}");
            throw new RegistrationValidationException("This league requires {$range} players per team. You submitted {$playerCount}.");
        }

        $seenNames = [];
        $seenJerseys = [];
        foreach ($playerNames as $index => $playerName) {
            $rowNumber = $index + 1;
            if (!is_scalar($playerName) || !is_scalar($playerRoles[$index]) || !is_scalar($playerGenders[$index]) || !is_scalar($playerBirthdates[$index]) || !is_scalar($playerJerseys[$index])) {
                throw new RegistrationValidationException("Player {$rowNumber} contains invalid values.");
            }

            $playerNames[$index] = trim((string)$playerName);
            $playerRoles[$index] = trim((string)$playerRoles[$index]);
            $playerGenders[$index] = trim((string)$playerGenders[$index]);
            if (mb_strlen($playerNames[$index]) < 2 || mb_strlen($playerNames[$index]) > 100) {
                throw new RegistrationValidationException("Player {$rowNumber}: name must contain 2 to 100 characters.");
            }
            if (mb_strlen($playerRoles[$index]) < 2 || mb_strlen($playerRoles[$index]) > 50) {
                throw new RegistrationValidationException("Player {$rowNumber}: role must contain 2 to 50 characters.");
            }
            if (preg_match('/[\x00-\x1F\x7F]/u', $playerRoles[$index])) {
                throw new RegistrationValidationException("Player {$rowNumber}: role contains unsupported characters.");
            }
            if (!in_array($playerGenders[$index], ['Male', 'Female'], true)) {
                throw new RegistrationValidationException("Player {$rowNumber}: select a valid gender.");
            }
            $birthdateValue = trim((string)$playerBirthdates[$index]);
            $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdateValue);
            $birthdateErrors = DateTimeImmutable::getLastErrors();
            if (!$birthdate || ($birthdateErrors !== false && ($birthdateErrors['warning_count'] > 0 || $birthdateErrors['error_count'] > 0)) || $birthdate->format('Y-m-d') !== $birthdateValue) {
                throw new RegistrationValidationException("Player {$rowNumber}: enter a valid birthdate.");
            }
            $leagueReferenceDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$availability['start_date']) ?: new DateTimeImmutable('today');
            if ($birthdate > $leagueReferenceDate) {
                throw new RegistrationValidationException("Player {$rowNumber}: birthdate cannot be after the league start date.");
            }
            $playerBirthdates[$index] = $birthdateValue;
            $playerAges[$index] = $birthdate->diff($leagueReferenceDate)->y;
            if ($playerAges[$index] < 1 || $playerAges[$index] > 100) {
                throw new RegistrationValidationException("Player {$rowNumber}: calculated age must be from 1 to 100 at league start.");
            }
            if (filter_var($playerJerseys[$index], FILTER_VALIDATE_INT) === false || (int)$playerJerseys[$index] < 0 || (int)$playerJerseys[$index] > 999) {
                throw new RegistrationValidationException("Player {$rowNumber}: jersey number must be a whole number from 0 to 999.");
            }

            $playerJerseys[$index] = (int)$playerJerseys[$index];
            $nameKey = mb_strtolower(preg_replace('/\s+/', ' ', $playerNames[$index]));
            if (isset($seenNames[$nameKey])) {
                throw new RegistrationValidationException("Player {$rowNumber}: this player name is already listed in the roster.");
            }
            if (isset($seenJerseys[$playerJerseys[$index]])) {
                throw new RegistrationValidationException("Player {$rowNumber}: jersey number {$playerJerseys[$index]} is already assigned to another player.");
            }
            $seenNames[$nameKey] = true;
            $seenJerseys[$playerJerseys[$index]] = true;
        }

        // Team barangay / school (ANP2026 residency + one-team-per-barangay rule)
        $teamBarangay = trim((string)($_POST['barangay'] ?? ''));
        if (mb_strlen($teamBarangay) > 120) {
            throw new RegistrationValidationException('The barangay / school name must not exceed 120 characters.');
        }
        $rawPlayerBarangays = $_POST['player_barangay'] ?? [];
        $playerBarangays = is_array($rawPlayerBarangays) ? array_values($rawPlayerBarangays) : [];

        // Sport-specific jersey range (Volleyball: numbers 1–99, §5.5)
        if (function_exists('getLeagueSportProfile')) {
            $jerseyCheck = validateJerseyRange($playerJerseys, getLeagueSportProfile($conn, $leagueId));
            if (!($jerseyCheck['valid'] ?? false)) {
                throw new RegistrationValidationException((string)$jerseyCheck['message']);
            }
        }

        // Validate eligibility
        $eligibilityCheck = validateAllEligibility(
            $conn,
            $leagueId,
            $playerNames,
            $playerAges,
            $playerGenders,
            [
                'roles'      => $playerRoles,
                'birthdates' => $playerBirthdates,
                'barangay'   => $teamBarangay,
            ]
        );


        if (!($eligibilityCheck['valid'] ?? false)) {
            echo json_encode([
                "status" => 400,
                "msg"    => (string)($eligibilityCheck['message'] ?? 'Eligibility validation failed')
            ]);
            exit;
        }

        // Team image upload with validation
        $imageName = null;
        $teamImage = $_FILES['team_image'] ?? null;
        if (!$teamImage || trim((string)($teamImage['name'] ?? '')) === '') {
            throw new RegistrationValidationException('A team image is required.');
        }
        $imageUploadError = (int)($teamImage['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($imageUploadError !== UPLOAD_ERR_OK) {
            $uploadMessages = [
                UPLOAD_ERR_INI_SIZE => 'The team image exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'The team image is too large.',
                UPLOAD_ERR_PARTIAL => 'The team image upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'A team image is required.',
            ];
            throw new RegistrationValidationException($uploadMessages[$imageUploadError] ?? 'The team image could not be uploaded.');
        }

        $maxTeamImageSize = 5 * 1024 * 1024;
        if ((int)($teamImage['size'] ?? 0) <= 0 || (int)$teamImage['size'] > $maxTeamImageSize) {
            throw new RegistrationValidationException('The team image must be no larger than 5 MB.');
        }

        $teamImageTmp = (string)($teamImage['tmp_name'] ?? '');
        if ($teamImageTmp === '' || !is_uploaded_file($teamImageTmp)) {
            throw new RegistrationValidationException('The uploaded team image is invalid.');
        }

        $imgExt = strtolower(pathinfo((string)$teamImage['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $imgMime = (string)$finfo->file($teamImageTmp);
        $allowedImageTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        if (!isset($allowedImageTypes[$imgExt]) || $allowedImageTypes[$imgExt] !== $imgMime) {
            throw new RegistrationValidationException('The team image must be a valid JPG, PNG, WebP, or GIF file.');
        }

        if (StorageService::isEnabled()) {
            $cloudTeamImg = StorageService::upload($teamImageTmp, 'team_images', [
                'public_id' => time() . '_' . bin2hex(random_bytes(4)),
            ]);
            $imageName = $cloudTeamImg['secure_url'];
        } else {
            $uploadDir = realpath(__DIR__ . '/../assets/uploads/');
            if (!$uploadDir) {
                mkdir(__DIR__ . '/../assets/uploads/', 0755, true);
                $uploadDir = realpath(__DIR__ . '/../assets/uploads/');
            }
            $imageName = time().'_'.bin2hex(random_bytes(4)).'.'.$imgExt;
            $targetFile = $uploadDir.DIRECTORY_SEPARATOR.$imageName;
            if (!move_uploaded_file($teamImageTmp, $targetFile)) {
                throw new RuntimeException('Failed to store the team image.');
            }
            @chmod($targetFile, 0644);
            $uploadedFiles[] = $targetFile;
        }

        $typedUploads=[]; $legacyPlayerDocs=[]; $preliminaryDocumentFlags=[]; $documentFieldErrors=[];
        $documentDir = documentStorageDirectory();
        if ($leagueDocumentRequirements) {
            foreach ($leagueDocumentRequirements as $requirement) {
                $reqId=(int)$requirement['id'];
                if ($requirement['applies_to']==='team') {
                    $name=$_FILES['team_documents']['name'][$reqId] ?? '';
                    if ($requirement['is_required'] && $name==='') throw new RegistrationValidationException($requirement['name'].' is required for the team.');
                    if ($name==='') continue;
                    $tmp=$_FILES['team_documents']['tmp_name'][$reqId] ?? ''; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    $files=['name'=>[$name],'tmp_name'=>[$tmp],'error'=>[$_FILES['team_documents']['error'][$reqId]],'size'=>[$_FILES['team_documents']['size'][$reqId]]];
                    $checked=validatePlayerDocuments($files,[$requirement['name']],[$requirement['name']]);
                    if (empty($checked['valid'][0])) {
                        $issue=$checked['issues'][0] ?? [];
                        $documentFieldErrors[]=['scope'=>'team','requirement_id'=>$reqId,'name'=>'team_documents['.$reqId.']','index'=>0,'message'=>documentQualityMessage($issue)];
                        continue;
                    }
                    $uploadedFiles[]=$documentDir.DIRECTORY_SEPARATOR.$checked['valid'][0];
                    $quality=$checked['quality'][0] ?? 'Pending'; $qualityDetails=$checked['quality_details'][0] ?? '';
                    $qualityFlag=documentQualityFlag($requirement['name'],$quality,$qualityDetails,isset($checked['unavailable'][0]));
                    if ($qualityFlag!==null) $preliminaryDocumentFlags[]=$qualityFlag;
                    $typedUploads[]=['requirement_id'=>$reqId,'player_index'=>null,'stored'=>$checked['valid'][0],'original'=>$name,'mime'=>$mime,'quality'=>$quality,'metrics'=>$checked['quality_metrics'][0] ?? null];
                } else {
                    $names=$_FILES['player_documents']['name'][$reqId] ?? [];
                    foreach ($playerNames as $i=>$playerName) if ($requirement['is_required'] && empty($names[$i])) throw new RegistrationValidationException($requirement['name'].' is required for player '.($i+1).' ('.$playerName.').');
                    $files=['name'=>$names,'tmp_name'=>$_FILES['player_documents']['tmp_name'][$reqId] ?? [],'error'=>$_FILES['player_documents']['error'][$reqId] ?? [],'size'=>$_FILES['player_documents']['size'][$reqId] ?? []];
                    $mimes=[]; foreach(($files['tmp_name'] ?? []) as $i=>$tmp) if($tmp) $mimes[$i]=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    $checked=validatePlayerDocuments($files,$playerNames,array_fill_keys(array_keys($names),$requirement['name']));
                    foreach($checked['valid'] as $storedName) $uploadedFiles[]=$documentDir.DIRECTORY_SEPARATOR.$storedName;
                    foreach ($playerNames as $i=>$playerName) {
                        if (!empty($names[$i]) && empty($checked['valid'][$i])) {
                            $issue=$checked['issues'][$i] ?? [];
                            $documentFieldErrors[]=['scope'=>'player','requirement_id'=>$reqId,'player_index'=>$i,'name'=>'player_documents['.$reqId.'][]','index'=>$i,'message'=>documentQualityMessage($issue)];
                            continue;
                        }
                        if (!empty($checked['valid'][$i])) {
                            $quality=$checked['quality'][$i] ?? 'Pending'; $qualityDetails=$checked['quality_details'][$i] ?? '';
                            $qualityFlag=documentQualityFlag($requirement['name'].' for '.$playerName,$quality,$qualityDetails,isset($checked['unavailable'][$i]));
                            if ($qualityFlag!==null) $preliminaryDocumentFlags[]=$qualityFlag;
                            $typedUploads[]=['requirement_id'=>$reqId,'player_index'=>$i,'stored'=>$checked['valid'][$i],'original'=>$names[$i],'mime'=>$mimes[$i] ?? 'application/octet-stream','quality'=>$quality,'metrics'=>$checked['quality_metrics'][$i] ?? null];
                            $legacyPlayerDocs[$i] ??= $checked['valid'][$i];
                        }
                    }
                }
            }
            if ($documentFieldErrors) {
                throw new RegistrationValidationException('This document appears to be unreadable or severely blurry.',422,$documentFieldErrors);
            }
            $validation=['valid'=>$legacyPlayerDocs,'blurry'=>[]];
        } else {
            // Compatibility for leagues created before typed requirements existed.
            $docNames = $_FILES['player_document']['name'] ?? [];
            foreach ($playerNames as $i => $name) if (empty($docNames[$i])) throw new RegistrationValidationException('Player '.($i+1).' is missing an eligibility document.');
            $validation = validatePlayerDocuments($_FILES['player_document'] ?? [], $playerNames);
            foreach($validation['valid'] as $storedName) $uploadedFiles[]=$documentDir.DIRECTORY_SEPARATOR.$storedName;
            if (!empty($validation['blurry'])) {
                foreach($validation['issues'] as $failedIndex=>$issue) {
                    $documentFieldErrors[]=['scope'=>'legacy_player','player_index'=>(int)$failedIndex,'name'=>'player_document[]','index'=>(int)$failedIndex,'message'=>documentQualityMessage($issue)];
                }
                throw new RegistrationValidationException('This document appears to be unreadable or severely blurry.',422,$documentFieldErrors);
            }
            foreach($validation['quality'] as $i=>$quality) {
                $qualityFlag=documentQualityFlag('Document for '.($playerNames[$i] ?? 'player'),$quality,$validation['quality_details'][$i] ?? 'Manual review is recommended.',isset($validation['unavailable'][$i]));
                if ($qualityFlag!==null) $preliminaryDocumentFlags[]=$qualityFlag;
            }
        }

        $conn->begin_transaction();
        $transactionStarted = true;

        // INSERT TEAM
        $stmt      = $conn->prepare("INSERT INTO tbl_team (team_name, league_id, status, team_image, user_id, team_repre, email, phone, bracket, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $status    = "Pending";
        $team_name = $teamName;
        $bracket   = '';
        $imageName = $imageName ?? '';

        $user_stmt = $conn->prepare("SELECT CONCAT(fname, ' ', lname) as full_name, email, phone FROM tbl_user WHERE id = ?");
        $user_stmt->bind_param('i', $userId);
        $user_stmt->execute();
        $user_row   = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();
        $team_repre = $user_row['full_name'] ?? '';
        $email      = $user_row['email'] ?? '';
        $phone      = $user_row['phone'] ?? '';

        $stmt->bind_param("sississsss", $team_name, $leagueId, $status, $imageName, $userId, $team_repre, $email, $phone, $bracket, $teamBarangay);

        try {
            $teamInserted = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $stmt->close();
                throw new RegistrationValidationException('You already have an active application for this league.', 409);
            }
            throw $e;
        }
        if (!$teamInserted) {
            error_log("[REGISTER] Team insert failed: " . sanitizeForLog($stmt->error), 0);
            $statementError = $stmt->errno;
            $stmt->close();
            if ($statementError === 1062) {
                throw new RegistrationValidationException('You already have an active application for this league.', 409);
            }
            throw new RuntimeException('Failed to register team.');
        }

        $team_id = $stmt->insert_id;
        $stmt->close();
        if ($preliminaryDocumentFlags) {
            $flagsJson=json_encode(['document_quality'=>$preliminaryDocumentFlags], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $flagStmt=$conn->prepare("UPDATE tbl_team SET preliminary_flags=? WHERE id=?");
            $flagStmt->bind_param('si',$flagsJson,$team_id); $flagStmt->execute(); $flagStmt->close();
        }
        error_log("[REGISTER] Team inserted successfully with ID: " . intval($team_id), 0);

        // INSERT PLAYERS
        $stmt2 = $conn->prepare("INSERT INTO tbl_player (team_id, name, age, birthdate, role, gender, jersey_number, document, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt2) {
            throw new Exception("Failed to prepare player insert statement: " . $conn->error);
        }

        $playerIds=[];
        foreach ($playerNames as $key => $name) {
            $age         = $playerAges[$key];
            $birthdate   = $playerBirthdates[$key];
            $role        = $playerRoles[$key];
            $gender      = $playerGenders[$key];
            $jersey      = $playerJerseys[$key];
            $document    = $validation['valid'][$key]     ?? null;
            $team_id_str = (string)$team_id;
            $age_str     = (string)$age;
            $jersey_str  = (string)$jersey;
            $playerBarangay = trim((string)($playerBarangays[$key] ?? ''));
            if ($playerBarangay === '') $playerBarangay = $teamBarangay;

            $stmt2->bind_param("sssssssss", $team_id_str, $name, $age_str, $birthdate, $role, $gender, $jersey_str, $document, $playerBarangay);

            if (!$stmt2->execute()) {
                throw new Exception("Failed to insert player: " . $stmt2->error);
            }
            $playerIds[$key]=$stmt2->insert_id;
            error_log("[REGISTER] Player '" . sanitizeForLog($name) . "' inserted successfully for team " . intval($team_id), 0);
        }

        $stmt2->close();

        if ($typedUploads) {
            $docInsert=$conn->prepare("INSERT INTO tbl_submitted_document(team_id,player_id,requirement_id,stored_name,original_name,mime_type,quality_status,quality_metrics,review_status) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach($typedUploads as $upload) {
                $playerId=$upload['player_index']===null ? null : ($playerIds[$upload['player_index']] ?? null);
                $metricsJson=$upload['metrics'] ?? null;
                $docReviewStatus = 'Pending';
                $docInsert->bind_param('iiissssss',$team_id,$playerId,$upload['requirement_id'],$upload['stored'],$upload['original'],$upload['mime'],$upload['quality'],$metricsJson,$docReviewStatus);
                if(!$docInsert->execute()) throw new Exception('Failed to store typed document metadata.');
            }
            $docInsert->close();
        }

        // Send notification (non-critical). createNotification() logs and
        // returns false on failure instead of throwing, so this guard only
        // protects the registration from a broken connection.
        try {
            notifyTeamRegistration($conn, $leagueId, $team_name, $team_id);
        } catch (Throwable $notifEx) {
            error_log("[REGISTER] Warning: Notification failed (non-critical): " . sanitizeForLog($notifEx->getMessage()), 0);
        }

        $conn->commit();
        $transactionStarted = false;
        $uploadedFiles = [];

        $conn->close();

        echo json_encode(["status" => 201, "msg" => "Team and players registered successfully"]);
        exit;

    } catch (RegistrationValidationException $e) {
        if ($transactionStarted) $conn->rollback();
        cleanupRegistrationFiles($uploadedFiles);
        http_response_code($e->responseStatus);
        header('Content-Type: application/json');
        echo json_encode(["status" => $e->responseStatus, "msg" => $e->getMessage(), "field_errors" => $e->fieldErrors]);
        exit;
    } catch (Throwable $e) {
        if ($transactionStarted) $conn->rollback();
        cleanupRegistrationFiles($uploadedFiles);
        error_log("[REGISTER] Exception: " . sanitizeForLog(get_class($e) . ': ' . $e->getMessage()) . " | File: " . $e->getFile() . " | Line: " . $e->getLine(), 0);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(["status" => 500, "msg" => "An error occurred during registration. Please try again."]);
        exit;
    }
}
?>
