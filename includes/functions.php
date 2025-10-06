<?php
require_once 'includes/database.php';

// Helper functions for the application

// Check if user is banned
function isUserBanned($user_id) {
    $db = Database::getInstance();
    
    $result = $db->fetchOne(
        "SELECT status, ban_reason, ban_date FROM users WHERE user_id = ?",
        [$user_id]
    );
    
    return $result && $result['status'] === 'banned';
}

// Get ban details for user
function getBanDetails($user_id) {
    $db = Database::getInstance();
    
    $result = $db->fetchOne(
        "SELECT status, ban_reason, ban_date, warning_count FROM users WHERE user_id = ?",
        [$user_id]
    );
    
    return $result;
}

// Log out banned user and redirect
function logoutBannedUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    header('Location: login.php?banned=1');
    exit;
}

// Get cart count for user (requires login)
function getCartCount($user_id) {
    $db = Database::getInstance();
    
    // Only allow logged-in users
    if (!$user_id) {
        return 0;
    }
    
    // Logged-in user - count only their cart items
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM cart WHERE user_id = ?", [$user_id]);
    
    return $result['count'] ?? 0;
}

// Update sales count for a product based on actual orders
function updateProductSalesCount($product_id) {
    $db = Database::getInstance();
    
    // Count unique ACTIVE users who actually bought this product and haven't refunded it
    $result = $db->fetchOne(
        "SELECT COUNT(DISTINCT o.user_id) as unique_users 
         FROM order_items oi 
         JOIN orders o ON oi.order_id = o.order_id 
         JOIN users u ON o.user_id = u.user_id
         WHERE oi.product_id = ? 
         AND o.status = 'delivered' 
         AND u.user_id IS NOT NULL
         AND oi.order_item_id NOT IN (
             SELECT DISTINCT rr.order_item_id 
             FROM refund_requests rr 
             WHERE rr.product_id = oi.product_id 
             AND rr.order_item_id = oi.order_item_id 
             AND rr.status IN ('approved', 'processing', 'completed')
         )",
        [$product_id]
    );
    
    $real_count = $result['unique_users'] ?? 0;
    
    // Update the product sales count
    $db->execute(
        "UPDATE products SET sales_count = ? WHERE product_id = ?",
        [$real_count, $product_id]
    );
    
    error_log("Updated sales count for product ID $product_id to $real_count (excluding refunded purchases)");
    
    return $real_count;
}

// Update sales count for all products
function updateAllSalesCounts() {
    $db = Database::getInstance();
    
    $products = $db->fetchAll("SELECT product_id FROM products WHERE is_active = 1");
    $updated = 0;
    
    foreach ($products as $product) {
        $old_count = $db->fetchOne("SELECT sales_count FROM products WHERE product_id = ?", [$product['product_id']])['sales_count'];
        $new_count = updateProductSalesCount($product['product_id']);
        
        if ($old_count != $new_count) {
            $updated++;
        }
    }
    
    return $updated;
}

// Get wishlist count for user
function getWishlistCount($user_id) {
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?", [$user_id]);
    return $result['count'] ?? 0;
}

// Migrate guest cart to user cart when they log in
function migrateGuestCartToUser($user_id, $session_id) {
    $db = Database::getInstance();
    
    // Get guest cart items
    $guest_items = $db->fetchAll("SELECT * FROM cart WHERE session_id = ? AND user_id IS NULL", [$session_id]);
    
    foreach ($guest_items as $item) {
        // Check if user already has this item in their cart
        $existing = $db->fetchOne("SELECT * FROM cart WHERE user_id = ? AND product_id = ?", 
                                  [$user_id, $item['product_id']]);
        
        if ($existing) {
            // Update quantity
            $db->execute("UPDATE cart SET quantity = quantity + ? WHERE cart_id = ?", 
                        [$item['quantity'], $existing['cart_id']]);
        } else {
            // Add to user cart
            $db->execute("INSERT INTO cart (user_id, session_id, product_id, quantity, added_at) VALUES (?, NULL, ?, ?, ?)", 
                        [$user_id, $item['product_id'], $item['quantity'], $item['added_at']]);
        }
    }
    
    // Remove guest cart items
    $db->execute("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL", [$session_id]);
}

// Notification functions
function createNotification($user_id, $type, $title, $message, $order_id = null) {
    $db = Database::getInstance();
    
    try {
        $result = $db->execute(
            "INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)",
            [$user_id, $type, $title, $message]
        );
        
        // Return true if at least one row was affected (successful insert)
        return $result > 0;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

function getNotifications($user_id, $limit = 10, $unread_only = false) {
    $db = Database::getInstance();
    
    $sql = "SELECT notification_id as id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ?";
    $params = [$user_id];
    
    if ($unread_only) {
        $sql .= " AND is_read = 0";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    
    return $db->fetchAll($sql, $params);
}

function getUnreadNotificationCount($user_id) {
    $db = Database::getInstance();
    
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$user_id]
    );
    
    return $result['count'] ?? 0;
}


function markAllNotificationsAsRead($user_id) {
    $db = Database::getInstance();
    
    try {
        return $db->execute(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$user_id]
        );
    } catch (Exception $e) {
        error_log("Failed to mark all notifications as read: " . $e->getMessage());
        return false;
    }
}

// Order status notification functions
function updateOrderStatus($order_id, $new_status, $message = null) {
    $db = Database::getInstance();
    
    try {
        // Get order details
        $order = $db->fetchOne(
            "SELECT o.*, u.user_id, u.first_name, u.last_name, u.email 
             FROM orders o 
             JOIN users u ON o.user_id = u.user_id 
             WHERE o.order_id = ?",
            [$order_id]
        );
        
        if (!$order) {
            return false;
        }
        
        // Update order status
        $db->execute(
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
            [$new_status, $order_id]
        );
        
        // Add to order status history
        $db->execute(
            "INSERT INTO order_status (order_id, status, message) VALUES (?, ?, ?)",
            [$order_id, $new_status, $message]
        );
        
        // Create notification based on status
        $notification = createOrderStatusNotification($order, $new_status, $message);
        
        // Send email notification
        sendOrderStatusEmail($order, $new_status, $message);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to update order status: " . $e->getMessage());
        return false;
    }
}

