<?php
/**
 * Mark notification as read
 */

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get JSON data
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = (int)($input['notification_id'] ?? 0);

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

try {
    $db = Database::getInstance();
    $user_id = $_SESSION['user_id'];
    
    // Mark notification as read
    $result = $db->execute(
        "UPDATE notifications 
         SET is_read = 1, read_at = NOW() 
         WHERE notification_id = ? AND user_id = ?",
        [$notification_id, $user_id]
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
