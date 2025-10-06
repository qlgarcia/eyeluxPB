<?php
// Admin script to recalculate sales counts
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

echo "<h2>🔧 Admin: Recalculate Sales Counters</h2>";

try {
    $updated_count = recalculateAllProductSalesCounts();
    
    if ($updated_count !== false) {
        echo "<p style='color: green; font-weight: bold;'>✅ Successfully recalculated sales counts for $updated_count products!</p>";
        echo "<p>The 'X PEOPLE BOUGHT THIS' counters now reflect accurate data based on actual orders.</p>";
        
        echo "<h3>📊 What This Fixed:</h3>";
        echo "<ul>";
        echo "<li>✅ Removed phantom sales from deleted user accounts</li>";
        echo "<li>✅ Updated counters to show only current, valid sales</li>";
        echo "<li>✅ Excluded cancelled and refunded orders</li>";
        echo "<li>✅ Counts unique customers (not quantity)</li>";
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>❌ Failed to recalculate sales counts</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<br><a href='admin.php'>← Back to Admin Panel</a> | <a href='index.php'>← View Homepage</a>";
?>





