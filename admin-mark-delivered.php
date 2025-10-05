<?php
require_once 'includes/header.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

$db = Database::getInstance();

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_delivered') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        
        if ($order_id) {
            // Update order status to delivered
            $db->execute("UPDATE orders SET status = 'delivered' WHERE order_id = ?", [$order_id]);
            
            // Get order items to create review notifications
            $order_items = $db->fetchAll(
                "SELECT oi.*, o.user_id FROM order_items oi 
                 JOIN orders o ON oi.order_id = o.order_id 
                 WHERE oi.order_id = ?",
                [$order_id]
            );
            
            // Create review notifications for each product
            foreach ($order_items as $item) {
                createReviewNotification($item['user_id'], $order_id, $item['product_id'], 'delivery_confirmed');
            }
            
            $success_message = "Order #{$order_id} marked as delivered and review notifications sent to customer.";
        }
    }
}

// Get orders that can be marked as delivered
$orders = $db->fetchAll(
    "SELECT o.*, u.first_name, u.last_name, u.email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     WHERE o.status IN ('processing', 'shipped') 
     ORDER BY o.order_date DESC"
);

echo "<h2>Mark Orders as Delivered</h2>";

if (isset($success_message)) {
    echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-radius: 8px; border: 1px solid #c3e6cb;'>";
    echo "<strong>✓ Success:</strong> {$success_message}";
    echo "</div>";
}

echo "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 8px; border: 1px solid #ffeaa7;'>";
echo "<h3>📦 Delivery Confirmation Process</h3>";
echo "<p>When you mark an order as 'delivered', the system will:</p>";
echo "<ul>";
echo "<li>Update the order status to 'delivered'</li>";
echo "<li>Create review notifications for each product in the order</li>";
echo "<li>Send notifications to the customer to review their purchases</li>";
echo "<li>Notifications will appear on the products page for 30 days</li>";
echo "</ul>";
echo "</div>";

if (empty($orders)) {
    echo "<div style='background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center;'>";
    echo "<p>No orders ready for delivery confirmation.</p>";
    echo "</div>";
} else {
    echo "<div style='display: grid; gap: 15px; margin: 20px 0;'>";
    
    foreach ($orders as $order) {
        echo "<div style='background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";
        echo "<div style='display: flex; justify-content: space-between; align-items: center;'>";
        
        echo "<div>";
        echo "<h4 style='margin: 0 0 5px 0; color: #2c3e50;'>Order #{$order['order_number']}</h4>";
        echo "<p style='margin: 0 0 5px 0; color: #666;'>Customer: {$order['first_name']} {$order['last_name']}</p>";
        echo "<p style='margin: 0 0 5px 0; color: #666;'>Email: {$order['email']}</p>";
        echo "<p style='margin: 0 0 5px 0; color: #666;'>Order Date: " . date('M j, Y', strtotime($order['order_date'])) . "</p>";
        echo "<p style='margin: 0; color: #666;'>Total: " . formatPrice($order['total_amount']) . "</p>";
        echo "</div>";
        
        echo "<div style='text-align: right;'>";
        echo "<span style='background: #ffc107; color: #333; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-bottom: 10px; display: inline-block;'>";
        echo strtoupper($order['status']);
        echo "</span><br>";
        
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='action' value='mark_delivered'>";
        echo "<input type='hidden' name='order_id' value='{$order['order_id']}'>";
        echo "<button type='submit' style='background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;' onclick=\"return confirm('Mark this order as delivered and send review notifications?')\">";
        echo "✓ Mark as Delivered";
        echo "</button>";
        echo "</form>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }
    
    echo "</div>";
}

echo "<p><a href='admin.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Admin Panel</a></p>";
?>



