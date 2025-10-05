<?php
// Ultra-simple AJAX handler - minimal code
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

session_start();

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '{"success":false,"message":"Method not allowed"}';
    exit;
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo '{"success":false,"message":"Not authenticated"}';
    exit;
}

// Set content type
header('Content-Type: application/json');

// Get action
$action = $_POST['action'] ?? '';

// Simple database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=eyelux_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo '{"success":false,"message":"Database connection failed"}';
    exit;
}

// Handle actions
if ($action === 'update_concern_status') {
    $concern_id = intval($_POST['concern_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    if (in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
        $stmt = $pdo->prepare("UPDATE user_concerns SET status = ?, updated_at = NOW() WHERE concern_id = ?");
        $result = $stmt->execute([$status, $concern_id]);
        
        if ($result) {
            echo '{"success":true,"message":"Status updated successfully"}';
        } else {
            echo '{"success":false,"message":"Failed to update status"}';
        }
    } else {
        echo '{"success":false,"message":"Invalid status"}';
    }
} elseif ($action === 'delete_concern') {
    $concern_id = intval($_POST['concern_id'] ?? 0);
    
    $stmt = $pdo->prepare("DELETE FROM user_concerns WHERE concern_id = ?");
    $result = $stmt->execute([$concern_id]);
    
    if ($result) {
        echo '{"success":true,"message":"Concern deleted successfully"}';
    } else {
        echo '{"success":false,"message":"Failed to delete concern"}';
    }
} else {
    echo '{"success":false,"message":"Invalid action"}';
}

exit;
?>
