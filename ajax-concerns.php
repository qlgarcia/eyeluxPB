<?php
// Dedicated AJAX handler for concerns
// Disable error reporting completely
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Start output buffering
ob_start();

// Start session
session_start();

// Clean any output before including files
while (ob_get_level()) {
    ob_end_clean();
}

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Clean any output after including files
while (ob_get_level()) {
    ob_end_clean();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Clean any output that might have been sent
while (ob_get_level()) {
    ob_end_clean();
}

// Set content type
header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $action = trim($_POST['action'] ?? '');
    
    switch ($action) {
        case 'update_concern_status':
            $concern_id = intval($_POST['concern_id'] ?? 0);
            $status = sanitizeInput($_POST['status'] ?? '');
            
            if (in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
                $result = updateConcernStatus($concern_id, $status);
                echo json_encode(['success' => $result, 'message' => $result ? 'Status updated successfully' : 'Failed to update status']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $status]);
            }
            break;
            
        case 'delete_concern':
            $concern_id = intval($_POST['concern_id'] ?? 0);
            
            // First check if the concern exists
            $existing = $db->fetchOne("SELECT concern_id FROM user_concerns WHERE concern_id = ?", [$concern_id]);
            
            if (!$existing) {
                echo json_encode(['success' => false, 'message' => 'Concern not found']);
                break;
            }
            
            // Delete the concern
            $stmt = $db->prepare("DELETE FROM user_concerns WHERE concern_id = ?");
            $result = $stmt->execute([$concern_id]);
            
            if ($result !== false) {
                echo json_encode(['success' => true, 'message' => 'Concern deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete concern']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Concerns AJAX error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

exit;
?>
