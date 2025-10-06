<?php
// AJAX handler for notifications
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_count':
            $count = getUnreadNotificationCount($user_id);
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        case 'get_notifications':
            $limit = (int)($_GET['limit'] ?? 10);
            $unread_only = ($_GET['unread_only'] ?? 'false') === 'true';
            $notifications = getNotifications($user_id, $limit, $unread_only);
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;
            
        case 'mark_read':
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                $result = markNotificationAsRead($notification_id, $user_id);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        case 'mark_all_read':
            $result = markAllNotificationsAsRead($user_id);
            echo json_encode(['success' => $result]);
            break;
            
        case 'delete':
            $notification_id = (int)($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                $db = Database::getInstance();
                $result = $db->execute(
                    "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?",
                    [$notification_id, $user_id]
                );
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            }
            break;
            
        case 'create_order_notification':
            $order_id = $_POST['order_id'] ?? '';
            if (!empty($order_id)) {
                $result = createNotification(
                    $user_id,
                    'order_status',
                    'Order Placed Successfully',
                    'Your order #' . $order_id . ' has been placed and is being processed.',
                    $order_id
                );
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            }
            break;
            
        case 'create_test_notification':
            $type = $_POST['type'] ?? 'system';
            $title = $_POST['title'] ?? 'Test Notification';
            $message = $_POST['message'] ?? 'This is a test notification';
            
            $result = createNotification(
                $user_id,
                $type,
                $title,
                $message,
                null
            );
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Test notification created']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create test notification']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Notification AJAX error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
