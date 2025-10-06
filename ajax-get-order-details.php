<?php
// AJAX endpoint to get order details for printing
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to view order details']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON data from request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = (int)$input['order_id'];
$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Get order details
    $order = $db->fetchOne(
        "SELECT o.*, sa.first_name as ship_first_name, sa.last_name as ship_last_name, 
                sa.address_line1 as ship_address, sa.address_line2 as ship_address2,
                sa.city as ship_city, sa.state as ship_state, 
                sa.postal_code as ship_postal_code, sa.country as ship_country, sa.phone as ship_phone,
                ba.first_name as bill_first_name, ba.last_name as bill_last_name,
                ba.address_line1 as bill_address, ba.address_line2 as bill_address2,
                ba.city as bill_city, ba.state as bill_state,
                ba.postal_code as bill_postal_code, ba.country as bill_country, ba.phone as bill_phone
         FROM orders o
         LEFT JOIN addresses sa ON o.shipping_address_id = sa.address_id
         LEFT JOIN addresses ba ON o.billing_address_id = ba.address_id
         WHERE o.order_id = ? AND o.user_id = ?",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }
    
    // Get order items
    $order_items = $db->fetchAll(
        "SELECT oi.*, p.image_url FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.product_id
         WHERE oi.order_id = ?",
        [$order_id]
    );
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'order_items' => $order_items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

