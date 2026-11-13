<?php
/**
 * Shared Notification AJAX Endpoint
 * Handles notification operations for roles: 1 (Organizer), 2 (Team Rep), 3 (Admin), 4 (Committee)
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/apiSecurity.php';
require_once __DIR__ . '/notificationHelper.php';

enforceRequestSizeLimit(512 * 1024); // 512KB
addApiSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 405, 'msg' => 'Method not allowed']);
    exit;
}

requireJsonRole([1, 2, 3, 4]);
requireJsonCsrf();

$action = (string) ($_POST['action'] ?? '');
$userId = currentUserId();
$role   = currentRole();

if (!$userId) {
    echo json_encode(['status' => 401, 'msg' => 'Unauthorized']);
    exit;
}

// Get all notifications for user
if ($action === 'get_all') {
    $sql = "SELECT id, type, title, message, related_id, is_read, created 
            FROM tbl_notification 
            WHERE user_id = ? 
            ORDER BY created DESC 
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 500, 'msg' => 'Unable to load notifications']);
        exit;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        // Send real integers: the bell compares is_read === 0 client side.
        $row['id']      = (int) $row['id'];
        $row['is_read'] = (int) $row['is_read'];
        $row['related_id'] = $row['related_id'] === null ? null : (int) $row['related_id'];

        if ($role === 1) {
            // Organizer pages expect the league id in `?id=`; the raw related_id
            // is a team/schedule/announcement id, so hand the UI a ready link.
            $row['link'] = organizerNotificationUrl($conn, (string) $row['type'], (int) ($row['related_id'] ?? 0));
        } elseif ($role === 2) {
            $row['link'] = teamRepNotificationUrl($conn, (string) $row['type'], (int) ($row['related_id'] ?? 0), (string) $row['title']);
        } elseif ($role === 4) {
            // Committee pages open the assigned match (matches.php?id=) for
            // match-level notices and the hub for league-wide ones.
            $row['link'] = committeeNotificationUrl((string) $row['type'], (int) ($row['related_id'] ?? 0));
        }
        $notifications[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'status' => 200, 
        'notifications' => $notifications, 
        'unread_count' => notificationUnreadCount($conn, $userId)
    ]);
    exit;
}
// Mark notification as read
elseif ($action === 'mark_read') {
    $notification_id = (int) ($_POST['notification_id'] ?? 0);

    if ($notification_id <= 0) {
        echo json_encode(['status' => 400, 'msg' => 'Invalid notification ID']);
        exit;
    }

    // The user_id predicate keeps one account from touching another's row.
    $sql = "UPDATE tbl_notification SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 500, 'msg' => 'Failed to update notification']);
        exit;
    }
    $stmt->bind_param('ii', $notification_id, $userId);

    if ($stmt->execute()) {
        // Return the fresh total so the bell/badge can sync in one round trip.
        echo json_encode([
            'status' => 200,
            'msg' => 'Notification marked as read',
            'unread_count' => notificationUnreadCount($conn, $userId)
        ]);
    } else {
        echo json_encode(['status' => 500, 'msg' => 'Failed to update notification']);
    }
    $stmt->close();
}
// Mark all as read
elseif ($action === 'mark_all_read') {
    $sql = "UPDATE tbl_notification SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 500, 'msg' => 'Failed to update notifications']);
        exit;
    }
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 200, 'msg' => 'All notifications marked as read', 'unread_count' => 0]);
    } else {
        echo json_encode(['status' => 500, 'msg' => 'Failed to update notifications']);
    }
    $stmt->close();
}
// Get unread count
elseif ($action === 'get_unread_count') {
    echo json_encode(['status' => 200, 'unread_count' => notificationUnreadCount($conn, $userId)]);
}
else {
    echo json_encode(['status' => 400, 'msg' => 'Invalid action']);
}

$conn->close();
?>
