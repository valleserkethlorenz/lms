<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../includes/notificationHelper.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
enforceRequestSizeLimit(1 * 1024 * 1024); // 1MB
addApiSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

/** Limits kept in sync with the composer in app/organizer/league_info.php. */
define('ANNOUNCEMENT_TITLE_MAX', 255);
define('ANNOUNCEMENT_CONTENT_MAX', 5000);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 405, 'msg' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

// Get announcements for a league - PUBLIC (no auth required)
if ($action === 'get') {
    $league_id = intval($_POST['league_id'] ?? 0);

    if (!$league_id) {
        echo json_encode(['status' => 400, 'msg' => 'Invalid league ID']);
        exit;
    }

    $sql = "SELECT id, title, content, created FROM tbl_announcement WHERE league_id = ? ORDER BY created DESC, id DESC";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['status' => 500, 'msg' => 'Database error']);
        exit;
    }

    $stmt->bind_param('i', $league_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $announcements[] = [
            'id'      => (int) $row['id'],
            'title'   => (string) $row['title'],
            'content' => (string) $row['content'],
            'created' => (string) $row['created'],
        ];
    }

    $stmt->close();
    $conn->close();
    echo json_encode(['status' => 200, 'announcements' => $announcements]);
    exit;
}

// Create and delete - PROTECTED (requires auth)
requireJsonRole([1]);
requireActiveAccount($conn, true);
requireJsonCsrf();
$userId = currentUserId();

// Create announcement
if ($action === 'create') {
    $league_id = intval($_POST['league_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));

    if (!$league_id || $title === '' || $content === '') {
        echo json_encode(['status' => 400, 'msg' => 'Please add both a title and a message.']);
        exit;
    }
    if (mb_strlen($title) > ANNOUNCEMENT_TITLE_MAX) {
        echo json_encode(['status' => 400, 'msg' => 'The title may not exceed ' . ANNOUNCEMENT_TITLE_MAX . ' characters.']);
        exit;
    }
    if (mb_strlen($content) > ANNOUNCEMENT_CONTENT_MAX) {
        echo json_encode(['status' => 400, 'msg' => 'The message may not exceed ' . ANNOUNCEMENT_CONTENT_MAX . ' characters.']);
        exit;
    }
    if (!organizerOwnsLeague($conn, $league_id)) {
        echo json_encode(['status' => 403, 'msg' => 'You may publish announcements only for your own leagues.']);
        exit;
    }

    $sql = "INSERT INTO tbl_announcement (league_id, title, content, created_by) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['status' => 500, 'msg' => 'Database error']);
        exit;
    }

    $stmt->bind_param('issi', $league_id, $title, $content, $userId);

    if ($stmt->execute()) {
        $announcementId = (int) $stmt->insert_id;

        // A failed notification must never discard a published announcement.
        try {
            notifyAnnouncement($conn, $league_id, $announcementId, $title, $content);
        } catch (Throwable $e) {
            error_log('[announcement] Notification failed for #' . $announcementId . ': ' . $e->getMessage());
        }

        $stmt->close();
        $conn->close();
        echo json_encode([
            'status'       => 201,
            'msg'          => 'Announcement published successfully.',
            'announcement' => [
                'id'      => $announcementId,
                'title'   => $title,
                'content' => $content,
                'created' => date('Y-m-d H:i:s'),
            ],
        ]);
        exit;
    }

    echo json_encode(['status' => 500, 'msg' => 'Failed to create announcement']);
    $stmt->close();
    $conn->close();
    exit;
}

// Delete announcement
if ($action === 'delete') {
    $announcement_id = intval($_POST['announcement_id'] ?? 0);

    if (!$announcement_id) {
        echo json_encode(['status' => 400, 'msg' => 'Invalid announcement ID']);
        exit;
    }

    // The organizer may remove any announcement that belongs to a league they
    // own, including posts originally created by an admin.
    $lookup = $conn->prepare("SELECT league_id FROM tbl_announcement WHERE id = ? LIMIT 1");

    if (!$lookup) {
        echo json_encode(['status' => 500, 'msg' => 'Database error']);
        exit;
    }

    $lookup->bind_param('i', $announcement_id);
    $lookup->execute();
    $announcement = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    if (!$announcement) {
        echo json_encode(['status' => 404, 'msg' => 'That announcement no longer exists.']);
        exit;
    }

    $league_id = (int) $announcement['league_id'];

    if (!organizerOwnsLeague($conn, $league_id, null, true)) {
        echo json_encode(['status' => 403, 'msg' => 'You may manage announcements only for your own leagues.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM tbl_announcement WHERE id = ? AND league_id = ? LIMIT 1");

    if (!$stmt) {
        echo json_encode(['status' => 500, 'msg' => 'Database error']);
        exit;
    }

    $stmt->bind_param('ii', $announcement_id, $league_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();

        // Drop the notifications that pointed at this announcement so the
        // notification centre never opens a deleted post.
        $cleanup = $conn->prepare("DELETE FROM tbl_notification WHERE type = 'announcement' AND related_id = ?");
        if ($cleanup) {
            $cleanup->bind_param('i', $announcement_id);
            $cleanup->execute();
            $cleanup->close();
        }

        $conn->close();
        echo json_encode(['status' => 200, 'msg' => 'Announcement deleted successfully.']);
        exit;
    }

    echo json_encode(['status' => 500, 'msg' => 'Failed to delete announcement']);
    $stmt->close();
    $conn->close();
    exit;
}

$conn->close();
echo json_encode(['status' => 400, 'msg' => 'Unsupported action.']);
?>
