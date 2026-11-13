<?php
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/documentStorage.php';
require_once __DIR__ . '/storageService.php';

requireRole([1, 2], getProjectBasePath() . '/login.php');
requireActiveAccount($conn);

require_once __DIR__ . '/documentStream.php';

$source = (string)($_GET['source'] ?? 'player');
$recordId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$recordId || !in_array($source, ['player', 'typed'], true)) {
    http_response_code(400); exit('Invalid document request.');
}

if ($source === 'typed') {
    $stmt = $conn->prepare("SELECT d.stored_name,d.original_name,t.user_id,l.created_by FROM tbl_submitted_document d JOIN tbl_team t ON t.id=d.team_id JOIN tbl_league l ON l.id=t.league_id WHERE d.id=? LIMIT 1");
} else {
    $stmt = $conn->prepare("SELECT p.document AS stored_name,p.document AS original_name,t.user_id,l.created_by FROM tbl_player p JOIN tbl_team t ON t.id=p.team_id JOIN tbl_league l ON l.id=t.league_id WHERE p.id=? LIMIT 1");
}
$stmt->bind_param('i', $recordId); $stmt->execute();
$document = $stmt->get_result()->fetch_assoc(); $stmt->close();
$authorized = $document && ((currentRole() === 1 && (int)$document['created_by'] === currentUserId()) ||
                            (currentRole() === 2 && (int)$document['user_id'] === currentUserId()));
if (!$authorized) { http_response_code(403); exit('You are not authorized to view this document.'); }

$storedName = (string)$document['stored_name'];
$downloadName = documentDownloadName($document);

$mode = strtolower(trim((string) ($_GET['mode'] ?? '')));
if (!in_array($mode, ['preview', 'download'], true)) $mode = '';
$inline = $mode !== 'download';

// Cloudinary documents: preview/download are proxied through the app.
if (StorageService::isCloudinary($storedName)) {
    if ($mode === '') {
        // Legacy links keep the old behaviour of handing the browser the cloud file.
        header('Location: ' . $storedName);
        exit;
    }
    $cloud = fetchCloudinaryDocument($storedName);
    if ($cloud === null) {
        http_response_code(502);
        exit('The document could not be loaded right now. Please try again.');
    }
    $mimeSource = pathinfo($downloadName, PATHINFO_EXTENSION) !== '' ? $downloadName : $storedName;
    sendDocumentHeaders(documentMimeFromName($mimeSource, $cloud['mime']), strlen($cloud['body']), $inline, $downloadName);
    echo $cloud['body'];
    exit;
}

$path = documentStoredPath($storedName);
if (!$path) { http_response_code(404); exit('Document not found.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
sendDocumentHeaders($mime, (int) filesize($path), $inline, $downloadName);
readfile($path);
