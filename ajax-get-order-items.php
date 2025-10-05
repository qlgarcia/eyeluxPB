<?php
/**
 * AJAX endpoint to get order items for refund requests
 */

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['order_id'])) {
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }
    
    $order_id = (int)$input['order_id'];
    
    // Verify the order belongs to the user
    $db = Database::getInstance();
    $order = $db->fetchOne(
        "SELECT order_id, user_id, status FROM orders WHERE order_id = ? AND user_id = ?",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    if ($order['status'] !== 'delivered') {
        echo json_encode(['success' => false, 'message' => 'Only delivered orders are eligible for refund']);
        exit;
    }
    
    // Get order items
    $items = $db->fetchAll(
        "SELECT oi.*, p.product_name, p.image_url
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.order_item_id",
        [$order_id]
    );
    
    // Check if items already have refund requests
    foreach ($items as &$item) {
        $existing_refund = $db->fetchOne(
            "SELECT refund_id, status FROM refund_requests 
             WHERE order_id = ? AND product_id = ? AND order_item_id = ? AND user_id = ? AND status = 'pending'",
            [$order_id, $item['product_id'], $item['order_item_id'], $user_id]
        );
        
        $item['has_refund_request'] = $existing_refund ? true : false;
        $item['refund_status'] = $existing_refund ? $existing_refund['status'] : null;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax-get-order-items.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving order items'
    ]);
}
?>
