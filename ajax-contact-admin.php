<?php
// AJAX handler for contact form submissions
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clean any output that might have been sent
if (ob_get_level()) {
    ob_clean();
}

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }
    
    // Validate email
    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    // Get user ID if logged in
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    
    // Create user concern
    $concern_id = createUserConcern($user_id, $name, $email, $subject, $message);
    
    if ($concern_id) {
        // Log activity if user is logged in
        if ($user_id) {
            logActivity($user_id, 'contact_form_submitted', "Subject: $subject");
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Your message has been sent to the admin. You will receive a reply via email.',
            'concern_id' => $concern_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>