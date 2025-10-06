<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if resending code
    if (isset($_POST['resend_code'])) {
        $user_id = $_SESSION['pending_verification_user_id'] ?? null;
        
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'No pending verification found']);
            exit;
        }
        
        // Get user details
        $user = $db->fetchOne("SELECT first_name, email FROM users WHERE user_id = ?", [$user_id]);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Generate new verification code
        $verification_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $verification_expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        // Update verification code
        $db->execute(
            "UPDATE users SET verification_code = ?, verification_code_expires = ? WHERE user_id = ?",
            [$verification_code, $verification_expires, $user_id]
        );
        
        // Send verification email
        require_once 'includes/email_service.php';
        $emailService = new EmailService();
        $emailSent = $emailService->sendVerificationEmail($user['email'], $user['first_name'], $verification_code);
        
        if ($emailSent) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please try again.']);
        }
        exit;
    }
    
    // Verify the code
    $verification_code = $_POST['verification_code'] ?? '';
    
    if (empty($verification_code) || strlen($verification_code) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid 6-digit verification code']);
        exit;
    }
    
    $user_id = $_SESSION['pending_verification_user_id'] ?? null;
    
    // If no session user ID, try to find user by verification code
    if (!$user_id) {
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE verification_code = ? AND verification_code_expires > ? AND email_verified = 0",
            [$verification_code, date('Y-m-d H:i:s')]
        );
        
        if ($user) {
            $user_id = $user['user_id'];
        }
    } else {
        // Check verification code with session user ID
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE user_id = ? AND verification_code = ? AND verification_code_expires > ?",
            [$user_id, $verification_code, date('Y-m-d H:i:s')]
        );
    }
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        exit;
    }
    
    // Mark email as verified
    $db->execute("UPDATE users SET email_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE user_id = ?", [$user_id]);
    
    // Log the user in
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    // Clear pending verification
    unset($_SESSION['pending_verification_user_id']);
    unset($_SESSION['unverified_user_id']);
    unset($_SESSION['unverified_user_email']);
    unset($_SESSION['unverified_user_name']);
    
    // Migrate guest cart to user cart
    migrateGuestCartToUser($user_id, session_id());
    
    echo json_encode([
        'success' => true, 
        'message' => 'Email verified successfully! You are now logged in.',
        'redirect' => 'index.php'
    ]);
    
} catch (Exception $e) {
    error_log("Email verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>