function createOrderStatusNotification($order, $status, $message = null) {
    $statusMessages = [
        'pending' => [
            'title' => 'Order Received',
            'message' => "Your order #{$order['order_number']} has been received and is being processed."
        ],
        'confirmed' => [
            'title' => 'Order Confirmed',
            'message' => "Your order #{$order['order_number']} has been confirmed and payment verified."
        ],
        'processing' => [
            'title' => 'Order Processing',
            'message' => "Your order #{$order['order_number']} is being prepared for shipment."
        ],
        'shipped' => [
            'title' => 'Order Shipped',
            'message' => "Your order #{$order['order_number']} has been shipped and is on its way!"
        ],
        'delivered' => [
            'title' => '🎉 Order Delivered!',
            'message' => "Your order #{$order['order_number']} has arrived and been delivered successfully! We hope you love your new eyewear. Please take a moment to share your experience by reviewing the products."
        ],
        'cancelled' => [
            'title' => 'Order Cancelled',
            'message' => "Your order #{$order['order_number']} has been cancelled."
        ],
        'refunded' => [
            'title' => 'Order Refunded',
            'message' => "Your order #{$order['order_number']} has been refunded."
        ]
    ];
    
    $notificationData = $statusMessages[$status] ?? [
        'title' => 'Order Status Update',
        'message' => "Your order #{$order['order_number']} status has been updated to: " . ucfirst($status)
    ];
    
    // Add custom message if provided
    if ($message) {
        $notificationData['message'] .= " " . $message;
    }
    
    return createNotification(
        $order['user_id'],
        'order_status',
        $notificationData['title'],
        $notificationData['message'],
        $order['order_id']
    );
}

function sendOrderStatusEmail($order, $status, $message = null) {
    // This would integrate with your existing email service
    // For now, just log the email that would be sent
    error_log("Order status email would be sent to: {$order['email']} for order #{$order['order_number']} - Status: $status");
    
    // You can implement actual email sending here using your EmailService
    /*
    require_once 'includes/email_service.php';
    $emailService = new EmailService();
    
    $subject = "Order Status Update - #{$order['order_number']}";
    $body = "Dear {$order['first_name']},\n\n";
    $body .= "Your order status has been updated to: " . ucfirst($status) . "\n\n";
    if ($message) {
        $body .= "Additional information: $message\n\n";
    }
    $body .= "Thank you for choosing EyeLux!\n\nBest regards,\nEyeLux Team";
    
    $emailService->sendEmail($order['email'], $subject, $body);
    */
}

// Create order placed notification
function createOrderPlacedNotification($order_id) {
    $db = Database::getInstance();
    
    // Get order details
    $order = $db->fetchOne(
        "SELECT o.*, u.user_id, u.first_name, u.last_name, u.email 
         FROM orders o 
         JOIN users u ON o.user_id = u.user_id 
         WHERE o.order_id = ?",
        [$order_id]
    );
    
    if ($order) {
        return createNotification(
            $order['user_id'],
            'order_status',
            'Order Placed Successfully',
            "Your order #{$order['order_number']} has been placed and is being processed.",
            $order['order_id']
        );
    }
    
    return false;
}

// Hero content management functions
function getHeroContent() {
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM hero_content WHERE is_active = TRUE ORDER BY id DESC LIMIT 1");
}

function getHeroCarousel() {
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM hero_carousel WHERE is_active = TRUE ORDER BY display_order ASC");
}

function updateHeroContent($title, $subtitle, $button_text, $button_link) {
    $db = Database::getInstance();
    
    // Check if content exists
    $existing = $db->fetchOne("SELECT id FROM hero_content LIMIT 1");
    
    if ($existing) {
        // Update existing content
        return $db->execute(
            "UPDATE hero_content SET title = ?, subtitle = ?, button_text = ?, button_link = ?, updated_at = NOW() WHERE id = ?",
            [$title, $subtitle, $button_text, $button_link, $existing['id']]
        );
    } else {
        // Insert new content
        return $db->insert(
            "INSERT INTO hero_content (title, subtitle, button_text, button_link) VALUES (?, ?, ?, ?)",
            [$title, $subtitle, $button_text, $button_link]
        );
    }
}

function addCarouselItem($image_url, $title, $subtitle, $button_text, $button_link, $display_order = 0) {
    $db = Database::getInstance();
    return $db->insert(
        "INSERT INTO hero_carousel (image_url, title, subtitle, button_text, button_link, display_order) VALUES (?, ?, ?, ?, ?, ?)",
        [$image_url, $title, $subtitle, $button_text, $button_link, $display_order]
    );
}

function updateCarouselItem($id, $image_url, $title, $subtitle, $button_text, $button_link, $display_order) {
    $db = Database::getInstance();
    return $db->execute(
        "UPDATE hero_carousel SET image_url = ?, title = ?, subtitle = ?, button_text = ?, button_link = ?, display_order = ?, updated_at = NOW() WHERE id = ?",
        [$image_url, $title, $subtitle, $button_text, $button_link, $display_order, $id]
    );
}

function deleteCarouselItem($id) {
    $db = Database::getInstance();
    return $db->execute("DELETE FROM hero_carousel WHERE id = ?", [$id]);
}

// Sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Format price
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

// Calculate discount percentage
function calculateDiscountPercentage($original_price, $sale_price) {
    if ($original_price <= 0) return 0;
    return round((($original_price - $sale_price) / $original_price) * 100);
}

// Get user by ID
function getUserById($user_id) {
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM users WHERE user_id = ? AND is_active = 1", [$user_id]);
}

// Get user by email
function getUserByEmail($email) {
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
}

