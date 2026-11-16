<?php
/**
 * League guideline document download (public).
 *
 * The league overview lists guideline files under "Documents". Local uploads
 * were already served with the HTML `download` attribute, but Cloudinary
 * uploads were linked directly with target="_blank" — the browser then *opened*
 * the PDF/image in a new tab instead of saving it, and the `download` attribute
 * is ignored for cross-origin URLs.
 *
 * This endpoint streams the file from either source with
 * `Content-Disposition: attachment` and the original file name, so clicking a
 * document always downloads it.
 *
 * Only documents of a published, non-archived league are served; organizer
 * drafts stay private.
 *
 * Usage: includes/downloadLeagueDocument.php?id=<tbl_league_guideline_document.id>
 */

require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/storageService.php';
require_once __DIR__ . '/documentStream.php';

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    http_response_code(400);
    exit('Invalid document request.');
}

$stmt = $conn->prepare(
    "SELECT g.stored_name, g.original_name
       FROM tbl_league_guideline_document g
       JOIN tbl_league l ON l.id = g.league_id
      WHERE g.id = ? AND g.is_archived = 0
        AND l.publication_status = 'Published' AND l.is_archived = 0
      LIMIT 1"
);
$stmt->bind_param('i', $documentId);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$storedName   = (string) ($document['stored_name'] ?? '');
$downloadName = documentDownloadName($document);

if (StorageService::isCloudinary($storedName)) {
    $cloud = fetchCloudinaryDocument($storedName);
    if ($cloud === null) {
        http_response_code(502);
        exit('The document could not be downloaded right now. Please try again.');
    }
    sendDocumentHeaders(documentMimeFromName($downloadName, $cloud['mime']), strlen($cloud['body']), false, $downloadName);
    echo $cloud['body'];
    exit;
}

// Local guideline uploads live in assets/documents/ (see
// app/organizer/action/leagueAction.php).
$safeName = basename($storedName);
if ($safeName === '' || $safeName !== $storedName) {
    http_response_code(404);
    exit('Document not found.');
}
$path = dirname(__DIR__) . '/assets/documents/' . $safeName;
if (!is_file($path)) {
    http_response_code(404);
    exit('Document not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
sendDocumentHeaders($mime, (int) filesize($path), false, $downloadName);
readfile($path);
