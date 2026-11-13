<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../../config/dbconnect.php';
require_once __DIR__ . '/../../../config/auth_guard.php';
require_once __DIR__ . '/../../../config/apiSecurity.php';
require_once __DIR__ . '/../../../includes/notificationHelper.php';
enforceRequestSizeLimit(512 * 1024); // 512KB
addApiSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireJsonRole([2]);
    requireJsonCsrf();
    $action = $_POST['action'] ?? '';
    $userId = currentUserId();

    if (!$userId) {
        echo json_encode(['status' => 401, 'msg' => 'Unauthorized']);
        exit;
    }

    // Get all notifications for user
    if ($action === 'get_all') {
        $sql = "SELECT id, type, title, message, related_id, is_read, created FROM tbl_notification 
                WHERE user_id = ? ORDER BY created DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $row['link'] = teamRepNotificationUrl($conn, (string) $row['type'], (int) ($row['related_id'] ?? 0), (string) $row['title']);
            $notifications[] = $row;
        }

        $unread_count = 0;
        $count_sql = "SELECT COUNT(*) as count FROM tbl_notification WHERE user_id = ? AND is_read = 0";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param('i', $userId);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $unread_count = $count_row['count'];

        echo json_encode(['status' => 200, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        $stmt->close();
        $count_stmt->close();
    }
    // Mark notification as read
    elseif ($action === 'mark_read') {
        $notification_id = intval($_POST['notification_id'] ?? 0);

        if (!$notification_id) {
            echo json_encode(['status' => 400, 'msg' => 'Invalid notification ID']);
            exit;
        }

        $sql = "UPDATE tbl_notification SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $notification_id, $userId);

        if ($stmt->execute()) {
            echo json_encode(['status' => 200, 'msg' => 'Notification marked as read']);
        } else {
            echo json_encode(['status' => 500, 'msg' => 'Failed to update notification']);
        }
        $stmt->close();
    }
    // Mark all as read
    elseif ($action === 'mark_all_read') {
        $sql = "UPDATE tbl_notification SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);

        if ($stmt->execute()) {
            echo json_encode(['status' => 200, 'msg' => 'All notifications marked as read']);
        } else {
            echo json_encode(['status' => 500, 'msg' => 'Failed to update notifications']);
        }
        $stmt->close();
    }
    // Get unread count
    elseif ($action === 'get_unread_count') {
        $sql = "SELECT COUNT(*) as count FROM tbl_notification WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        echo json_encode(['status' => 200, 'unread_count' => $row['count']]);
        $stmt->close();
    }

    $conn->close();
} else {
    echo json_encode(['status' => 405, 'msg' => 'Method not allowed']);
}
?>