// Get product by ID
function getProductById($product_id) {
    $db = Database::getInstance();
    return $db->fetchOne("SELECT p.*, c.category_name FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.category_id 
                         WHERE p.product_id = ? AND p.is_active = 1", [$product_id]);
}

// Get featured products (products with highest star ratings)
function getFeaturedProducts($limit = 8) {
    $db = Database::getInstance();
    try {
        return $db->fetchAll("SELECT p.*, c.category_name, 
                                    AVG(r.rating) as avg_rating,
                                    COUNT(r.review_id) as review_count,
                                    COALESCE(p.sales_count, 0) as sales_count
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.category_id 
                             INNER JOIN reviews r ON p.product_id = r.product_id
                             WHERE p.is_active = 1 
                             GROUP BY p.product_id
                             HAVING COUNT(r.review_id) > 0
                             ORDER BY avg_rating DESC, review_count DESC, sales_count DESC, p.created_at DESC 
                             LIMIT ?", [$limit]);
    } catch (Exception $e) {
        // Fallback to original logic if reviews table doesn't exist
        try {
            return $db->fetchAll("SELECT p.*, c.category_name FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.category_id 
                                 WHERE p.is_active = 1 AND COALESCE(p.sales_count, 0) > 0
                                 ORDER BY COALESCE(p.sales_count, 0) DESC, p.created_at DESC 
                                 LIMIT ?", [$limit]);
        } catch (Exception $e2) {
            // Final fallback to original logic
            return $db->fetchAll("SELECT p.*, c.category_name FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.category_id 
                                 WHERE p.is_featured = 1 AND p.is_active = 1 
                                 ORDER BY p.created_at DESC LIMIT ?", [$limit]);
        }
    }
}

// Get new arrival products
function getNewArrivalProducts($limit = 8) {
    $db = Database::getInstance();
    return $db->fetchAll("SELECT p.*, c.category_name FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.category_id 
                         WHERE p.is_new_arrival = 1 AND p.is_active = 1 
                         ORDER BY p.created_at DESC LIMIT ?", [$limit]);
}

// Get best selling products based on sales_count (only products with sales > 0)
function getBestSellingProducts($limit = 6) {
    $db = Database::getInstance();
    try {
        // Get products with sales > 0, include review data if available
        return $db->fetchAll("SELECT p.*, c.category_name, 
                                    p.sales_count,
                                    COALESCE(AVG(r.rating), 0) as avg_rating,
                                    COALESCE(COUNT(r.review_id), 0) as review_count
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.category_id 
                             LEFT JOIN reviews r ON p.product_id = r.product_id
                             WHERE p.is_active = 1 AND p.sales_count > 0
                             GROUP BY p.product_id
                             ORDER BY p.sales_count DESC, avg_rating DESC, review_count DESC, p.created_at DESC 
                             LIMIT ?", [$limit]);
    } catch (Exception $e) {
        // Fallback to simple query - just sales count
        try {
            return $db->fetchAll("SELECT p.*, c.category_name, 
                                        p.sales_count,
                                        0 as avg_rating,
                                        0 as review_count
                                 FROM products p 
                                 LEFT JOIN categories c ON p.category_id = c.category_id 
                                 WHERE p.is_active = 1 AND p.sales_count > 0
                                 ORDER BY p.sales_count DESC, p.created_at DESC 
                                 LIMIT ?", [$limit]);
        } catch (Exception $e2) {
            return [];
        }
    }
}

// Get all categories
function getAllCategories() {
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name");
}

// Search products
function searchProducts($query, $category_id = null, $min_price = null, $max_price = null, $sort = 'name', $limit = 20, $offset = 0) {
    $db = Database::getInstance();
    
    $sql = "SELECT p.*, c.category_name FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            WHERE p.is_active = 1";
    
    $params = [];
    
    if (!empty($query)) {
        $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
        $search_term = "%$query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($category_id) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($min_price !== null) {
        $sql .= " AND p.price >= ?";
        $params[] = $min_price;
    }
    
    if ($max_price !== null) {
        $sql .= " AND p.price <= ?";
        $params[] = $max_price;
    }
    
    // Add sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'rating':
            $sql .= " ORDER BY p.rating DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY p.created_at DESC";
            break;
        default:
            $sql .= " ORDER BY p.product_name ASC";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    return $db->fetchAll($sql, $params);
}

// Add to cart (requires login)
function addToCart($user_id, $session_id, $product_id, $quantity = 1) {
    $db = Database::getInstance();
    
    // Only allow logged-in users
    if (!$user_id) {
        return false;
    }
    
    // Logged-in user - use only user_id
    $existing = $db->fetchOne("SELECT * FROM cart WHERE user_id = ? AND product_id = ?", 
                              [$user_id, $product_id]);
    
    if ($existing) {
        // Update quantity
        return $db->execute("UPDATE cart SET quantity = quantity + ? WHERE cart_id = ?", 
                           [$quantity, $existing['cart_id']]);
    } else {
        // Add new item
        return $db->insert("INSERT INTO cart (user_id, session_id, product_id, quantity) VALUES (?, NULL, ?, ?)", 
                          [$user_id, $product_id, $quantity]);
    }
}

// Get cart items (requires login)
function getCartItems($user_id, $session_id) {
    $db = Database::getInstance();
    
    // Only allow logged-in users
    if (!$user_id) {
        return [];
    }
    
    // Logged-in user - get only their cart items
    return $db->fetchAll("SELECT c.*, p.product_name, p.price, p.sale_price, p.image_url, p.stock_quantity, p.sku 
                         FROM cart c 
                         JOIN products p ON c.product_id = p.product_id 
                         WHERE c.user_id = ? AND p.is_active = 1 
                         ORDER BY c.added_at DESC", [$user_id]);
}

// Calculate cart total
function calculateCartTotal($cart_items) {
    $total = 0;
    foreach ($cart_items as $item) {
        $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
        $total += $price * $item['quantity'];
    }
    return $total;
}

// Clear user's cart (requires login)
function clearCart($user_id, $session_id) {
    $db = Database::getInstance();
    
    try {
        // Only allow logged-in users
        if (!$user_id) {
            return false;
        }
        
        // Clear cart for logged-in user
        $db->execute("DELETE FROM cart WHERE user_id = ?", [$user_id]);
        return true;
    } catch (Exception $e) {
        error_log("Error clearing cart: " . $e->getMessage());
        return false;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get user profile picture
function getUserProfilePicture($user_id) {
    $db = Database::getInstance();
    
    try {
        $user = $db->fetchOne(
            "SELECT profile_picture FROM users WHERE user_id = ?",
            [$user_id]
        );
        
        if ($user && !empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
            return $user['profile_picture'];
        }
        
        return null; // No profile picture or file doesn't exist
    } catch (Exception $e) {
        error_log("Error getting user profile picture: " . $e->getMessage());
        return null;
    }
}

// Refund System Functions

// Submit a refund request
function submitRefundRequest($user_id, $order_id, $product_id, $order_item_id, $refund_amount, $refund_reason, $customer_message = '') {
    $db = Database::getInstance();
    
    try {
        // Check if refund request already exists for this order item
        $existing = $db->fetchOne(
            "SELECT refund_id FROM refund_requests WHERE user_id = ? AND order_id = ? AND product_id = ? AND order_item_id = ? AND status IN ('pending', 'approved', 'processing')",
            [$user_id, $order_id, $product_id, $order_item_id]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'A refund request already exists for this item'];
        }
        
        // Create refund request
        $refund_id = $db->insert(
            "INSERT INTO refund_requests (user_id, order_id, product_id, order_item_id, refund_amount, refund_reason, customer_message, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$user_id, $order_id, $product_id, $order_item_id, $refund_amount, $refund_reason, $customer_message]
        );
        
        if ($refund_id) {
            // Create notification for admin
            createNotification(null, 'refund_request', 'New Refund Request', "A new refund request has been submitted for Order #{$order_id}");
            
            return ['success' => true, 'message' => 'Refund request submitted successfully', 'refund_id' => $refund_id];
        } else {
            return ['success' => false, 'message' => 'Failed to submit refund request'];
        }
        
    } catch (Exception $e) {
        error_log("Error submitting refund request: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error submitting refund request'];
    }
}

// Get refund requests for admin
function getRefundRequests($status = null, $limit = 50) {
    $db = Database::getInstance();
    
    try {
        $sql = "SELECT rr.*, u.first_name, u.last_name, u.email, o.order_number, p.product_name, p.image_url
                FROM refund_requests rr
                JOIN users u ON rr.user_id = u.user_id
                JOIN orders o ON rr.order_id = o.order_id
                JOIN products p ON rr.product_id = p.product_id";
        
        $params = [];
        
        if ($status) {
            $sql .= " WHERE rr.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY rr.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $db->fetchAll($sql, $params);
        
    } catch (Exception $e) {
        error_log("Error getting refund requests: " . $e->getMessage());
        return [];
    }
}

// Get refund requests for a specific user
function getUserRefundRequests($user_id) {
    $db = Database::getInstance();
    
    try {
        return $db->fetchAll(
            "SELECT rr.*, o.order_number, p.product_name, p.image_url
             FROM refund_requests rr
             JOIN orders o ON rr.order_id = o.order_id
             JOIN products p ON rr.product_id = p.product_id
             WHERE rr.user_id = ?
             ORDER BY rr.created_at DESC",
            [$user_id]
        );
        
    } catch (Exception $e) {
        error_log("Error getting user refund requests: " . $e->getMessage());
        return [];
    }
}

// Process refund request (approve/decline)
function processRefundRequest($refund_id, $status, $admin_id, $admin_message = '') {
    $db = Database::getInstance();
    
    try {
        // Get refund request details
        $refund = $db->fetchOne(
            "SELECT * FROM refund_requests WHERE refund_id = ?",
            [$refund_id]
        );
        
        if (!$refund) {
            return ['success' => false, 'message' => 'Refund request not found'];
        }
        
        // Update refund request (only set admin_id if it's valid)
        if ($admin_id && $admin_id > 0) {
            // Check if admin user exists
            $admin_exists = $db->fetchOne("SELECT user_id FROM users WHERE user_id = ?", [$admin_id]);
            if (!$admin_exists) {
                $admin_id = null; // Set to null if admin doesn't exist
            }
        } else {
            $admin_id = null;
        }
        
        $result = $db->execute(
            "UPDATE refund_requests SET status = ?, admin_id = ?, admin_message = ?, processed_at = NOW() WHERE refund_id = ?",
            [$status, $admin_id, $admin_message, $refund_id]
        );
        
        if ($result) {
            // If refund is approved, deduct from product sales count
            if ($status === 'approved') {
                // Update the product's sales count by recalculating it (removing this refunded purchase)
                updateProductSalesCount($refund['product_id']);
                
                error_log("Refund approved - Updated sales count for product ID: " . $refund['product_id']);
            }
            
            // Create notification for customer
            $notification_title = $status === 'approved' ? 'Refund Request Approved' : 'Refund Request Declined';
            $notification_message = $admin_message ?: ($status === 'approved' ? 'Your refund request has been approved and is being processed.' : 'Your refund request has been declined.');
            
            createNotification($refund['user_id'], 'refund_update', $notification_title, $notification_message);
            
            return ['success' => true, 'message' => 'Refund request processed successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to process refund request'];
        }
        
    } catch (Exception $e) {
        error_log("Error processing refund request: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error processing refund request'];
    }
}

// Get refund request details
function getRefundRequest($refund_id) {
    $db = Database::getInstance();
    
    try {
        return $db->fetchOne(
            "SELECT rr.*, u.first_name, u.last_name, u.email, o.order_number, p.product_name, p.image_url,
                    admin.first_name as admin_first_name, admin.last_name as admin_last_name
             FROM refund_requests rr
             JOIN users u ON rr.user_id = u.user_id
             JOIN orders o ON rr.order_id = o.order_id
             JOIN products p ON rr.product_id = p.product_id
             LEFT JOIN users admin ON rr.admin_id = admin.user_id
             WHERE rr.refund_id = ?",
            [$refund_id]
        );
        
    } catch (Exception $e) {
        error_log("Error getting refund request: " . $e->getMessage());
        return null;
    }
}

// Redirect function
function redirect($url) {
    // Try header redirect first, fallback to JavaScript
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    } else {
        // Use JavaScript redirect as fallback
        echo "<script>window.location.href = '$url';</script>";
        exit();
    }
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Generate order number
function generateOrderNumber() {
    return 'EL' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Review notification functions
function createReviewNotification($user_id, $order_id, $product_id, $type = 'delivery_confirmed') {
    $db = Database::getInstance();
    
    try {
        // Check if notification already exists
        $existing = $db->fetchOne(
            "SELECT notification_id FROM review_notifications 
             WHERE user_id = ? AND order_id = ? AND product_id = ? AND notification_type = ?",
            [$user_id, $order_id, $product_id, $type]
        );
        
        if (!$existing) {
            $result = $db->insert(
                "INSERT INTO review_notifications (user_id, order_id, product_id, notification_type) VALUES (?, ?, ?, ?)",
                [$user_id, $order_id, $product_id, $type]
            );
            
            return $result;
        }
        
        return false; // Notification already exists
    } catch (Exception $e) {
        error_log("Failed to create review notification: " . $e->getMessage());
        return false;
    }
}

function getReviewNotifications($user_id, $unread_only = true) {
    $db = Database::getInstance();
    
    try {
        $sql = "SELECT rn.*, p.product_name, p.image_url, o.order_number, o.order_date 
                FROM review_notifications rn
                JOIN products p ON rn.product_id = p.product_id
                JOIN orders o ON rn.order_id = o.order_id
                WHERE rn.user_id = ? AND rn.expires_at > NOW()";
        
        $params = [$user_id];
        
        if ($unread_only) {
            $sql .= " AND rn.is_read = FALSE";
        }
        
        $sql .= " ORDER BY rn.created_at DESC";
        
        return $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        // Table doesn't exist yet, return empty array
        return [];
    }
}

function markReviewNotificationAsRead($notification_id, $user_id) {
    $db = Database::getInstance();
    
    return $db->execute(
        "UPDATE review_notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?",
        [$notification_id, $user_id]
    );
}

function getUnreadReviewNotificationCount($user_id) {
    $db = Database::getInstance();
    
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM review_notifications 
         WHERE user_id = ? AND is_read = FALSE AND expires_at > NOW()",
        [$user_id]
    );
    
    return $result['count'] ?? 0;
}

function submitProductReview($user_id, $product_id, $order_id, $rating, $title, $comment) {
    $db = Database::getInstance();
    
    try {
        // Insert review
        $review_id = $db->insert(
            "INSERT INTO reviews (user_id, product_id, order_id, rating, title, comment, is_verified) VALUES (?, ?, ?, ?, ?, ?, TRUE)",
            [$user_id, $product_id, $order_id, $rating, $title, $comment]
        );
        
        if ($review_id) {
            // Update product rating and review count
            $db->execute(
                "UPDATE products SET 
                 rating = (SELECT AVG(rating) FROM reviews WHERE product_id = ?),
                 review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?)
                 WHERE product_id = ?",
                [$product_id, $product_id, $product_id]
            );
            
            // Update sales count with correct calculation (only active users)
            updateProductSalesCount($product_id);
            
            // Mark review notification as completed (ignore if table doesn't exist)
            try {
                $db->execute(
                    "UPDATE review_notifications SET notification_type = 'review_completed', is_read = TRUE 
                     WHERE user_id = ? AND product_id = ? AND order_id = ?",
                    [$user_id, $product_id, $order_id]
                );
            } catch (Exception $e) {
                // Ignore notification update errors
                error_log("Review notification update failed: " . $e->getMessage());
            }
            
            return $review_id;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Failed to submit review: " . $e->getMessage());
        error_log("Review data: user_id=$user_id, product_id=$product_id, order_id=$order_id, rating=$rating, title=$title, comment=$comment");
        return false;
    }
}

function getProductSalesCount($product_id) {
    $db = Database::getInstance();
    
    try {
        $result = $db->fetchOne(
            "SELECT sales_count FROM products WHERE product_id = ?",
            [$product_id]
        );
        
        return $result['sales_count'] ?? 0;
    } catch (Exception $e) {
        // Column doesn't exist yet, return 0
        return 0;
    }
}

// Recalculate actual sales count for a product based on real order data
function recalculateProductSalesCount($product_id) {
    $db = Database::getInstance();
    
    try {
        // Count unique orders (not quantity) for this product
        $result = $db->fetchOne(
            "SELECT COUNT(DISTINCT oi.order_id) as actual_count 
             FROM order_items oi 
             JOIN orders o ON oi.order_id = o.order_id 
             WHERE oi.product_id = ? AND o.status != 'cancelled' AND o.status != 'refunded'",
            [$product_id]
        );
        
        $actual_count = $result['actual_count'] ?? 0;
        
        // Update the product's sales_count
        $db->execute(
            "UPDATE products SET sales_count = ? WHERE product_id = ?",
            [$actual_count, $product_id]
        );
        
        return $actual_count;
    } catch (Exception $e) {
        error_log("Failed to recalculate sales count for product $product_id: " . $e->getMessage());
        return false;
    }
}

// Recalculate sales counts for all products
function recalculateAllProductSalesCounts() {
    $db = Database::getInstance();
    
    try {
        $products = $db->fetchAll("SELECT product_id FROM products");
        $updated_count = 0;
        
        foreach ($products as $product) {
            if (recalculateProductSalesCount($product['product_id']) !== false) {
                $updated_count++;
            }
        }
        
        return $updated_count;
    } catch (Exception $e) {
        error_log("Failed to recalculate all sales counts: " . $e->getMessage());
        return false;
    }
}

// User Management Functions

// Get user details for admin
function getUserDetails($user_id) {
    $db = Database::getInstance();
    return $db->fetchOne(
        "SELECT u.*, 
                COUNT(DISTINCT o.order_id) as total_orders,
                COUNT(DISTINCT r.review_id) as total_reviews,
                COUNT(DISTINCT n.id) as unread_notifications
         FROM users u 
         LEFT JOIN orders o ON u.user_id = o.user_id 
         LEFT JOIN reviews r ON u.user_id = r.user_id 
         LEFT JOIN user_notifications n ON u.user_id = n.user_id AND n.is_read = FALSE
         WHERE u.user_id = ? 
         GROUP BY u.user_id", 
        [$user_id]
    );
}

// Send warning to user
function sendUserWarning($user_id, $admin_user_id, $reason) {
    $db = Database::getInstance();
    
    try {
        // Update user warning count and status
        $update_result = $db->execute(
            "UPDATE users SET warning_count = warning_count + 1, last_warning_date = NOW(), status = 'warned' WHERE user_id = ?",
            [$user_id]
        );
        error_log("User update result: " . $update_result);
        
        // Create notification
        $notification_result = createNotification($user_id, 'warning', '⚠️ Account Warning', "You have received a warning from an administrator. Reason: " . $reason);
        error_log("Notification creation result: " . ($notification_result ? 'TRUE' : 'FALSE'));
        
        // Log admin action (only if admin_user_id is valid)
        if ($admin_user_id > 0) {
            $admin_action_result = $db->execute(
                "INSERT INTO admin_actions (admin_user_id, target_user_id, action_type, reason) VALUES (?, ?, 'warning', ?)",
                [$admin_user_id, $user_id, $reason]
            );
            error_log("Admin action result: " . $admin_action_result);
        } else {
            error_log("Skipping admin action logging - admin_user_id is 0");
        }
        
        // Always return true since all operations are working
        error_log("sendUserWarning returning TRUE");
        return true;
    } catch (Exception $e) {
        error_log("Error sending warning: " . $e->getMessage());
        error_log("Error file: " . $e->getFile());
        error_log("Error line: " . $e->getLine());
        return false;
    }
}

// Ban user
function banUser($user_id, $admin_user_id, $reason) {
    $db = Database::getInstance();
    
    try {
        // Update user status
        $db->execute(
            "UPDATE users SET status = 'banned', ban_reason = ?, ban_date = NOW() WHERE user_id = ?",
            [$reason, $user_id]
        );
        
        // Create notification
        $result = createNotification($user_id, 'ban', '🚫 Account Banned', "Your account has been banned. Reason: " . $reason);
        
        // Log admin action
        $db->execute(
            "INSERT INTO admin_actions (admin_user_id, target_user_id, action_type, reason) VALUES (?, ?, 'ban', ?)",
            [$admin_user_id, $user_id, $reason]
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Error banning user: " . $e->getMessage());
        return false;
    }
}

// Unban user
function unbanUser($user_id, $admin_user_id, $reason = 'Account unbanned by administrator') {
    $db = Database::getInstance();
    
    try {
        // Update user status
        $db->execute(
            "UPDATE users SET status = 'active', ban_reason = NULL, ban_date = NULL WHERE user_id = ?",
            [$user_id]
        );
        
        // Create notification
        $result = createNotification($user_id, 'unban', '✅ Account Unbanned', "Your account has been unbanned. Reason: " . $reason);
        
        // Log admin action
        $db->execute(
            "INSERT INTO admin_actions (admin_user_id, target_user_id, action_type, reason) VALUES (?, ?, 'unban', ?)",
            [$admin_user_id, $user_id, $reason]
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Error unbanning user: " . $e->getMessage());
        return false;
    }
}

// Get user notifications
function getUserNotifications($user_id, $limit = 10) {
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$user_id, $limit]
    );
}

// Mark notification as read
function markNotificationAsRead($notification_id, $user_id) {
    $db = Database::getInstance();
    return $db->execute(
        "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?",
        [$notification_id, $user_id]
    );
}

// Get admin actions for a user
function getUserAdminActions($user_id) {
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT aa.*, u.first_name, u.last_name 
         FROM admin_actions aa 
         JOIN users u ON aa.admin_user_id = u.user_id 
         WHERE aa.target_user_id = ? 
         ORDER BY aa.created_at DESC",
        [$user_id]
    );
}

// Delete user and all related data
function deleteUser($user_id, $admin_user_id, $reason = 'User deleted by administrator') {
    $db = Database::getInstance();
    
    // Debug logging
    error_log("deleteUser function called - User ID: $user_id, Admin ID: $admin_user_id, Reason: $reason");
    
    try {
        // Start transaction
        $db->beginTransaction();
        error_log("Transaction started for user deletion");
        
        // Delete from related tables first (in order of dependencies)
        // 1. Delete cart items
        $db->execute("DELETE FROM cart WHERE user_id = ?", [$user_id]);
        
        // 2. Delete wishlist items
        $db->execute("DELETE FROM wishlist WHERE user_id = ?", [$user_id]);
        
        // 3. Delete user notifications
        $db->execute("DELETE FROM user_notifications WHERE user_id = ?", [$user_id]);
        
        // 4. Delete notifications
        $db->execute("DELETE FROM notifications WHERE user_id = ?", [$user_id]);
        
        // 5. Delete reviews
        $db->execute("DELETE FROM reviews WHERE user_id = ?", [$user_id]);
        
        // 6. Delete order-related data (refund requests, review notifications, then order items)
        $orders = $db->fetchAll("SELECT order_id FROM orders WHERE user_id = ?", [$user_id]);
        if (!empty($orders)) {
            $orderIds = array_column($orders, 'order_id');
            $inClause = implode(',', array_fill(0, count($orderIds), '?'));

            // 6a. Delete refund requests linked to this user's orders (prevents FK errors on order_items)
            try {
                $db->execute(
                    "DELETE rr FROM refund_requests rr JOIN orders o ON rr.order_id = o.order_id WHERE o.user_id = $user_id"
                );
            } catch (Exception $e) {
                error_log("Warning: refund_requests delete failed: " . $e->getMessage());
            }

            // 6b. Delete review notifications linked to this user's orders
            try {
                $db->execute(
                    "DELETE rn FROM review_notifications rn JOIN orders o ON rn.order_id = o.order_id WHERE o.user_id = $user_id"
                );
            } catch (Exception $e) {
                // Not critical; continue
            }

            // 6c. Delete order items for each order
            foreach ($orders as $order) {
                $db->execute("DELETE FROM order_items WHERE order_id = ?", [$order['order_id']]);
            }
        }
        
        // 7. Delete orders (before addresses to avoid foreign key constraint)
        $db->execute("DELETE FROM orders WHERE user_id = ?", [$user_id]);
        
        // 8. Delete addresses (after orders are deleted)
        $db->execute("DELETE FROM addresses WHERE user_id = ?", [$user_id]);
        
        // 9. Delete admin actions related to this user
        $db->execute("DELETE FROM admin_actions WHERE target_user_id = ?", [$user_id]);
        
        // 10. Finally delete the user
        $result = $db->execute("DELETE FROM users WHERE user_id = ?", [$user_id]);
        
        if ($result) {
            // 11. Recalculate all product statistics after user deletion
            recalculateAllProductStatistics();
            // Log admin action (only if admin_user_id is valid and not the same as target user)
            if ($admin_user_id > 0 && $admin_user_id != $user_id) {
                try {
                    $db->execute(
                        "INSERT INTO admin_actions (admin_user_id, target_user_id, action_type, reason) VALUES (?, ?, 'delete_user', ?)",
                        [$admin_user_id, $user_id, $reason]
                    );
                } catch (Exception $e) {
                    // If admin_actions insert fails, log it but don't fail the deletion
                    error_log("Warning: Could not log admin action: " . $e->getMessage());
                }
            }
            
            // Commit transaction
            $db->commit();
            error_log("User deletion completed successfully");
            return true;
        } else {
            $db->rollback();
            error_log("User deletion failed - no rows affected");
            return false;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        error_log("Error deleting user: " . $e->getMessage());
        return false;
    }
}

// Recalculate all product statistics (sales count, review count, ratings)
function recalculateAllProductStatistics() {
    $db = Database::getInstance();
    
    try {
        // Get all products
        $products = $db->fetchAll("SELECT product_id FROM products");
        
        foreach ($products as $product) {
            $product_id = $product['product_id'];
            
            // Calculate actual review count and average rating
            $review_stats = $db->fetchOne("
                SELECT 
                    COUNT(*) as review_count,
                    AVG(rating) as avg_rating
                FROM reviews 
                WHERE product_id = ?
            ", [$product_id]);
            
            $review_count = $review_stats['review_count'] ?? 0;
            $avg_rating = $review_stats['avg_rating'] ? round($review_stats['avg_rating'], 2) : 0.00;
            
            // Calculate actual sales count from unique ACTIVE users who bought this product and haven't refunded it
            $sales_stats = $db->fetchOne("
                SELECT COUNT(DISTINCT o.user_id) as unique_users
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                JOIN users u ON o.user_id = u.user_id
                WHERE oi.product_id = ? 
                AND o.status = 'delivered' 
                AND u.user_id IS NOT NULL
                AND oi.order_item_id NOT IN (
                    SELECT DISTINCT rr.order_item_id 
                    FROM refund_requests rr 
                    WHERE rr.product_id = oi.product_id 
                    AND rr.order_item_id = oi.order_item_id 
                    AND rr.status IN ('approved', 'processing', 'completed')
                )
            ", [$product_id]);
            
            $sales_count = $sales_stats['unique_users'] ?? 0;
            
            // Update product with correct statistics
            $db->execute("
                UPDATE products SET 
                    sales_count = ?,
                    review_count = ?,
                    rating = ?
                WHERE product_id = ?
            ", [$sales_count, $review_count, $avg_rating, $product_id]);
        }
        
        error_log("Product statistics recalculated successfully");
        return true;
        
    } catch (Exception $e) {
        error_log("Error recalculating product statistics: " . $e->getMessage());
        return false;
    }
}

// User Concerns Functions
function createUserConcern($user_id, $name, $email, $subject, $message) {
    $db = Database::getInstance();
    
    try {
        // First, create the table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS user_concerns (
            concern_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
            admin_reply TEXT NULL,
            admin_replied_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
        )";
        
        $db->execute($create_table_sql);
        
        // Insert the concern
        $concern_id = $db->insert(
            "INSERT INTO user_concerns (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)",
            [$user_id, $name, $email, $subject, $message]
        );
        
        if ($concern_id) {
            // Send email notification to admin
            sendConcernNotificationToAdmin($concern_id, $name, $email, $subject, $message);
            return $concern_id;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error creating user concern: " . $e->getMessage());
        return false;
    }
}

function getAllUserConcerns($status = null, $limit = 50) {
    $db = Database::getInstance();
    
    try {
        $sql = "SELECT uc.*, u.first_name, u.last_name, u.email as user_email 
                FROM user_concerns uc 
                LEFT JOIN users u ON uc.user_id = u.user_id 
                ORDER BY uc.created_at DESC";
        
        $params = [];
        
        if ($status) {
            $sql = "SELECT uc.*, u.first_name, u.last_name, u.email as user_email 
                    FROM user_concerns uc 
                    LEFT JOIN users u ON uc.user_id = u.user_id 
                    WHERE uc.status = ? 
                    ORDER BY uc.created_at DESC";
            $params = [$status];
        }
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        return $db->fetchAll($sql, $params);
        
    } catch (Exception $e) {
        error_log("Error fetching user concerns: " . $e->getMessage());
        return [];
    }
}

function getConcernById($concern_id) {
    $db = Database::getInstance();
    
    try {
        return $db->fetchOne(
            "SELECT uc.*, u.first_name, u.last_name, u.email as user_email 
             FROM user_concerns uc 
             LEFT JOIN users u ON uc.user_id = u.user_id 
             WHERE uc.concern_id = ?",
            [$concern_id]
        );
    } catch (Exception $e) {
        error_log("Error fetching concern by ID: " . $e->getMessage());
        return false;
    }
}


function getConcernStats() {
    $db = Database::getInstance();
    
    try {
        $stats = $db->fetchOne("
            SELECT 
                COUNT(*) as total_concerns,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_concerns,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_concerns,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_concerns,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_concerns
            FROM user_concerns
        ");
        
        return $stats ?: [
            'total_concerns' => 0,
            'new_concerns' => 0,
            'in_progress_concerns' => 0,
            'resolved_concerns' => 0,
            'closed_concerns' => 0
        ];
        
    } catch (Exception $e) {
        error_log("Error fetching concern stats: " . $e->getMessage());
        return [
            'total_concerns' => 0,
            'new_concerns' => 0,
            'in_progress_concerns' => 0,
            'resolved_concerns' => 0,
            'closed_concerns' => 0
        ];
    }
}

function sendConcernNotificationToAdmin($concern_id, $name, $email, $subject, $message) {
    // This would send an email to admin about new concern
    // For now, we'll just log it
    error_log("New user concern received - ID: $concern_id, From: $name ($email), Subject: $subject");
}

function sendConcernReplyToUser($concern, $admin_reply) {
    // This would send an email reply to the user
    // For now, we'll just log it
    error_log("Admin reply sent to user - Concern ID: {$concern['concern_id']}, User: {$concern['email']}");                                                    
}

/**
 * Submit a user concern/contact form message
 */
function submitUserConcern($name, $email, $subject, $message, $user_id = null) {
    $db = Database::getInstance();
    
    try {
        $concern_id = $db->insert(
            "INSERT INTO user_concerns (user_id, name, email, subject, message, status) 
             VALUES (?, ?, ?, ?, ?, 'new')",
            [$user_id, $name, $email, $subject, $message]
        );
        
        if ($concern_id) {
            // Create notification for admin
            createNotification(null, 'user_concern', 'New User Concern', "A new concern has been submitted: {$subject}");
            
            return ['success' => true, 'message' => 'Your concern has been submitted successfully', 'concern_id' => $concern_id];
        } else {
            return ['success' => false, 'message' => 'Failed to submit concern'];
        }
        
    } catch (Exception $e) {
        error_log("Error submitting user concern: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error submitting concern'];
    }
}

/**
 * Get user concerns for admin panel
 */
function getUserConcerns($status = null, $limit = 50) {
    $db = Database::getInstance();
    
    try {
        $sql = "SELECT uc.*, u.first_name, u.last_name, u.email as user_email
                FROM user_concerns uc
                LEFT JOIN users u ON uc.user_id = u.user_id
                WHERE 1=1";
        
        $params = [];
        
        if ($status && $status !== 'all') {
            if ($status === 'unread') {
                $sql .= " AND uc.status = 'new'";
            } elseif ($status === 'read') {
                $sql .= " AND uc.status IN ('in_progress', 'resolved')";
            } elseif ($status === 'replied') {
                $sql .= " AND uc.status = 'closed'";
            } else {
                $sql .= " AND uc.status = ?";
                $params[] = $status;
            }
        }
        
        $sql .= " ORDER BY uc.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $db->fetchAll($sql, $params);
        
    } catch (Exception $e) {
        error_log("Error getting user concerns: " . $e->getMessage());
        return [];
    }
}

/**
 * Update concern status
 */
function updateConcernStatus($concern_id, $status, $admin_reply = '', $admin_id = null) {
    $db = Database::getInstance();
    
    try {
        $sql = "UPDATE user_concerns SET status = ?, updated_at = NOW()";
        $params = [$status];
        
        if (!empty($admin_reply)) {
            $sql .= ", admin_reply = ?";
            $params[] = $admin_reply;
        }
        
        if ($status === 'closed') {
            $sql .= ", admin_replied_at = NOW()";
        }
        
        $sql .= " WHERE concern_id = ?";
        $params[] = $concern_id;
        
        $result = $db->execute($sql, $params);
        
        if ($result) {
            // Get concern details for notification
            $concern = $db->fetchOne(
                "SELECT * FROM user_concerns WHERE concern_id = ?",
                [$concern_id]
            );
            
            if ($concern && $concern['user_id']) {
                // Create notification for user if they have an account
                $notification_title = 'Response to Your Concern';
                $notification_message = "We have responded to your concern: {$concern['subject']}";
                
                createNotification($concern['user_id'], 'concern_response', $notification_title, $notification_message);
            }
            
            return ['success' => true, 'message' => 'Concern updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update concern'];
        }
        
    } catch (Exception $e) {
        error_log("Error updating concern status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating concern'];
    }
}

/**
 * Get a single concern by ID
 */
function getConcern($concern_id) {
    $db = Database::getInstance();
    
    try {
        return $db->fetchOne(
            "SELECT uc.*, u.first_name, u.last_name, u.email as user_email
             FROM user_concerns uc
             LEFT JOIN users u ON uc.user_id = u.user_id
             WHERE uc.concern_id = ?",
            [$concern_id]
        );
    } catch (Exception $e) {
        error_log("Error getting concern: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete a user concern
 */
function deleteConcern($concern_id) {
    $db = Database::getInstance();
    
    try {
        // Check if concern exists
        $concern = $db->fetchOne(
            "SELECT * FROM user_concerns WHERE concern_id = ?",
            [$concern_id]
        );
        
        if (!$concern) {
            return ['success' => false, 'message' => 'Concern not found'];
        }
        
        // Delete the concern
        $result = $db->execute(
            "DELETE FROM user_concerns WHERE concern_id = ?",
            [$concern_id]
        );
        
        if ($result) {
            return ['success' => true, 'message' => 'Concern deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete concern'];
        }
        
    } catch (Exception $e) {
        error_log("Error deleting concern: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error deleting concern'];
    }
}
?>
