<?php
// Final ultra-clean AJAX handler - completely self-contained
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo '{"success":false,"message":"Method not allowed"}';
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '{"success":false,"message":"Not authenticated"}';
    exit;
}

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';

// Include files safely
if (!file_exists('includes/config.php')) {
    echo '{"success":false,"message":"Config missing"}';
    exit;
}
require_once 'includes/config.php';

if (!file_exists('includes/database.php')) {
    echo '{"success":false,"message":"Database missing"}';
    exit;
}
require_once 'includes/database.php';

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'update_concern_status':
            $concern_id = intval($_POST['concern_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            if (in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
                // Direct database update instead of using function
                $stmt = $db->prepare("UPDATE user_concerns SET status = ?, updated_at = NOW() WHERE concern_id = ?");
                $result = $stmt->execute([$status, $concern_id]);
                
                if ($result !== false) {
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
            $stmt = $db->prepare("SELECT concern_id FROM user_concerns WHERE concern_id = ?");
            $stmt->execute([$concern_id]);
            $existing = $stmt->fetch();
            
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






