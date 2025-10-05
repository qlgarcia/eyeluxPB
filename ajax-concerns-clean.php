<?php
// Ultra-clean AJAX handler - absolutely no HTML output possible
// Disable everything that could cause output
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

// Start session
session_start();

// Only proceed if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo '{"success":false,"message":"Method not allowed"}';
    exit;
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '{"success":false,"message":"Not authenticated"}';
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Get action
$action = $_POST['action'] ?? '';

// Include database files safely
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
} else {
    echo '{"success":false,"message":"Config file missing"}';
    exit;
}

if (file_exists('includes/database.php')) {
    require_once 'includes/database.php';
} else {
    echo '{"success":false,"message":"Database file missing"}';
    exit;
}

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
} else {
    echo '{"success":false,"message":"Functions file missing"}';
    exit;
}

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'update_concern_status':
            $concern_id = intval($_POST['concern_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            if (in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
                $result = updateConcernStatus($concern_id, $status);
                if ($result) {
                    echo '{"success":true,"message":"Status updated successfully"}';
                } else {
                    echo '{"success":false,"message":"Failed to update status"}';
                }
            } else {
                echo '{"success":false,"message":"Invalid status"}';
            }
            break;
            
        case 'delete_concern':
            $concern_id = intval($_POST['concern_id'] ?? 0);
            
            // Check if concern exists
            $existing = $db->fetchOne("SELECT concern_id FROM user_concerns WHERE concern_id = ?", [$concern_id]);
            
            if (!$existing) {
                echo '{"success":false,"message":"Concern not found"}';
                break;
            }
            
            // Delete the concern
            $stmt = $db->prepare("DELETE FROM user_concerns WHERE concern_id = ?");
            $result = $stmt->execute([$concern_id]);
            
            if ($result !== false) {
                echo '{"success":true,"message":"Concern deleted successfully"}';
            } else {
                echo '{"success":false,"message":"Failed to delete concern"}';
            }
            break;
            
        default:
            echo '{"success":false,"message":"Invalid action"}';
            break;
    }
    
} catch (Exception $e) {
    echo '{"success":false,"message":"Server error"}';
} catch (Error $e) {
    echo '{"success":false,"message":"Fatal error"}';
}

exit;
?>
