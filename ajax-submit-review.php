<?php
// Include config first to start session
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review', 'debug' => [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'session_data' => $_SESSION
    ]]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON data from request body
$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log the input
error_log("AJAX Input: " . print_r($input, true));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data', 'debug' => [
        'raw_input' => file_get_contents('php://input'),
        'json_error' => json_last_error_msg()
    ]]);
    exit;
}

// Extract data from JSON
$product_id = (int)($input['product_id'] ?? 0);
$order_id = (int)($input['order_id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);
$title = trim($input['title'] ?? '');
$comment = trim($input['comment'] ?? '');

// Validate input
if (!$product_id || !$order_id || !$rating || !$title || !$comment) {
    echo json_encode(['success' => false, 'message' => 'All fields are required', 'debug' => [
        'product_id' => $product_id,
        'order_id' => $order_id,
        'rating' => $rating,
        'title' => $title,
        'comment' => $comment,
        'user_id' => $user_id
    ]]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Verify the order belongs to the user
$db = Database::getInstance();
$order = $db->fetchOne(
    "SELECT * FROM orders 
     WHERE order_id = ? AND user_id = ? AND status = 'delivered'",
    [$order_id, $user_id]
);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Invalid order or order not delivered']);
    exit;
}

// Verify the product exists in this order
$order_item = $db->fetchOne(
    "SELECT * FROM order_items 
     WHERE order_id = ? AND product_id = ?",
    [$order_id, $product_id]
);

if (!$order_item) {
    // Get debug info
    $order_items = $db->fetchAll("SELECT product_id FROM order_items WHERE order_id = ?", [$order_id]);
    $product_exists = $db->fetchOne("SELECT product_id FROM products WHERE product_id = ?", [$product_id]);
    
    $debug_info = [
        'order_id' => $order_id,
        'product_id' => $product_id,
        'user_id' => $user_id,
        'order_items' => $order_items,
        'product_exists' => $product_exists ? true : false
    ];
    
    echo json_encode([
        'success' => false, 
        'message' => 'Product not found in this order',
        'debug' => $debug_info
    ]);
    exit;
}

// Check if review already exists for this order and product
$existing_review = $db->fetchOne(
    "SELECT review_id FROM reviews 
     WHERE user_id = ? AND product_id = ? AND order_id = ?",
    [$user_id, $product_id, $order_id]
);

if ($existing_review) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
    exit;
}

try {
    // Submit the review
    $review_id = submitProductReview($user_id, $product_id, $order_id, $rating, $title, $comment);

    if ($review_id) {
        // Update product sales count with correct calculation (only active users)
        updateProductSalesCount($product_id);
        
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully', 'review_id' => $review_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review', 'debug' => [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'order_id' => $order_id,
            'rating' => $rating,
            'title' => $title,
            'comment' => $comment
        ]]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'debug' => [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]]);
}
?>



