<?php
// Ban check middleware - include this in all protected pages
require_once 'includes/functions.php';

// Check if user is logged in and banned
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if user is banned
    if (isUserBanned($user_id)) {
        // Get ban details for the logout message
        $ban_details = getBanDetails($user_id);
        
        // Store ban details in session for display
        $_SESSION['ban_reason'] = $ban_details['ban_reason'] ?? 'No reason provided';
        $_SESSION['ban_date'] = $ban_details['ban_date'] ?? date('Y-m-d H:i:s');
        $_SESSION['warning_count'] = $ban_details['warning_count'] ?? 0;
        
        // Log out the banned user
        logoutBannedUser();
    }
}
?>













