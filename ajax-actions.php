<?php
// Handle AJAX requests without including header
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['ajax_add_to_cart']) || isset($_POST['ajax_add_to_wishlist']))) {
    // Include only necessary files for AJAX
    require_once 'includes/config.php';
    require_once 'includes/database.php';
    require_once 'includes/functions.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} else {
    // Regular page request - include full header
    require_once 'includes/header.php';
    $page_title = 'Add to Cart';
}

// Handle AJAX add to cart request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax_add_to_cart'])) {
    header('Content-Type: application/json');
    
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Please login to add items to cart', 'login_required' => true]);
            exit;
        }
        
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
            exit;
        }
        
        // Get product details
        $product = getProductById($product_id);
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        // Check stock
        if ($quantity > $product['stock_quantity']) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        $db = Database::getInstance();
        
        // Check if item already exists in cart
        $existing_item = $db->fetchOne(
            "SELECT * FROM cart WHERE user_id = ? AND product_id = ?", 
            [$user_id, $product_id]
        );
        
        if ($existing_item) {
            // Update existing item quantity
            $new_quantity = $existing_item['quantity'] + $quantity;
            if ($new_quantity > $product['stock_quantity']) {
                echo json_encode(['success' => false, 'message' => 'Cannot add more items. Only ' . $product['stock_quantity'] . ' available in stock.']);
                exit;
            }
            
            $db->execute(
                "UPDATE cart SET quantity = ? WHERE cart_id = ?", 
                [$new_quantity, $existing_item['cart_id']]
            );
        } else {
            // Add new item to cart
            if (!addToCart($user_id, null, $product_id, $quantity)) {
                echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
                exit;
            }
        }
        
        // Return success response
        $cart_count = getCartCount($user_id);
        echo json_encode([
            'success' => true, 
            'message' => 'Product added to cart!',
            'cart_count' => $cart_count,
            'product_name' => $product['product_name']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX add to wishlist request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax_add_to_wishlist'])) {
    header('Content-Type: application/json');
    
    try {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Please login to add to wishlist']);
            exit;
        }
        
        $product_id = (int)($_POST['product_id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
            exit;
        }
        
        $db = Database::getInstance();
        
        // Check if already in wishlist
        $existing = $db->fetchOne("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?", 
                                 [$user_id, $product_id]);
        
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
            exit;
        }
        
        // Add to wishlist
        $result = $db->insert("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)", 
                             [$user_id, $product_id]);
        
        if ($result) {
            $wishlist_count = $db->fetchOne("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?", [$user_id])['count'];
            $product = getProductById($product_id);
            echo json_encode([
                'success' => true, 
                'message' => 'Added to wishlist!',
                'wishlist_count' => $wishlist_count,
                'product_name' => $product['product_name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Regular page request - redirect to home
redirect('index.php');
?>
