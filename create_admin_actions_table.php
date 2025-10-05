<?php
require_once 'includes/database.php';

$db = Database::getInstance();

echo "<h2>🔧 Creating Admin Actions Table</h2>";

try {
    // Create admin_actions table
    $db->execute("
        CREATE TABLE IF NOT EXISTS admin_actions (
            action_id INT(11) AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT(11) NOT NULL,
            target_user_id INT(11) NULL,
            action_type VARCHAR(50) NOT NULL,
            reason TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_user_id (admin_user_id),
            INDEX idx_target_user_id (target_user_id),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at)
        )
    ");
    
    echo "<p style='color: green;'>✅ Admin actions table created successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error creating admin actions table: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='admin.php'>← Go to Admin Panel</a></p>";
?>





