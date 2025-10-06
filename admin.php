<?php
// Start session before any output (safely)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Handle AJAX requests FIRST, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Debug: Log the incoming request
    error_log("AJAX Request received - Action: " . ($_POST['action'] ?? 'none') . ", Session admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'true' : 'false') : 'not set'));
    
    // Check if admin is logged in for AJAX requests
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance();
        
        switch (trim($_POST['action'])) {
            case 'add_product':
                $product_name = $_POST['product_name'];
                $description = $_POST['description'];
                $price = floatval($_POST['price']);
                $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
                $category_id = intval($_POST['category_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $image_url = $_POST['image_url'];
                // Normalize additional_images JSON
                $additional_images = null;
                if (!empty($_POST['additional_images'])) {
                    $decoded = json_decode($_POST['additional_images'], true);
                    if (is_array($decoded)) {
                        $decoded = array_values(array_filter($decoded, function($u){ return is_string($u) && trim($u) !== ''; }));
                        $decoded = array_slice($decoded, 0, 3); // front, side, back
                        $additional_images = json_encode($decoded);
                    }
                }
                $brand = $_POST['brand'];
                $color = $_POST['color'];
                $gender = $_POST['gender'];
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
                
                // Generate SKU
                $sku = strtoupper(substr($brand, 0, 3)) . rand(1000, 9999);
                
                // Debug: Log the data being inserted
                error_log("Adding product: Name=$product_name, Category=$category_id, Brand=$brand, SKU=$sku");
                
                $result = $db->insert(
                    "INSERT INTO products (product_name, description, price, sale_price, category_id, stock_quantity, image_url, additional_images, brand, color, gender, is_featured, is_new_arrival, is_active, sku, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                    [$product_name, $description, $price, $sale_price, $category_id, $stock_quantity, $image_url, $additional_images, $brand, $color, $gender, $is_featured, $is_new_arrival, $sku]
                );
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Product added successfully', 'product_id' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add product']);
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                // Delete from related tables first
                $db->execute("DELETE FROM cart WHERE product_id = ?", [$product_id]);
                $db->execute("DELETE FROM wishlist WHERE product_id = ?", [$product_id]);
                $db->execute("DELETE FROM order_items WHERE product_id = ?", [$product_id]);
                $db->execute("DELETE FROM reviews WHERE product_id = ?", [$product_id]);
                
                // Delete the product
                $db->execute("DELETE FROM products WHERE product_id = ?", [$product_id]);
                
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
                break;
                
            case 'update_stock':
                $product_id = intval($_POST['product_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                
                $db->execute("UPDATE products SET stock_quantity = ? WHERE product_id = ?", [$stock_quantity, $product_id]);
                
                echo json_encode(['success' => true, 'message' => 'Stock updated successfully!']);
                break;
                
            case 'update_product':
                $product_id = intval($_POST['product_id']);
                $product_name = $_POST['product_name'];
                $description = $_POST['description'];
                $price = floatval($_POST['price']);
                $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
                $category_id = intval($_POST['category_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $image_url = $_POST['image_url'];
                $brand = $_POST['brand'];
                $color = $_POST['color'];
                $gender = $_POST['gender'];
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
                // Normalize additional_images JSON for update
                $additional_images = null;
                if (isset($_POST['additional_images'])) {
                    $decoded = json_decode($_POST['additional_images'] ?: '[]', true);
                    if (is_array($decoded)) {
                        $decoded = array_values(array_filter($decoded, function($u){ return is_string($u) && trim($u) !== ''; }));
                        $decoded = array_slice($decoded, 0, 3);
                        $additional_images = json_encode($decoded);
                    } else {
                        $additional_images = null;
                    }
                }
                
                $db->execute(
                    "UPDATE products SET product_name = ?, description = ?, price = ?, sale_price = ?, category_id = ?, stock_quantity = ?, image_url = ?, additional_images = ?, brand = ?, color = ?, gender = ?, is_featured = ?, is_new_arrival = ?, updated_at = NOW() WHERE product_id = ?",
                    [$product_name, $description, $price, $sale_price, $category_id, $stock_quantity, $image_url, $additional_images, $brand, $color, $gender, $is_featured, $is_new_arrival, $product_id]
                );
                
                echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
                break;
                
            case 'get_order_details':
                $order_id = intval($_POST['order_id']);
                
                // Get order details
                $order = $db->fetchOne(
                    "SELECT o.*, u.first_name, u.last_name, u.email, u.phone,
                            sa.first_name as ship_first, sa.last_name as ship_last, sa.address_line1 as ship_address, 
                            sa.city as ship_city, sa.state as ship_state, sa.postal_code as ship_postal, sa.country as ship_country,
                            ba.first_name as bill_first, ba.last_name as bill_last, ba.address_line1 as bill_address,
                            ba.city as bill_city, ba.state as bill_state, ba.postal_code as bill_postal, ba.country as bill_country
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.user_id
                     LEFT JOIN addresses sa ON o.shipping_address_id = sa.address_id
                     LEFT JOIN addresses ba ON o.billing_address_id = ba.address_id
                     WHERE o.order_id = ?", 
                    [$order_id]
                );
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    break;
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
                    'items' => $order_items
                ]);
                break;
                
            case 'update_order_status':
                $order_id = intval($_POST['order_id']);
                $new_status = sanitizeInput($_POST['status']);
                
                // Validate status
                $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                if (!in_array($new_status, $valid_statuses)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
                    break;
                }
                
                // Get order details for notification
                $order = $db->fetchOne(
                    "SELECT o.*, u.first_name, u.last_name, u.email FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.user_id 
                     WHERE o.order_id = ?", 
                    [$order_id]
                );
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    break;
                }
                
                // Update order status
                $db->execute("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?", [$new_status, $order_id]);
                
                // Create notification for user
                $status_messages = [
                    'processing' => 'Your order has been confirmed and is being processed.',
                    'shipped' => 'Your order has been shipped and is on its way!',
                    'delivered' => 'Your order has been delivered successfully.',
                    'cancelled' => 'Your order has been cancelled.'
                ];
                
                $status_titles = [
                    'processing' => 'Order Confirmed',
                    'shipped' => 'Order Shipped',
                    'delivered' => 'Order Delivered',
                    'cancelled' => 'Order Cancelled'
                ];
                
                if (isset($status_messages[$new_status]) && $order['user_id']) {
                    // Create notification for the user
                    $db->insert(
                        "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())",
                        [
                            $order['user_id'],
                            $status_titles[$new_status],
                            $status_messages[$new_status],
                            'order_status'
                        ]
                    );
                    
                    // If marking as delivered, also create review notifications
                    if ($new_status === 'delivered') {
                        $order_items = $db->fetchAll("SELECT product_id FROM order_items WHERE order_id = ?", [$order_id]);
                        foreach ($order_items as $item) {
                            createReviewNotification($order['user_id'], $order_id, $item['product_id']);
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Order status updated successfully!']);
                break;
                
            case 'mark_delivered':
                $order_id = intval($_POST['order_id']);
                
                // Update order status
                $db->execute("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE order_id = ?", [$order_id]);
                
                // Create review notifications for each product in the order
                $order_items = $db->fetchAll("SELECT product_id FROM order_items WHERE order_id = ?", [$order_id]);
                $order_info = $db->fetchOne("SELECT user_id FROM orders WHERE order_id = ?", [$order_id]);
                
                if ($order_info && $order_items) {
                    foreach ($order_items as $item) {
                        createReviewNotification($order_info['user_id'], $order_id, $item['product_id']);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Order marked as delivered and review notifications sent!']);
                break;
                
            case 'add_carousel_item':
                $image_url = sanitizeInput($_POST['image_url'] ?? '');
                $title = sanitizeInput($_POST['title'] ?? '');
                $subtitle = sanitizeInput($_POST['subtitle'] ?? '');
                $button_text = sanitizeInput($_POST['button_text'] ?? '');
                $button_link = sanitizeInput($_POST['button_link'] ?? '');
                $display_order = intval($_POST['display_order'] ?? 0);
                
                if (empty($image_url) || empty($title)) {
                    echo json_encode(['success' => false, 'message' => 'Image URL and title are required']);
                    break;
                }
                
                $result = addCarouselItem($image_url, $title, $subtitle, $button_text, $button_link, $display_order);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Carousel item added successfully!', 'item_id' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add carousel item']);
                }
                break;
                
            case 'update_carousel_item':
                $id = intval($_POST['id'] ?? 0);
                $image_url = sanitizeInput($_POST['image_url'] ?? '');
                $title = sanitizeInput($_POST['title'] ?? '');
                $subtitle = sanitizeInput($_POST['subtitle'] ?? '');
                $button_text = sanitizeInput($_POST['button_text'] ?? '');
                $button_link = sanitizeInput($_POST['button_link'] ?? '');
                $display_order = intval($_POST['display_order'] ?? 0);
                
                if ($id <= 0 || empty($image_url) || empty($title)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
                    break;
                }
                
                $result = updateCarouselItem($id, $image_url, $title, $subtitle, $button_text, $button_link, $display_order);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Carousel item updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update carousel item']);
                }
                break;
                
            case 'delete_carousel_item':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Invalid carousel item ID']);
                    break;
                }
                
                $result = deleteCarouselItem($id);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Carousel item deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete carousel item']);
                }
                break;
                
            case 'update_hero_content':
                // Debug: Log the action being processed
                error_log("Processing update_hero_content action - SUCCESS!");
                
                $title = sanitizeInput($_POST['title'] ?? '');
                $subtitle = sanitizeInput($_POST['subtitle'] ?? '');
                $button_text = sanitizeInput($_POST['button_text'] ?? '');
                $button_link = sanitizeInput($_POST['button_link'] ?? '');
                
                // Debug: Log the received data
                error_log("Hero content data: title=$title, subtitle=$subtitle, button_text=$button_text, button_link=$button_link");
                
                if (empty($title) || empty($subtitle) || empty($button_text) || empty($button_link)) {
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    break;
                }
                
                $result = updateHeroContent($title, $subtitle, $button_text, $button_link);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Hero content updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update hero content']);
                }
                break;
                
            case 'get_product_details':
                error_log("FIRST SWITCH: Processing get_product_details for ID: " . ($_POST['product_id'] ?? 'none'));
                $product_id = intval($_POST['product_id']);
                
                $product = $db->fetchOne(
                    "SELECT p.*, c.category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.category_id 
                     WHERE p.product_id = ?", 
                    [$product_id]
                );
                
                if (!$product) {
                    error_log("FIRST SWITCH: Product not found for ID: $product_id");
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    break;
                }
                
                // Debug logging
                error_log("FIRST SWITCH: Product details for ID $product_id: " . json_encode($product));
                
                echo json_encode(['success' => true, 'product' => $product]);
                break;
                
            case 'get_user_details':
                $user_id = intval($_POST['user_id']);
                $user = getUserDetails($user_id);
                
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    break;
                }
                
                // Get admin actions for this user
                $admin_actions = getUserAdminActions($user_id);
                $user['admin_actions'] = $admin_actions;
                
                echo json_encode(['success' => true, 'user' => $user]);
                break;
                
            case 'warn_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason']);
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Warning reason is required']);
                    break;
                }
                
                $result = sendUserWarning($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Warning sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send warning']);
                }
                break;
                
            case 'ban_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason']);
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Ban reason is required']);
                    break;
                }
                
                $result = banUser($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User banned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to ban user']);
                }
                break;
                
            case 'unban_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason'] ?? 'Account unbanned by administrator');
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                $result = unbanUser($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to unban user']);
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason'] ?? 'User deleted by administrator');
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                // Debug logging
                error_log("Delete user request - User ID: $user_id, Reason: $reason, Admin ID: $admin_user_id");
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Deletion reason is required']);
                    break;
                }
                
                try {
                    $result = deleteUser($user_id, $admin_user_id, $reason);
                    error_log("Delete user result: " . ($result ? 'true' : 'false'));
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete user - function returned false']);
                    }
                } catch (Exception $e) {
                    error_log("Delete user error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_refunds':
                $status = sanitizeInput($_POST['status'] ?? 'all');
                $status_filter = ($status === 'all') ? null : $status;
                
                $refunds = getRefundRequests($status_filter, 100);
                echo json_encode(['success' => true, 'refunds' => $refunds]);
                break;
                
            case 'process_refund':
                $refund_id = intval($_POST['refund_id']);
                $status = sanitizeInput($_POST['status']);
                $admin_message = sanitizeInput($_POST['admin_message'] ?? '');
                $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
                
                if (empty($refund_id) || empty($status)) {
                    echo json_encode(['success' => false, 'message' => 'Refund ID and status are required']);
                    break;
                }
                
                if (!in_array($status, ['approved', 'declined'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
                    break;
                }
                
                $result = processRefundRequest($refund_id, $status, $admin_id, $admin_message);
                echo json_encode($result);
                break;

            case 'get_concerns':
                $status = sanitizeInput($_POST['status'] ?? 'all');
                $status_filter = ($status === 'all') ? null : $status;
                
                $concerns = getUserConcerns($status_filter, 100);
                echo json_encode(['success' => true, 'concerns' => $concerns]);
                break;
                
            case 'update_concern':
                $concern_id = intval($_POST['concern_id']);
                $status = sanitizeInput($_POST['status']);
                $admin_reply = sanitizeInput($_POST['admin_reply'] ?? '');
                $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
                
                if (empty($concern_id) || empty($status)) {
                    echo json_encode(['success' => false, 'message' => 'Concern ID and status are required']);
                    break;
                }
                
                if (!in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
                    break;
                }
                
                $result = updateConcernStatus($concern_id, $status, $admin_reply, $admin_id);
                echo json_encode($result);
                break;
                
            case 'delete_concern':
                $concern_id = intval($_POST['concern_id']);
                
                if (empty($concern_id)) {
                    echo json_encode(['success' => false, 'message' => 'Concern ID is required']);
                    break;
                }
                
                $result = deleteConcern($concern_id);
                echo json_encode($result);
                break;
                
            default:
                // Debug: Log the action that wasn't matched
                $action = $_POST['action'] ?? 'no action';
                error_log("Unmatched action: '" . $action . "' (length: " . strlen($action) . ")");
                error_log("Action bytes: " . bin2hex($action));
                echo json_encode(['success' => false, 'message' => 'Invalid action: "' . $action . '" (length: ' . strlen($action) . ')']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Enhanced admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_POST['admin_login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
                // Enhanced admin authentication with database
        try {
            $db = Database::getInstance();
            $admin = $db->fetchOne(
                "SELECT * FROM admin_users WHERE username = ? AND is_active = 1", 
                [$username]
            );
            
            // Support both 'password' (hashed) and legacy 'password_hash' keys
            $storedHash = $admin['password'] ?? ($admin['password_hash'] ?? null);
            if ($admin && $storedHash && (password_verify($password, $storedHash) || $password === $storedHash)) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // If password was stored in plain text, upgrade it to a secure hash
                if ($password === $storedHash) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    try {
                        $db->execute("UPDATE admin_users SET password = ? WHERE admin_id = ?", [$newHash, $admin['admin_id']]);
                    } catch (Exception $e) {
                        // Ignore hashing upgrade failure to not block login
                        error_log('Admin password rehash failed: ' . $e->getMessage());
                    }
                }

                // Update last login
                $db->query(
                    "UPDATE admin_users SET last_login = NOW() WHERE admin_id = ?", 
                    [$admin['admin_id']]
                );
                
                // Redirect to admin panel after successful login
                header('Location: admin.php');
                exit;
            } else {
                $login_error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            // Fallback to simple authentication
            if ($username === 'admin' && $password === 'admin123') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['admin_role'] = 'super_admin';
                
                // Redirect to admin panel after successful login
                header('Location: admin.php');
                exit;
            } else {
                $login_error = 'Login failed: ' . $e->getMessage();
            }
        }
    }
    
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - EyeLux</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            /* MINIMALIST AESTHETIC THEME - KHAKI & EARTH TONES */
            :root {
                --khaki-light: #f7f5f3;
                --khaki-medium: #d4c4b0;
                --khaki-dark: #b8a082;
                --khaki-deep: #8b7355;
                --cream: #faf8f5;
                --beige: #e8ddd4;
                --sage: #9caf88;
                --terracotta: #c17b5c;
                --charcoal: #2c2c2c;
                --text-primary: #2c2c2c;
                --text-secondary: #6b6b6b;
                --text-muted: #9a9a9a;
                --bg-primary: #faf8f5;
                --bg-secondary: #f7f5f3;
                --bg-accent: rgba(212, 196, 176, 0.1);
                --border-light: rgba(184, 160, 130, 0.2);
                --shadow-subtle: 0 2px 20px rgba(139, 115, 85, 0.08);
                --gradient-warm: linear-gradient(135deg, #f7f5f3 0%, #e8ddd4 50%, #d4c4b0 100%);
                --gradient-accent: linear-gradient(135deg, var(--sage) 0%, var(--terracotta) 100%);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: var(--gradient-warm);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-container {
                background: var(--bg-primary);
                padding: 50px 40px;
                border-radius: 12px;
                box-shadow: var(--shadow-subtle);
                border: 1px solid var(--border-light);
                width: 100%;
                max-width: 420px;
                text-align: center;
                backdrop-filter: blur(10px);
                animation: slideUp 0.6s ease-out;
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 40px;
            }
            
            .login-header h1 {
                color: var(--text-primary);
                font-size: 32px;
                font-weight: 300;
                letter-spacing: 1px;
                margin-bottom: 12px;
                font-family: 'Inter', sans-serif;
            }
            
            .login-header p {
                color: var(--text-secondary);
                font-size: 15px;
                font-weight: 400;
                letter-spacing: 0.3px;
            }
            
            .form-group {
                margin-bottom: 25px;
                text-align: left;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 10px;
                color: var(--text-primary);
                font-weight: 500;
                font-size: 14px;
                letter-spacing: 0.3px;
            }
            
            .form-group input {
                width: 100%;
                padding: 15px 18px;
                border: 1px solid var(--border-light);
                border-radius: 8px;
                font-size: 15px;
                font-family: inherit;
                transition: all 0.3s ease;
                background: var(--bg-secondary);
                color: var(--text-primary);
                letter-spacing: 0.3px;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: var(--sage);
                background: white;
                box-shadow: 0 0 0 3px var(--bg-accent);
                transform: translateY(-1px);
            }
            
            .form-group input::placeholder {
                color: var(--text-muted);
            }
            
            .btn-login {
                width: 100%;
                padding: 16px 24px;
                background: var(--gradient-accent);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-bottom: 25px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                font-family: inherit;
            }
            
            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(156, 175, 136, 0.3);
            }
            
            .btn-login:active {
                transform: translateY(0);
            }
            
            .error {
                background: rgba(193, 123, 92, 0.1);
                color: var(--terracotta);
                padding: 15px 18px;
                border-radius: 8px;
                margin-bottom: 25px;
                font-size: 14px;
                border: 1px solid rgba(193, 123, 92, 0.2);
                text-align: left;
            }
            
            .success {
                background: rgba(156, 175, 136, 0.1);
                color: var(--sage);
                padding: 15px 18px;
                border-radius: 8px;
                margin-bottom: 25px;
                font-size: 14px;
                border: 1px solid rgba(156, 175, 136, 0.2);
                text-align: left;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media (max-width: 480px) {
                .login-container {
                    padding: 40px 30px;
                    margin: 10px;
                }
                
                .login-header h1 {
                    font-size: 28px;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1><i class="fas fa-shield-alt"></i> Admin Login</h1>
                <p>EyeLux Product Management</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="admin_login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle AJAX requests for product management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance();
        
        switch (trim($_POST['action'])) {
            case 'add_product':
                $product_name = $_POST['product_name'];
                $description = $_POST['description'];
                $price = floatval($_POST['price']);
                $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
                $category_id = intval($_POST['category_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $image_url = $_POST['image_url'];
                $brand = $_POST['brand'];
                $color = $_POST['color'];
                $gender = $_POST['gender'];
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
                
                // Generate SKU
                $sku = strtoupper(substr($brand, 0, 3)) . rand(1000, 9999);
                
                // Debug: Log the data being inserted
                error_log("Adding product: Name=$product_name, Category=$category_id, Brand=$brand, SKU=$sku");
                
                $result = $db->insert(
                    "INSERT INTO products (product_name, description, price, sale_price, category_id, stock_quantity, image_url, additional_images, brand, color, gender, is_featured, is_new_arrival, is_active, sku, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                    [$product_name, $description, $price, $sale_price, $category_id, $stock_quantity, $image_url, $additional_images, $brand, $color, $gender, $is_featured, $is_new_arrival, $sku]
                );
                
                if ($result) {
                    // Get the category name for confirmation
                    $category_info = $db->fetchOne("SELECT category_name FROM categories WHERE category_id = ?", [$category_id]);
                    $category_name = $category_info ? $category_info['category_name'] : 'Unknown';
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Product added successfully to {$category_name} category!",
                        'product_id' => $result,
                        'category_name' => $category_name
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add product to database']);
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                // Delete from related tables first
                $db->execute("DELETE FROM cart WHERE product_id = ?", [$product_id]);
                $db->execute("DELETE FROM wishlist WHERE product_id = ?", [$product_id]);
                $db->execute("DELETE FROM order_items WHERE product_id = ?", [$product_id]);
                $db->execute("DELETE FROM reviews WHERE product_id = ?", [$product_id]);
                
                // Delete the product
                $db->execute("DELETE FROM products WHERE product_id = ?", [$product_id]);
                
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
                break;
                
            case 'update_stock':
                $product_id = intval($_POST['product_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                
                $db->execute("UPDATE products SET stock_quantity = ? WHERE product_id = ?", [$stock_quantity, $product_id]);
                
                echo json_encode(['success' => true, 'message' => 'Stock updated successfully!']);
                break;
                
            case 'update_product':
                $product_id = intval($_POST['product_id']);
                $product_name = $_POST['product_name'];
                $description = $_POST['description'];
                $price = floatval($_POST['price']);
                $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
                $category_id = intval($_POST['category_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $image_url = $_POST['image_url'];
                $brand = $_POST['brand'];
                $color = $_POST['color'];
                $gender = $_POST['gender'];
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
                
                // Debug logging
                error_log("Update Product - ID: $product_id, Price: $price, Sale Price: " . ($sale_price ?? 'NULL'));
                
                $db->execute(
                    "UPDATE products SET product_name = ?, description = ?, price = ?, sale_price = ?, category_id = ?, stock_quantity = ?, image_url = ?, additional_images = ?, brand = ?, color = ?, gender = ?, is_featured = ?, is_new_arrival = ?, updated_at = NOW() WHERE product_id = ?",
                    [$product_name, $description, $price, $sale_price, $category_id, $stock_quantity, $image_url, $additional_images, $brand, $color, $gender, $is_featured, $is_new_arrival, $product_id]
                );
                
                echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
                break;
                
            case 'get_order_details':
                $order_id = intval($_POST['order_id']);
                
                // Get order details
                $order = $db->fetchOne(
                    "SELECT o.*, u.first_name, u.last_name, u.email, u.phone,
                            sa.first_name as ship_first, sa.last_name as ship_last, sa.address_line1 as ship_address, 
                            sa.city as ship_city, sa.state as ship_state, sa.postal_code as ship_postal, sa.country as ship_country,
                            ba.first_name as bill_first, ba.last_name as bill_last, ba.address_line1 as bill_address,
                            ba.city as bill_city, ba.state as bill_state, ba.postal_code as bill_postal, ba.country as bill_country
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.user_id
                     LEFT JOIN addresses sa ON o.shipping_address_id = sa.address_id
                     LEFT JOIN addresses ba ON o.billing_address_id = ba.address_id
                     WHERE o.order_id = ?", 
                    [$order_id]
                );
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    break;
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
                    'items' => $order_items
                ]);
                break;
                
            case 'update_order_status':
                $order_id = intval($_POST['order_id']);
                $new_status = sanitizeInput($_POST['status']);
                
                // Validate status
                $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
                if (!in_array($new_status, $valid_statuses)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status']);
                    break;
                }
                
                // Get order details for notification
                $order = $db->fetchOne(
                    "SELECT o.*, u.first_name, u.last_name, u.email FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.user_id 
                     WHERE o.order_id = ?", 
                    [$order_id]
                );
                
                if (!$order) {
                    echo json_encode(['success' => false, 'message' => 'Order not found']);
                    break;
                }
                
                // Update order status
                $db->execute(
                    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?", 
                    [$new_status, $order_id]
                );
                
                // Create notification for user
                $status_messages = [
                    'processing' => 'Your order has been confirmed and is being processed.',
                    'shipped' => 'Your order has been shipped and is on its way!',
                    'delivered' => 'Your order has been delivered successfully.',
                    'cancelled' => 'Your order has been cancelled.'
                ];
                
                $status_titles = [
                    'processing' => 'Order Confirmed',
                    'shipped' => 'Order Shipped',
                    'delivered' => 'Order Delivered',
                    'cancelled' => 'Order Cancelled'
                ];
                
                if (isset($status_messages[$new_status])) {
                    $db->insert(
                        "INSERT INTO notifications (user_id, title, message, type, order_id) VALUES (?, ?, ?, ?, ?)",
                        [
                            $order['user_id'], 
                            $status_titles[$new_status], 
                            $status_messages[$new_status] . " Order #{$order['order_number']}",
                            'order_status',
                            $order_id
                        ]
                    );
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Order status updated successfully!',
                    'new_status' => $new_status
                ]);
                break;
                
            case 'get_product_details':
                error_log("SECOND SWITCH: Processing get_product_details for ID: " . ($_POST['product_id'] ?? 'none'));
                $product_id = intval($_POST['product_id']);
                
                $product = $db->fetchOne(
                    "SELECT p.*, c.category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.category_id 
                     WHERE p.product_id = ?", 
                    [$product_id]
                );
                
                if (!$product) {
                    error_log("SECOND SWITCH: Product not found for ID: $product_id");
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    break;
                }
                
                // Debug logging
                error_log("SECOND SWITCH: Product details for ID $product_id: " . json_encode($product));
                
                echo json_encode(['success' => true, 'product' => $product]);
                break;
                
            case 'get_user_details':
                $user_id = intval($_POST['user_id']);
                $user = getUserDetails($user_id);
                
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    break;
                }
                
                // Get admin actions for this user
                $admin_actions = getUserAdminActions($user_id);
                $user['admin_actions'] = $admin_actions;
                
                echo json_encode(['success' => true, 'user' => $user]);
                break;
                
            case 'warn_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason']);
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Warning reason is required']);
                    break;
                }
                
                $result = sendUserWarning($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Warning sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send warning']);
                }
                break;
                
            case 'ban_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason']);
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Ban reason is required']);
                    break;
                }
                
                $result = banUser($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User banned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to ban user']);
                }
                break;
                
            case 'unban_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason'] ?? 'Account unbanned by administrator');
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                $result = unbanUser($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to unban user']);
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason'] ?? 'User deleted by administrator');
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                // Debug logging
                error_log("Delete user request - User ID: $user_id, Reason: $reason, Admin ID: $admin_user_id");
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Deletion reason is required']);
                    break;
                }
                
                try {
                    $result = deleteUser($user_id, $admin_user_id, $reason);
                    error_log("Delete user result: " . ($result ? 'true' : 'false'));
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete user - function returned false']);
                    }
                } catch (Exception $e) {
                    error_log("Delete user error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
                }
                break;
                
            default:
                // Debug: Log the action that wasn't matched
                $action = $_POST['action'] ?? 'no action';
                error_log("Unmatched action: '" . $action . "' (length: " . strlen($action) . ")");
                error_log("Action bytes: " . bin2hex($action));
                echo json_encode(['success' => false, 'message' => 'Invalid action: "' . $action . '" (length: ' . strlen($action) . ')']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX requests for product management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance();
        
        switch (trim($_POST['action'])) {
            case 'add_product':
                $product_name = $_POST['product_name'];
                $description = $_POST['description'];
                $price = floatval($_POST['price']);
                $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
                $category_id = intval($_POST['category_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $image_url = $_POST['image_url'];
                $brand = $_POST['brand'];
                $color = $_POST['color'];
                $gender = $_POST['gender'];
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
                
                // Generate SKU
                $sku = strtoupper(substr($brand, 0, 3)) . rand(1000, 9999);
                
                // Debug: Log the data being inserted
                error_log("Adding product: Name=$product_name, Category=$category_id, Brand=$brand, SKU=$sku");
                
                $result = $db->insert(
                    "INSERT INTO products (product_name, description, price, sale_price, category_id, stock_quantity, image_url, brand, color, gender, is_featured, is_new_arrival, is_active, sku, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                    [$product_name, $description, $price, $sale_price, $category_id, $stock_quantity, $image_url, $brand, $color, $gender, $is_featured, $is_new_arrival, $sku]
                );
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Product added successfully', 'product_id' => $result]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add product']);
                }
                break;
                
            case 'get_product_details':
                error_log("FIRST SWITCH: Processing get_product_details for ID: " . ($_POST['product_id'] ?? 'none'));
                $product_id = intval($_POST['product_id']);
                
                $product = $db->fetchOne(
                    "SELECT p.*, c.category_name 
                     FROM products p 
                     LEFT JOIN categories c ON p.category_id = c.category_id 
                     WHERE p.product_id = ?", 
                    [$product_id]
                );
                
                if (!$product) {
                    error_log("FIRST SWITCH: Product not found for ID: $product_id");
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    break;
                }
                
                // Debug logging
                error_log("FIRST SWITCH: Product details for ID $product_id: " . json_encode($product));
                
                echo json_encode(['success' => true, 'product' => $product]);
                break;
                
            case 'get_user_details':
                $user_id = intval($_POST['user_id']);
                $user = getUserDetails($user_id);
                
                if (!$user) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    break;
                }
                
                // Get admin actions for this user
                $admin_actions = getUserAdminActions($user_id);
                $user['admin_actions'] = $admin_actions;
                
                echo json_encode(['success' => true, 'user' => $user]);
                break;
                
            case 'warn_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason']);
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Warning reason is required']);
                    break;
                }
                
                $result = sendUserWarning($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Warning sent successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send warning']);
                }
                break;
                
            case 'ban_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason']);
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Ban reason is required']);
                    break;
                }
                
                $result = banUser($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User banned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to ban user']);
                }
                break;
                
            case 'unban_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason'] ?? 'Account unbanned by administrator');
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                $result = unbanUser($user_id, $admin_user_id, $reason);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to unban user']);
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                $reason = sanitizeInput($_POST['reason'] ?? 'User deleted by administrator');
                $admin_user_id = $_SESSION['user_id'] ?? 0;
                
                // Debug logging
                error_log("Delete user request - User ID: $user_id, Reason: $reason, Admin ID: $admin_user_id");
                
                if (empty($reason)) {
                    echo json_encode(['success' => false, 'message' => 'Deletion reason is required']);
                    break;
                }
                
                try {
                    $result = deleteUser($user_id, $admin_user_id, $reason);
                    error_log("Delete user result: " . ($result ? 'true' : 'false'));
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete user - function returned false']);
                    }
                } catch (Exception $e) {
                    error_log("Delete user error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
                }
                break;
                
            default:
                // Debug: Log the action that wasn't matched
                $action = $_POST['action'] ?? 'no action';
                error_log("FIRST SWITCH: Unmatched action: '" . $action . "' (length: " . strlen($action) . ")");
                error_log("FIRST SWITCH: Action bytes: " . bin2hex($action));
                echo json_encode(['success' => false, 'message' => 'Invalid action: "' . $action . '" (length: ' . strlen($action) . ')']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Get all products for display
$db = Database::getInstance();
$products = $db->fetchAll("SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id ORDER BY p.created_at DESC");


// Get categories for dropdown
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Get dashboard statistics
$total_products = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'];
$total_users = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
$pending_orders = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'];
$low_stock_products = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= 5 AND stock_quantity > 0")['count'];
$out_of_stock = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0")['count'];

// Get user concerns and refund requests counts
$pending_concerns = $db->fetchOne("SELECT COUNT(*) as count FROM user_concerns WHERE status = 'new'")['count'];
$pending_refunds = $db->fetchOne("SELECT COUNT(*) as count FROM refund_requests WHERE status = 'pending'")['count'];

// Get recent orders
$recent_orders = $db->fetchAll("SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.order_date DESC LIMIT 5");

// Get recent users
$recent_users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - EyeLux</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* MINIMALIST AESTHETIC THEME - KHAKI & EARTH TONES */
        :root {
            --khaki-light: #f7f5f3;
            --khaki-medium: #d4c4b0;
            --khaki-dark: #b8a082;
            --khaki-deep: #8b7355;
            --cream: #faf8f5;
            --beige: #e8ddd4;
            --sage: #9caf88;
            --terracotta: #c17b5c;
            --charcoal: #2c2c2c;
            --text-primary: #2c2c2c;
            --text-secondary: #6b6b6b;
            --text-muted: #9a9a9a;
            --bg-primary: #faf8f5;
            --bg-secondary: #f7f5f3;
            --bg-accent: rgba(212, 196, 176, 0.1);
            --border-light: rgba(184, 160, 130, 0.2);
            --shadow-subtle: 0 2px 20px rgba(139, 115, 85, 0.08);
            --gradient-warm: linear-gradient(135deg, #f7f5f3 0%, #e8ddd4 50%, #d4c4b0 100%);
            --gradient-accent: linear-gradient(135deg, var(--sage) 0%, var(--terracotta) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            letter-spacing: -0.01em;
        }
        
        .admin-header {
            background: var(--gradient-warm);
            color: var(--text-primary);
            padding: 25px 0;
            box-shadow: var(--shadow-subtle);
            border-bottom: 1px solid var(--border-light);
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-nav h1 {
            font-size: 28px;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        .admin-nav .logout {
            color: var(--text-primary);
            text-decoration: none;
            padding: 12px 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .admin-nav .logout:hover {
            background: var(--sage);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-subtle);
        }
        
        .admin-content {
            padding: 30px 0;
        }
        
        /* Dashboard Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-card.products { color: #667eea; }
        .stat-card.users { color: #28a745; }
        .stat-card.orders { color: #ffc107; }
        .stat-card.stock { color: #dc3545; }
        .stat-card.concerns { color: #17a2b8; }
        .stat-card.refunds { color: #fd7e14; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-subtle);
            overflow-y: auto;
            animation: slideIn 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .modal-header {
            background: var(--gradient-accent);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .admin-tabs {
            display: flex;
            margin-bottom: 30px;
            background: var(--bg-primary);
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }
        
        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: var(--bg-primary);
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 400;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            color: var(--text-secondary);
        }
        
        .tab-button.active {
            background: var(--sage);
            color: white;
            border-bottom-color: var(--sage);
        }
        
        .tab-content {
            display: none;
            background: var(--bg-primary);
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-grid .form-group {
            position: relative;
        }
        
        .form-grid .form-group::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border-radius: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .form-grid .form-group:hover::before {
            opacity: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            position: relative;
        }
        
        .form-group label::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        .form-group input:focus + label::after,
        .form-group select:focus + label::after,
        .form-group textarea:focus + label::after {
            width: 100%;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }
        
        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #667eea;
            background: white;
        }
        
        /* Special styling for category dropdown */
        select[name="category_id"] {
            background: #fff3cd !important;
            border-color: #ffc107 !important;
            font-weight: 600;
        }
        
        select[name="category_id"]:focus {
            background: white !important;
            border-color: #667eea !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15) !important;
        }
        
        select[name="category_id"] option {
            font-weight: 500;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border-radius: 12px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .checkbox-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        .checkbox-item label {
            margin: 0;
            font-weight: 600;
            color: #333;
            cursor: pointer;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: var(--sage);
            color: white;
            box-shadow: var(--shadow-subtle);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(156, 175, 136, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .products-table th,
        .products-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .products-table tr:hover {
            background: #f8f9fa;
        }
        
        .product-image-small {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .stock-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .stock-in {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-out {
            background: #f8d7da;
            color: #721c24;
        }
        
        .new-badge {
            background: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-nav {
                flex-direction: column;
                gap: 15px;
            }
            
            .products-table {
                font-size: 12px;
            }
            
            .products-table th,
            .products-table td {
                padding: 8px;
            }
        }
        
        /* Sale Options Styles */
        .sale-options {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .sale-method-toggle {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .toggle-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #555;
        }
        
        .toggle-label input[type="radio"] {
            margin: 0;
            accent-color: #e74c3c;
        }
        
        .toggle-label:hover {
            color: #e74c3c;
        }
        
        .sale-input-group {
            margin-top: 10px;
        }
        
        .sale-input-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            font-size: 14px;
        }
        
        .calculated-price {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        
        .price-calculation {
            text-align: center;
        }
        
        .sale-price {
            font-size: 16px;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        
        .savings {
            font-size: 14px;
            color: #27ae60;
        }
        
        .savings-amount {
            font-weight: 600;
        }
        
        /* Responsive Sale Options */
        @media (max-width: 768px) {
            .sale-method-toggle {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        /* User Management Styles */
        .user-profile {
            max-width: 100%;
        }
        
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .user-avatar {
            font-size: 60px;
            color: #6c757d;
            margin-right: 20px;
        }
        
        .user-info h2 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .user-email {
            color: #6c757d;
            margin: 0 0 10px 0;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .stat-item i {
            font-size: 20px;
            color: #007bff;
            margin-right: 10px;
        }
        
        .user-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row label {
            font-weight: bold;
            color: #495057;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .admin-actions-history {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .admin-actions-history h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .actions-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .action-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .action-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .action-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .action-ban {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-unban {
            background: #d4edda;
            color: #155724;
        }
        
        .action-date {
            color: #6c757d;
            font-size: 12px;
        }
        
        .action-admin {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .action-reason {
            color: #495057;
            font-style: italic;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
            border: none;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="admin-container">
            <div class="admin-nav">
                <h1><i class="fas fa-glasses"></i> EyeLux Admin Panel</h1>
                <a href="?logout=1" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="admin-content">
            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card products">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-number"><?php echo $total_products; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card users">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card orders">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-number"><?php echo $pending_orders; ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card stock">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-number"><?php echo $low_stock_products + $out_of_stock; ?></div>
                    <div class="stat-label">Stock Issues</div>
                </div>
                <div class="stat-card concerns">
                    <div class="stat-icon"><i class="fas fa-comments"></i></div>
                    <div class="stat-number"><?php echo $pending_concerns; ?></div>
                    <div class="stat-label">User Concerns</div>
                </div>
                <div class="stat-card refunds">
                    <div class="stat-icon"><i class="fas fa-undo"></i></div>
                    <div class="stat-number"><?php echo $pending_refunds; ?></div>
                    <div class="stat-label">Refund Requests</div>
                </div>
            </div>
            
            <div class="admin-tabs">
                <button class="tab-button active" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('dashboard');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </button>
                <button class="tab-button" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('add-product');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-plus"></i> Add Product
                </button>
                <button class="tab-button active" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('manage-products');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-list"></i> Manage Products
                </button>
                <button class="tab-button" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('orders');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-shopping-cart"></i> Orders
                </button>
                <button class="tab-button" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('refunds');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-undo"></i> Refunds
                </button>
                <button class="tab-button" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('users');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-users"></i> Users
                </button>
                <button class="tab-button" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('user-concerns');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-comments"></i> User Concerns
                </button>
                <button class="tab-button" onclick="
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    const targetTab = document.getElementById('hero-content');
                    if (targetTab) targetTab.classList.add('active');
                    this.classList.add('active');
                ">
                    <i class="fas fa-home"></i> Hero Content
                </button>
            </div>
            
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                    <!-- Recent Orders -->
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 20px; color: #333;"><i class="fas fa-shopping-cart"></i> Recent Orders</h3>
                        <?php if (empty($recent_orders)): ?>
                            <p style="color: #666; text-align: center; padding: 20px;">No recent orders</p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Order #<?php echo $order['order_id']; ?></strong><br>
                                        <small style="color: #666;">
                                            <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                        </small>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="color: #333; font-weight: 600;">₱<?php echo number_format($order['total_amount'], 2); ?></span><br>
                                        <span style="background: #ffc107; color: #333; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Users -->
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 20px; color: #333;"><i class="fas fa-users"></i> Recent Users</h3>
                        <?php if (empty($recent_users)): ?>
                            <p style="color: #666; text-align: center; padding: 20px;">No recent users</p>
                        <?php else: ?>
                            <?php foreach ($recent_users as $user): ?>
                                <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </div>
                                    <div style="text-align: right;">
                                        <small style="color: #666;">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Add Product Tab -->
            <div id="add-product" class="tab-content">
                <h2><i class="fas fa-plus-circle"></i> Add New Product</h2>
                <button class="btn btn-primary" onclick="openAddProductModal()" id="add-product-btn">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
            
            <!-- Orders Tab -->
            <div id="orders" class="tab-content">
                <h2><i class="fas fa-shopping-cart"></i> Order Management</h2>
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <?php if (empty($recent_orders)): ?>
                        <p style="color: #666; text-align: center; padding: 40px;">No orders found</p>
                    <?php else: ?>
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span style="background: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <button class="btn btn-success" style="padding: 5px 10px; font-size: 12px; margin-left: 5px;" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'processing')">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                            <button class="btn btn-danger" style="padding: 5px 10px; font-size: 12px; margin-left: 5px;" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'cancelled')">
                                                <i class="fas fa-times"></i> Deny
                                            </button>
                                        <?php elseif ($order['status'] == 'processing'): ?>
                                            <button class="btn btn-info" style="padding: 5px 10px; font-size: 12px; margin-left: 5px;" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'shipped')">
                                                <i class="fas fa-truck"></i> Ship
                                            </button>
                                        <?php elseif ($order['status'] == 'shipped'): ?>
                                            <button class="btn btn-success" style="padding: 5px 10px; font-size: 12px; margin-left: 5px;" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'delivered')">
                                                <i class="fas fa-check-circle"></i> Deliver
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Refunds Tab -->
            <div id="refunds" class="tab-content">
                <h2><i class="fas fa-undo"></i> Refund Requests</h2>
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div class="refund-filters" style="margin-bottom: 20px;">
                        <button class="btn btn-outline active" onclick="filterRefunds('all')" id="filter-all">All</button>
                        <button class="btn btn-outline" onclick="filterRefunds('pending')" id="filter-pending">Pending</button>
                        <button class="btn btn-outline" onclick="filterRefunds('approved')" id="filter-approved">Approved</button>
                        <button class="btn btn-outline" onclick="filterRefunds('declined')" id="filter-declined">Declined</button>
                    </div>
                    
                    <div id="refunds-list">
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #666;"></i>
                            <p style="color: #666; margin-top: 10px;">Loading refund requests...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Tab -->
            <div id="users" class="tab-content">
                <h2><i class="fas fa-users"></i> User Management</h2>
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <?php if (empty($recent_users)): ?>
                        <p style="color: #666; text-align: center; padding: 40px;">No users found</p>
                    <?php else: ?>
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Join Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-warning" style="padding: 5px 10px; font-size: 12px; margin-left: 5px;" onclick="warnUser(<?php echo $user['user_id']; ?>)">
                                            <i class="fas fa-exclamation-triangle"></i> Warn
                                        </button>
                                        <button class="btn btn-danger" style="padding: 5px 10px; font-size: 12px; margin-left: 5px;" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- User Concerns Tab -->
            <div id="user-concerns" class="tab-content">
                <h2><i class="fas fa-comments"></i> User Concerns</h2>
                <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div class="concerns-filters" style="margin-bottom: 20px;">
                        <button class="btn btn-outline active" onclick="filterConcerns('all')" id="filter-concerns-all">All</button>
                        <button class="btn btn-outline" onclick="filterConcerns('unread')" id="filter-concerns-unread">Unread</button>
                        <button class="btn btn-outline" onclick="filterConcerns('read')" id="filter-concerns-read">Read</button>
                        <button class="btn btn-outline" onclick="filterConcerns('replied')" id="filter-concerns-replied">Replied</button>
                    </div>
                    
                    <div id="concerns-list">
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #666;"></i>
                            <p style="color: #666; margin-top: 10px;">Loading user concerns...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hero Content Tab -->
            <div id="hero-content" class="tab-content">
                <h2><i class="fas fa-home"></i> Hero Content Management</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                    <!-- Hero Text Editor -->
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 20px; color: #333;"><i class="fas fa-edit"></i> Edit Hero Text</h3>
                        <form id="hero-content-form">
                            <div class="form-group">
                                <label for="hero-title">Title:</label>
                                <input type="text" id="hero-title" name="title" value="<?php echo htmlspecialchars($hero_content['title'] ?? 'Discover Your Perfect Eyewear'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="hero-subtitle">Subtitle:</label>
                                <textarea id="hero-subtitle" name="subtitle" rows="3" required><?php echo htmlspecialchars($hero_content['subtitle'] ?? 'From classic aviators to modern frames, find the perfect pair that matches your style and personality.'); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="hero-button-text">Button Text:</label>
                                <input type="text" id="hero-button-text" name="button_text" value="<?php echo htmlspecialchars($hero_content['button_text'] ?? 'Shop Now'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="hero-button-link">Button Link:</label>
                                <input type="text" id="hero-button-link" name="button_link" value="<?php echo htmlspecialchars($hero_content['button_link'] ?? 'products.php'); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Hero Content
                            </button>
                        </form>
                    </div>
                    
                    <!-- Carousel Management -->
                    <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <h3 style="margin-bottom: 20px; color: #333;"><i class="fas fa-images"></i> Carousel Images Management</h3>
                        <button onclick="openAddCarouselModal()" class="btn btn-success" style="margin-bottom: 20px;">
                            <i class="fas fa-plus"></i> Add Carousel Image
                        </button>
                        
                        <div id="carousel-items">
                            <?php
                            $carousel_items = getHeroCarousel();
                            foreach ($carousel_items as $item):
                            ?>
                            <div class="carousel-item-card" style="border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px;">
                                <div class="carousel-preview">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px;">
                                </div>
                                <div class="carousel-info" style="flex: 1;">
                                    <h4 style="margin: 0 0 5px 0; font-size: 16px;"><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p style="margin: 0 0 5px 0; font-size: 14px; color: #666;"><?php echo htmlspecialchars($item['subtitle']); ?></p>
                                    <small style="color: #999;">Order: <?php echo $item['display_order']; ?></small>
                                </div>
                                <div class="carousel-actions" style="display: flex; gap: 5px;">
                                    <button onclick="editCarouselItem(<?php echo $item['id']; ?>)" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCarouselItem(<?php echo $item['id']; ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manage Products Tab -->
            <div id="manage-products" class="tab-content">
                <h2><i class="fas fa-list"></i> Manage Products</h2>
                <div id="products-list">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr id="product-<?php echo $product['product_id']; ?>">
                                <td>
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             class="product-image-small"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div style="display: none; width: 60px; height: 60px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #ccc;"></i>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #ccc;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                    <?php if ($product['is_new_arrival']): ?>
                                        <span class="new-badge">NEW</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>
                                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                        <span style="color: #e74c3c; font-weight: 600;">₱<?php echo number_format($product['sale_price'], 2); ?></span>
                                        <br><small style="text-decoration: line-through; color: #999;">₱<?php echo number_format($product['price'], 2); ?></small>
                                    <?php else: ?>
                                        <span style="color: #333; font-weight: 600;">₱<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="stock-quantity" data-product-id="<?php echo $product['product_id']; ?>"><?php echo $product['stock_quantity']; ?></span>
                                    <?php if ($product['stock_quantity'] <= 5): ?>
                                        <br><small style="color: #dc3545;">Low Stock</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary" onclick="editProduct(<?php echo $product['product_id']; ?>)" style="margin-right: 5px; padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteProduct(<?php echo $product['product_id']; ?>)" style="padding: 5px 10px; font-size: 12px;">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Product</h3>
                <span class="close" onclick="closeModal('addProductModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="add-product-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="product_name">Product Name *</label>
                            <input type="text" id="product_name" name="product_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="brand">Brand *</label>
                            <input type="text" id="brand" name="brand" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (₱) *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required onchange="calculateSalePrice('add')">
                        </div>
                        
                        <div class="form-group">
                            <label for="sale_price">Sale Price</label>
                            <div class="sale-options">
                                <div class="sale-method-toggle">
                                    <label class="toggle-label">
                                        <input type="radio" name="sale_method" value="percentage" checked onchange="toggleSaleMethod('add')">
                                        <span>Percentage</span>
                                    </label>
                                    <label class="toggle-label">
                                        <input type="radio" name="sale_method" value="amount" onchange="toggleSaleMethod('add')">
                                        <span>Fixed Amount</span>
                                    </label>
                                </div>
                                
                                <div id="sale-percentage-add" class="sale-input-group">
                                    <label for="sale_percentage">Sale Percentage</label>
                                    <select id="sale_percentage" name="sale_percentage" onchange="calculateSalePrice('add')">
                                        <option value="">No Sale</option>
                                        <option value="5">5% Off</option>
                                        <option value="10">10% Off</option>
                                        <option value="15">15% Off</option>
                                        <option value="20">20% Off</option>
                                        <option value="25">25% Off</option>
                                        <option value="30">30% Off</option>
                                        <option value="35">35% Off</option>
                                        <option value="40">40% Off</option>
                                        <option value="50">50% Off</option>
                                    </select>
                                    <div id="calculated-sale-price-add" class="calculated-price"></div>
                                </div>
                                
                                <div id="sale-amount-add" class="sale-input-group" style="display: none;">
                                    <label for="sale_price">Sale Price (₱)</label>
                                    <input type="number" id="sale_price" name="sale_price" step="0.01" min="0" placeholder="Enter sale price">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">🔍 Select Category (Required)</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock_quantity">Stock Quantity *</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="color">Color</label>
                            <input type="text" id="color" name="color">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="men">Men</option>
                                <option value="women">Women</option>
                                <option value="unisex">Unisex</option>
                                <option value="kids">Kids</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="image_url">Image URL</label>
                            <input type="url" id="image_url" name="image_url" placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Product description..."></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="is_featured" name="is_featured">
                            <label for="is_featured">Featured Product</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="is_new_arrival" name="is_new_arrival" checked>
                            <label for="is_new_arrival">New Arrival</label>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')" style="margin-right: 10px;">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="addProduct()" id="add-product-btn">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Product</h3>
                <span class="close" onclick="closeModal('editProductModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="edit-product-form">
                    <input type="hidden" id="edit_product_id" name="product_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_product_name">Product Name *</label>
                            <input type="text" id="edit_product_name" name="product_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_brand">Brand *</label>
                            <input type="text" id="edit_brand" name="brand" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_price">Price (₱) *</label>
                            <input type="number" id="edit_price" name="price" step="0.01" min="0" required onchange="calculateSalePrice('edit')">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_sale_price">Sale Price</label>
                            <div class="sale-options">
                                <div class="sale-method-toggle">
                                    <label class="toggle-label">
                                        <input type="radio" id="edit_sale_method_percentage" name="edit_sale_method" value="percentage" checked onchange="toggleSaleMethod('edit')">
                                        <span>Percentage</span>
                                    </label>
                                    <label class="toggle-label">
                                        <input type="radio" id="edit_sale_method_amount" name="edit_sale_method" value="amount" onchange="toggleSaleMethod('edit')">
                                        <span>Fixed Amount</span>
                                    </label>
                                </div>
                                
                                <div id="sale-percentage-edit" class="sale-input-group">
                                    <label for="edit_sale_percentage">Sale Percentage</label>
                                    <select id="edit_sale_percentage" name="edit_sale_percentage" onchange="calculateSalePrice('edit')">
                                        <option value="">No Sale</option>
                                        <option value="5">5% Off</option>
                                        <option value="10">10% Off</option>
                                        <option value="15">15% Off</option>
                                        <option value="20">20% Off</option>
                                        <option value="25">25% Off</option>
                                        <option value="30">30% Off</option>
                                        <option value="35">35% Off</option>
                                        <option value="40">40% Off</option>
                                        <option value="50">50% Off</option>
                                    </select>
                                    <div id="calculated-sale-price-edit" class="calculated-price"></div>
                                </div>
                                
                                <div id="sale-amount-edit" class="sale-input-group" style="display: none;">
                                    <label for="edit_sale_price">Sale Price (₱)</label>
                                    <input type="number" id="edit_sale_price" name="sale_price" step="0.01" min="0" placeholder="Enter sale price">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_category_id">Category *</label>
                            <select id="edit_category_id" name="category_id" required>
                                <option value="">🔍 Select Category (Required)</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_stock_quantity">Stock Quantity *</label>
                            <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_color">Color</label>
                            <input type="text" id="edit_color" name="color">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_gender">Gender</label>
                            <select id="edit_gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="men">Men</option>
                                <option value="women">Women</option>
                                <option value="unisex">Unisex</option>
                                <option value="kids">Kids</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_image_url">Image URL</label>
                            <input type="url" id="edit_image_url" name="image_url" placeholder="https://example.com/image.jpg">
                        </div>

                        <div class="form-group">
                            <label>Additional Images (by position)</label>
                            <div style="display:grid; gap:8px;">
                                <div>
                                    <label for="edit_image_1" style="display:block; font-size:12px; color:#6c757d;">Image 1 (Front view)</label>
                                    <input type="url" id="edit_image_1" placeholder="https://.../front.jpg">
                                </div>
                                <div>
                                    <label for="edit_image_2" style="display:block; font-size:12px; color:#6c757d;">Image 2 (Side view)</label>
                                    <input type="url" id="edit_image_2" placeholder="https://.../side.jpg">
                                </div>
                                <div>
                                    <label for="edit_image_3" style="display:block; font-size:12px; color:#6c757d;">Image 3 (Back view)</label>
                                    <input type="url" id="edit_image_3" placeholder="https://.../back.jpg">
                                </div>
                            </div>
                            <input type="hidden" id="edit_additional_images" name="additional_images">
                            <small class="hint">Leave any field empty if you don't have that view.</small>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description" placeholder="Product description..."></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="edit_is_featured" name="is_featured">
                            <label for="edit_is_featured">Featured Product</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="edit_is_new_arrival" name="is_new_arrival">
                            <label for="edit_is_new_arrival">New Arrival</label>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')" style="margin-right: 10px;">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="update-product-btn" onclick="updateProduct()">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-shopping-cart"></i> Order Details</h3>
                <span class="close" onclick="closeModal('orderDetailsModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent">
                    <!-- Order details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Details Modal -->
    <div id="userDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> User Details</h3>
                <span class="close" onclick="closeModal('userDetailsModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="userDetailsContent">
                    <!-- User details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Define showTab function immediately - before any HTML elements try to use it
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            const targetTab = document.getElementById(tabName);
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Add active class to clicked button
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
        
        // Also make it available on window object
        window.showTab = showTab;
        
        // Modal functions - make them global
        window.openAddProductModal = function() {
            document.getElementById('addProductModal').style.display = 'block';
        }
        
        window.closeModal = function(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editProduct(productId) {
            // Show loading state
            const editModal = document.getElementById('editProductModal');
            editModal.style.display = 'block';
            
            // Show loading in modal
            const modalBody = editModal.querySelector('.modal-body');
            const originalContent = modalBody.innerHTML;
            modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #e74c3c;"></i><br><br>Loading product details...</div>';
            
            // Fetch complete product data
            const formData = new FormData();
            formData.append('action', 'get_product_details');
            formData.append('product_id', productId);
            
            
            fetch('admin.php?t=' + Date.now(), {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.product;
                    
                    
                    // Restore modal content
                    modalBody.innerHTML = originalContent;
                    
                    // Populate all form fields
                    document.getElementById('edit_product_id').value = product.product_id;
                    document.getElementById('edit_product_name').value = product.product_name || '';
                    document.getElementById('edit_brand').value = product.brand || '';
                    document.getElementById('edit_price').value = product.price || '';
                    document.getElementById('edit_stock_quantity').value = product.stock_quantity || '';
                    document.getElementById('edit_description').value = product.description || '';
                    document.getElementById('edit_image_url').value = product.image_url || '';
                    // additional images json -> comma list
                    try {
                        const imgs = product.additional_images ? JSON.parse(product.additional_images) : [];
                        document.getElementById('edit_image_1').value = imgs[0] || '';
                        document.getElementById('edit_image_2').value = imgs[1] || '';
                        document.getElementById('edit_image_3').value = imgs[2] || '';
                        document.getElementById('edit_additional_images').value = JSON.stringify(imgs);
                    } catch (e) {
                        document.getElementById('edit_image_1').value = '';
                        document.getElementById('edit_image_2').value = '';
                        document.getElementById('edit_image_3').value = '';
                        document.getElementById('edit_additional_images').value = '';
                    }
                    document.getElementById('edit_color').value = product.color || '';
                    
                    // Set gender field
                    const genderField = document.getElementById('edit_gender');
                    if (genderField) {
                        genderField.value = product.gender || '';
                    }
                    
                    // Set category
                    const categorySelect = document.getElementById('edit_category_id');
                    for (let option of categorySelect.options) {
                        if (option.value == product.category_id) {
                            option.selected = true;
                            break;
                        }
                    }
                    
                    // Handle checkboxes
                    document.getElementById('edit_is_featured').checked = product.is_featured == 1;
                    document.getElementById('edit_is_new_arrival').checked = product.is_new_arrival == 1;
                    
                    // Handle sale price and percentage
                    if (product.sale_price && product.sale_price > 0) {
                        // Calculate percentage from sale price
                        const percentage = Math.round(((product.price - product.sale_price) / product.price) * 100);
                        
                        // Check if it's a standard percentage
                        const standardPercentages = [5, 10, 15, 20, 25, 30, 35, 40, 50];
                        if (standardPercentages.includes(percentage)) {
                            // Use percentage method
                            document.querySelector('input[name="edit_sale_method"][value="percentage"]').checked = true;
                            document.getElementById('edit_sale_percentage').value = percentage;
                            toggleSaleMethod('edit');
                        } else {
                            // Use fixed amount method
                            document.querySelector('input[name="edit_sale_method"][value="amount"]').checked = true;
                            document.getElementById('edit_sale_price').value = product.sale_price;
                            toggleSaleMethod('edit');
                        }
                    } else {
                        // No sale
                        document.querySelector('input[name="edit_sale_method"][value="percentage"]').checked = true;
                        document.getElementById('edit_sale_percentage').value = '';
                        toggleSaleMethod('edit');
                    }
                    
                    // Calculate sale price display
                    calculateSalePrice('edit');
                    
                } else {
                    alert('Error loading product: ' + data.message);
                    editModal.style.display = 'none';
                }
            })
            .catch(error => {
                // console.error('Error:', error);
                alert('Error loading product details');
                editModal.style.display = 'none';
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Sale percentage calculation functions
        function toggleSaleMethod(formType) {
            const percentageDiv = document.getElementById('sale-percentage-' + formType);
            const amountDiv = document.getElementById('sale-amount-' + formType);
            const percentageRadio = document.querySelector(`input[name="${formType === 'add' ? 'sale_method' : 'edit_sale_method'}"][value="percentage"]`);
            
            if (percentageRadio.checked) {
                percentageDiv.style.display = 'block';
                amountDiv.style.display = 'none';
                calculateSalePrice(formType);
            } else {
                percentageDiv.style.display = 'none';
                amountDiv.style.display = 'block';
                document.getElementById('calculated-sale-price-' + formType).innerHTML = '';
            }
        }
        
        function calculateSalePrice(formType) {
            const priceInput = document.getElementById(formType === 'add' ? 'price' : 'edit_price');
            const percentageSelect = document.getElementById(formType === 'add' ? 'sale_percentage' : 'edit_sale_percentage');
            const calculatedDiv = document.getElementById('calculated-sale-price-' + formType);
            
            const price = parseFloat(priceInput.value) || 0;
            const percentage = parseFloat(percentageSelect.value) || 0;
            
            if (price > 0 && percentage > 0) {
                const salePrice = price * (1 - percentage / 100);
                const savings = price - salePrice;
                
                calculatedDiv.innerHTML = `
                    <div class="price-calculation">
                        <div class="sale-price">Sale Price: <strong>₱${salePrice.toFixed(2)}</strong></div>
                        <div class="savings">You save: <span class="savings-amount">₱${savings.toFixed(2)}</span></div>
                    </div>
                `;
            } else {
                calculatedDiv.innerHTML = '';
            }
        }
        
        // Simple Add Product Function
        function addProduct() {
            // Get form data
            const productName = document.getElementById('product_name').value;
            const brand = document.getElementById('brand').value;
            const price = document.getElementById('price').value;
            const categoryId = document.getElementById('category_id').value;
            const stockQuantity = document.getElementById('stock_quantity').value;
            
            // Simple validation
            if (!productName || !brand || !price || !categoryId || !stockQuantity) {
                alert('Please fill in all required fields!');
                return;
            }
            
            // Show loading
            const btn = document.getElementById('add-product-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            btn.disabled = true;
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'add_product');
            formData.append('product_name', productName);
            formData.append('brand', brand);
            formData.append('price', price);
            formData.append('category_id', categoryId);
            formData.append('stock_quantity', stockQuantity);
            formData.append('description', document.getElementById('description').value || '');
            
            // Calculate sale price based on method
            let salePrice = '';
            const saleMethod = document.querySelector('input[name="sale_method"]:checked').value;
            if (saleMethod === 'percentage') {
                const percentage = document.getElementById('sale_percentage').value;
                if (percentage) {
                    salePrice = (price * (1 - percentage / 100)).toFixed(2);
                }
            } else {
                salePrice = document.getElementById('sale_price').value || '';
            }
            formData.append('sale_price', salePrice);
            
            formData.append('image_url', document.getElementById('image_url').value || '');
            formData.append('color', document.getElementById('color').value || '');
            formData.append('gender', document.getElementById('gender').value || '');
            formData.append('is_featured', document.getElementById('is_featured').checked ? '1' : '0');
            formData.append('is_new_arrival', document.getElementById('is_new_arrival').checked ? '1' : '0');
            
            // Submit
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Product added successfully!');
                    // Reset form
                    document.getElementById('add-product-form').reset();
                    // Close modal
                    closeModal('addProductModal');
                    // Reload page to show new product
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            })
            .finally(() => {
                // Reset button
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Global update product function
        function updateProduct() {
            const editForm = document.getElementById('edit-product-form');
            const updateBtn = document.getElementById('update-product-btn');
            
            if (!editForm || !updateBtn) {
                return;
            }
            
            // Get submit button and add loading state
            const submitBtn = updateBtn;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Product...';
            submitBtn.disabled = true;
            
            const formData = new FormData(editForm);
            formData.append('action', 'update_product');
            
            // Calculate sale price based on method (same as add form)
            const saleMethod = document.querySelector('input[name="edit_sale_method"]:checked').value;
            const price = document.getElementById('edit_price').value;
            
            
            if (saleMethod === 'percentage') {
                const percentage = document.getElementById('edit_sale_percentage').value;
                if (percentage) {
                    const calculatedSalePrice = (price * (1 - percentage / 100)).toFixed(2);
                    formData.set('sale_price', calculatedSalePrice);
                } else {
                    formData.set('sale_price', '');
                }
            } else {
                const salePrice = document.getElementById('edit_sale_price').value;
                formData.set('sale_price', salePrice || '');
            }
            
            
            // Add a small delay to ensure calculation is complete
            setTimeout(() => {
                // Build additional_images array by position
                const arr = [];
                const i1 = (document.getElementById('edit_image_1').value || '').trim();
                const i2 = (document.getElementById('edit_image_2').value || '').trim();
                const i3 = (document.getElementById('edit_image_3').value || '').trim();
                if (i1) arr.push(i1);
                if (i2) arr.push(i2);
                if (i3) arr.push(i3);
                const jsonArr = arr.length ? JSON.stringify(arr) : '';
                formData.set('additional_images', jsonArr);
                const hidden = document.getElementById('edit_additional_images');
                if (hidden) hidden.value = jsonArr;


                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Product updated successfully!', 'success');
                        closeModal('editProductModal');
                        // Refresh the page to show updated data
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    // console.error('Update error:', error);
                    showNotification('Error: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            }, 100); // Small delay to ensure calculation is complete
        }
        
        // Edit Product Form
        document.addEventListener('DOMContentLoaded', function() {
            
            const editForm = document.getElementById('edit-product-form');
            const updateBtn = document.getElementById('update-product-btn');
            
            
            if (editForm && updateBtn) {
                // console.log('Attaching click event to update button...');
                
                updateBtn.addEventListener('click', function(e) {
                    // console.log('Update button clicked!');
                    e.preventDefault();
                    
                    // Get submit button and add loading state
                    const submitBtn = this;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Product...';
                    submitBtn.disabled = true;
                    
                    const formData = new FormData(editForm);
                    formData.append('action', 'update_product');
                    
                    // Calculate sale price based on method (same as add form)
                    const saleMethod = document.querySelector('input[name="edit_sale_method"]:checked').value;
                    const price = document.getElementById('edit_price').value;
                    
                    // console.log('Sale method:', saleMethod);
                    // console.log('Price:', price);
                    
                    if (saleMethod === 'percentage') {
                        const percentage = document.getElementById('edit_sale_percentage').value;
                        // console.log('Percentage:', percentage);
                        if (percentage) {
                            const calculatedSalePrice = (price * (1 - percentage / 100)).toFixed(2);
                            // console.log('Calculated sale price:', calculatedSalePrice);
                            formData.set('sale_price', calculatedSalePrice);
                        } else {
                            // console.log('No percentage selected, clearing sale price');
                            formData.set('sale_price', '');
                        }
                    } else {
                        const salePrice = document.getElementById('edit_sale_price').value;
                        // console.log('Fixed sale price:', salePrice);
                        formData.set('sale_price', salePrice || '');
                    }
                    
                    
                    // Add a small delay to ensure calculation is complete
                    setTimeout(() => {
                        // Build additional_images array by position
                        const arr = [];
                        const i1 = (document.getElementById('edit_image_1').value || '').trim();
                        const i2 = (document.getElementById('edit_image_2').value || '').trim();
                        const i3 = (document.getElementById('edit_image_3').value || '').trim();
                        if (i1) arr.push(i1);
                        if (i2) arr.push(i2);
                        if (i3) arr.push(i3);
                        const jsonArr = arr.length ? JSON.stringify(arr) : '';
                        formData.set('additional_images', jsonArr);
                        const hidden = document.getElementById('edit_additional_images');
                        if (hidden) hidden.value = jsonArr;

                        
                        

                        fetch('admin.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Product updated successfully!', 'success');
                                closeModal('editProductModal');
                                // Refresh the page to show updated data
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                showNotification('Error: ' + data.message, 'error');
                            }
                        })
                        .catch(error => {
                            // console.error('Update error:', error);
                            showNotification('Error: ' + error.message, 'error');
                        })
                        .finally(() => {
                            // Reset button state
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        });
                    }, 100); // Small delay to ensure calculation is complete
                });
                
                // console.log('Update button event listener attached successfully');
            } else {
                // console.error('Edit form or update button not found!');
                // console.log('Edit form:', editForm);
                // console.log('Update button:', updateBtn);
            }
        });
        
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add notification styles if not already added
            if (!document.getElementById('notification-styles')) {
                const styles = document.createElement('style');
                styles.id = 'notification-styles';
                styles.textContent = `
                    .notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 15px 20px;
                        border-radius: 8px;
                        color: white;
                        font-weight: 600;
                        z-index: 10000;
                        animation: slideInRight 0.3s ease;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    }
                    .notification-success { background: linear-gradient(135deg, #28a745, #20c997); }
                    .notification-error { background: linear-gradient(135deg, #dc3545, #e74c3c); }
                    .notification-info { background: linear-gradient(135deg, #17a2b8, #6f42c1); }
                    .notification-content {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                `;
                document.head.appendChild(styles);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                const formData = new FormData();
                formData.append('action', 'delete_product');
                formData.append('product_id', productId);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('product-' + productId).remove();
                        alert('Product deleted successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }
        
        function updateStock(productId, newStock) {
            const formData = new FormData();
            formData.append('action', 'update_stock');
            formData.append('product_id', productId);
            formData.append('stock_quantity', newStock);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the status badge
                    const row = document.getElementById('product-' + productId);
                    const statusCell = row.cells[5];
                    if (newStock > 0) {
                        statusCell.innerHTML = '<span class="stock-badge stock-in">In Stock</span>';
                    } else {
                        statusCell.innerHTML = '<span class="stock-badge stock-out">Out of Stock</span>';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        
        // Order Management Functions
        function viewOrder(orderId) {
            // Show loading
            document.getElementById('orderDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i><br>Loading order details...</div>';
            document.getElementById('orderDetailsModal').style.display = 'block';
            
            // Fetch order details
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_order_details&order_id=' + orderId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayOrderDetails(data.order, data.items);
                } else {
                    document.getElementById('orderDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i><br>Error: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('orderDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i><br>Error loading order details</div>';
            });
        }
        
        function displayOrderDetails(order, items) {
            const statusColors = {
                'pending': '#ffc107',
                'processing': '#17a2b8',
                'shipped': '#6f42c1',
                'delivered': '#28a745',
                'cancelled': '#dc3545'
            };
            
            const statusLabels = {
                'pending': 'Pending',
                'processing': 'Processing',
                'shipped': 'Shipped',
                'delivered': 'Delivered',
                'cancelled': 'Cancelled'
            };
            
            let html = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <!-- Order Info -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-info-circle"></i> Order Information</h4>
                        <p><strong>Order #:</strong> ${order.order_number}</p>
                        <p><strong>Date:</strong> ${new Date(order.order_date).toLocaleDateString()}</p>
                        <p><strong>Status:</strong> <span style="background: ${statusColors[order.status]}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">${statusLabels[order.status]}</span></p>
                        <p><strong>Payment Method:</strong> ${order.payment_method}</p>
                        <p><strong>Payment Status:</strong> ${order.payment_status}</p>
                        ${order.tracking_number ? `<p><strong>Tracking #:</strong> ${order.tracking_number}</p>` : ''}
                        ${order.notes ? `<p><strong>Notes:</strong> ${order.notes}</p>` : ''}
                    </div>
                    
                    <!-- Customer Info -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-user"></i> Customer Information</h4>
                        <p><strong>Name:</strong> ${order.first_name} ${order.last_name}</p>
                        <p><strong>Email:</strong> ${order.email}</p>
                        ${order.phone ? `<p><strong>Phone:</strong> ${order.phone}</p>` : ''}
                    </div>
                </div>
                
                <!-- Addresses -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <!-- Shipping Address -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-truck"></i> Shipping Address</h4>
                        <p>${order.ship_first} ${order.ship_last}</p>
                        <p>${order.ship_address}</p>
                        <p>${order.ship_city}, ${order.ship_state} ${order.ship_postal}</p>
                        <p>${order.ship_country}</p>
                    </div>
                    
                    <!-- Billing Address -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-credit-card"></i> Billing Address</h4>
                        <p>${order.bill_first} ${order.bill_last}</p>
                        <p>${order.bill_address}</p>
                        <p>${order.bill_city}, ${order.bill_state} ${order.bill_postal}</p>
                        <p>${order.bill_country}</p>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-shopping-cart"></i> Order Items</h4>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #e9ecef;">
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6;">Product</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6;">SKU</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">Qty</th>
                                    <th style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">Unit Price</th>
                                    <th style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            items.forEach(item => {
                html += `
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                ${item.image_url ? `<img src="${item.image_url}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` : '<div style="width: 40px; height: 40px; background: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-image" style="color: #6c757d;"></i></div>'}
                                <span>${item.product_name}</span>
                            </div>
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">${item.product_sku}</td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">${item.quantity}</td>
                        <td style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td style="padding: 10px; text-align: right; border-bottom: 1px solid #dee2e6;">$${parseFloat(item.total_price).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <h4 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-calculator"></i> Order Summary</h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Subtotal:</span>
                        <span>$${(parseFloat(order.total_amount) - parseFloat(order.tax_amount || 0) - parseFloat(order.shipping_amount || 0)).toFixed(2)}</span>
                    </div>
                    ${order.tax_amount > 0 ? `<div style="display: flex; justify-content: space-between; margin-bottom: 10px;"><span>Tax:</span><span>$${parseFloat(order.tax_amount).toFixed(2)}</span></div>` : ''}
                    ${order.shipping_amount > 0 ? `<div style="display: flex; justify-content: space-between; margin-bottom: 10px;"><span>Shipping:</span><span>$${parseFloat(order.shipping_amount).toFixed(2)}</span></div>` : ''}
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 18px; padding-top: 10px; border-top: 2px solid #dee2e6;">
                        <span>Total:</span>
                        <span>$${parseFloat(order.total_amount).toFixed(2)}</span>
                    </div>
                </div>
            `;
            
            document.getElementById('orderDetailsContent').innerHTML = html;
        }
        
        function updateOrderStatus(orderId, newStatus) {
            const statusLabels = {
                'processing': 'confirm',
                'cancelled': 'deny',
                'shipped': 'ship',
                'delivered': 'deliver'
            };
            
            const action = statusLabels[newStatus] || newStatus;
            const confirmMessage = `Are you sure you want to ${action} this order?`;
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading on the button
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_order_status&order_id=${orderId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Reload the page to show updated status
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error updating order status', 'error');
            })
            .finally(() => {
                // Reset button
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        // Hero Content Management Functions
        document.getElementById('hero-content-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_hero_content');
            
            // Debug: Log what we're sending
            // console.log('Sending hero content update request...');
            // console.log('Action:', 'update_hero_content');
            // console.log('Form data:', Object.fromEntries(formData));
            
            fetch('admin.php?t=' + Date.now(), {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // console.log('Response status:', response.status);
                return response.text(); // Get as text first to see raw response
            })
            .then(text => {
                // console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    // console.log('Parsed response:', data);
                    if (data.success) {
                        showNotification('Hero content updated successfully!', 'success');
                    } else {
                        showNotification(data.message || 'Failed to update hero content', 'error');
                    }
                } catch (e) {
                    // console.error('Failed to parse JSON response:', e);
                    showNotification('Invalid response from server', 'error');
                }
            })
            .catch(error => {
                // console.error('Fetch error:', error);
                showNotification('Error updating hero content', 'error');
            });
        });
        
        function openAddCarouselModal() {
            // Create modal dynamically
            const modal = document.createElement('div');
            modal.id = 'addCarouselModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Add Carousel Image</h3>
                        <button onclick="closeModal('addCarouselModal')" class="close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="add-carousel-form">
                            <div class="form-group">
                                <label for="carousel-image-url">Image URL:</label>
                                <input type="url" id="carousel-image-url" name="image_url" required placeholder="https://example.com/image.jpg">
                                <small style="color: #666; font-size: 12px;">Enter the URL of the image you want to add to the carousel</small>
                            </div>
                            <div class="form-group">
                                <label for="carousel-display-order">Display Order:</label>
                                <input type="number" id="carousel-display-order" name="display_order" value="0" min="0">
                                <small style="color: #666; font-size: 12px;">Lower numbers appear first in the carousel</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button onclick="closeModal('addCarouselModal')" class="btn btn-secondary">Cancel</button>
                        <button onclick="addCarouselItem()" class="btn btn-primary">Add Image</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
        }
        
        function addCarouselItem() {
            const form = document.getElementById('add-carousel-form');
            const formData = new FormData(form);
            formData.append('action', 'add_carousel_item');
            
            // Set default values for required fields that are not in the form
            formData.append('title', 'Carousel Image');
            formData.append('subtitle', '');
            formData.append('button_text', '');
            formData.append('button_link', '');
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Carousel image added successfully!', 'success');
                    closeModal('addCarouselModal');
                    location.reload(); // Refresh to show new item
                } else {
                    showNotification(data.message || 'Failed to add carousel image', 'error');
                }
            })
            .catch(error => {
                showNotification('Error adding carousel image', 'error');
            });
        }
        
        function editCarouselItem(id) {
            // For now, just show a simple prompt - can be enhanced with a proper modal
            const newTitle = prompt('Enter new title:');
            if (newTitle) {
                const formData = new FormData();
                formData.append('action', 'update_carousel_item');
                formData.append('id', id);
                formData.append('title', newTitle);
                formData.append('image_url', 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80');
                formData.append('subtitle', 'Updated subtitle');
                formData.append('button_text', 'Shop Now');
                formData.append('button_link', 'products.php');
                formData.append('display_order', '0');
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Carousel item updated successfully!', 'success');
                        location.reload();
                    } else {
                        showNotification(data.message || 'Failed to update carousel item', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error updating carousel item', 'error');
                });
            }
        }
        
        function deleteCarouselItem(id) {
            if (confirm('Are you sure you want to delete this carousel item?')) {
                const formData = new FormData();
                formData.append('action', 'delete_carousel_item');
                formData.append('id', id);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Carousel item deleted successfully!', 'success');
                        location.reload();
                    } else {
                        showNotification(data.message || 'Failed to delete carousel item', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error deleting carousel item', 'error');
                });
            }
        }
        
        // User management functions
        function viewUser(userId) {
            // Show loading
            document.getElementById('userDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i><br>Loading user details...</div>';
            document.getElementById('userDetailsModal').style.display = 'block';
            
            // Fetch user details
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_user_details&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUserDetails(data.user);
                } else {
                    document.getElementById('userDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i><br>' + data.message + '</div>';
                }
            })
            .catch(error => {
                document.getElementById('userDetailsContent').innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i><br>Error loading user details</div>';
            });
        }
        
        function displayUserDetails(user) {
            const content = `
                <div style="padding: 20px;">
                    <h3 style="margin-bottom: 20px; color: #333;">User Details</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <strong>Name:</strong> ${user.first_name} ${user.last_name}<br>
                            <strong>Email:</strong> ${user.email}<br>
                            <strong>Phone:</strong> ${user.phone || 'N/A'}<br>
                            <strong>Email Verified:</strong> ${user.email_verified ? 'Yes' : 'No'}<br>
                        </div>
                        <div>
                            <strong>Join Date:</strong> ${new Date(user.created_at).toLocaleDateString()}<br>
                            <strong>Last Updated:</strong> ${new Date(user.updated_at).toLocaleDateString()}<br>
                            <strong>Status:</strong> ${user.status || 'Active'}<br>
                            <strong>Warning Count:</strong> ${user.warning_count || 0}<br>
                        </div>
                    </div>
                    ${user.admin_actions && user.admin_actions.length > 0 ? `
                        <div style="margin-top: 20px;">
                            <h4>Admin Actions</h4>
                            <div style="max-height: 200px; overflow-y: auto;">
                                ${user.admin_actions.map(action => `
                                    <div style="padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 5px;">
                                        <strong>${action.action_type}</strong> by ${action.first_name} ${action.last_name}<br>
                                        <small>${new Date(action.created_at).toLocaleString()}</small><br>
                                        <em>${action.reason}</em>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('userDetailsContent').innerHTML = content;
        }
        
        function warnUser(userId) {
            const reason = prompt('Enter warning reason:');
            if (reason && reason.trim()) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=warn_user&user_id=' + userId + '&reason=' + encodeURIComponent(reason)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        location.reload();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error sending warning', 'error');
                });
            }
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone and will delete all related data (orders, addresses, reviews, etc.).')) {
                const reason = prompt('Enter deletion reason:');
                if (reason && reason.trim()) {
                    fetch('admin.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete_user&user_id=' + userId + '&reason=' + encodeURIComponent(reason)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            location.reload();
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Error deleting user', 'error');
                    });
                }
            }
        }
        
        // Refund Management Functions
        function loadRefunds(status = 'all') {
            const refundsList = document.getElementById('refunds-list');
            
            // Show loading state
            refundsList.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #666;"></i>
                    <p style="color: #666; margin-top: 10px;">Loading refund requests...</p>
                </div>
            `;
            
            // Fetch refunds via AJAX
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_refunds&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRefunds(data.refunds);
                } else {
                    refundsList.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                            <p>Error loading refund requests: ${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                refundsList.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                        <p>Error loading refund requests</p>
                    </div>
                `;
            });
        }
        
        function displayRefunds(refunds) {
            const refundsList = document.getElementById('refunds-list');
            
            if (refunds.length === 0) {
                refundsList.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-receipt" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                        <p>No refund requests found</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="refunds-grid" style="display: grid; gap: 20px;">';
            
            refunds.forEach(refund => {
                const statusClass = `status-${refund.status}`;
                const statusColor = getStatusColor(refund.status);
                
                html += `
                    <div class="refund-card" style="border: 1px solid #eee; border-radius: 8px; padding: 20px; background: white;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div>
                                <h4 style="margin: 0 0 5px 0; color: #2c3e50;">Refund Request #${refund.refund_id}</h4>
                                <p style="margin: 0; color: #666; font-size: 14px;">Order: ${refund.order_number}</p>
                                <p style="margin: 0; color: #666; font-size: 14px;">Customer: ${refund.first_name} ${refund.last_name}</p>
                            </div>
                            <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                ${refund.status}
                            </span>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                ${refund.image_url ? `<img src="${refund.image_url}" alt="${refund.product_name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">` : ''}
                                <div>
                                    <strong>${refund.product_name}</strong>
                                    <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">Amount: ₱${parseFloat(refund.refund_amount).toFixed(2)}</p>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 10px;">
                                <strong>Reason:</strong> ${refund.refund_reason}
                            </div>
                            
                            ${refund.customer_message ? `<div style="margin-bottom: 10px;"><strong>Customer Message:</strong> ${refund.customer_message}</div>` : ''}
                            ${refund.admin_message ? `<div style="margin-bottom: 10px; background: #f8f9fa; padding: 10px; border-radius: 5px; border-left: 4px solid #e74c3c;"><strong>Admin Response:</strong> ${refund.admin_message}</div>` : ''}
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #eee;">
                            <span style="color: #666; font-size: 14px;">
                                Submitted: ${new Date(refund.created_at).toLocaleDateString()}
                            </span>
                            
                            ${refund.status === 'pending' ? `
                                <div style="display: flex; gap: 10px;">
                                    <button class="btn btn-success" style="padding: 8px 16px; font-size: 14px;" onclick="processRefund(${refund.refund_id}, 'approved')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger" style="padding: 8px 16px; font-size: 14px;" onclick="processRefund(${refund.refund_id}, 'declined')">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            refundsList.innerHTML = html;
        }
        
        function getStatusColor(status) {
            const colors = {
                'pending': '#ffc107',
                'approved': '#28a745',
                'declined': '#dc3545',
                'processing': '#17a2b8',
                'completed': '#28a745'
            };
            return colors[status] || '#6c757d';
        }
        
        function filterRefunds(status) {
            // Update active filter button
            document.querySelectorAll('.refund-filters .btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(`filter-${status}`).classList.add('active');
            
            // Load refunds with filter
            loadRefunds(status);
        }
        
        function processRefund(refundId, status) {
            const action = status === 'approved' ? 'approve' : 'decline';
            const message = prompt(`${action === 'approve' ? 'Approve' : 'Decline'} refund request. Add a message (optional):`);
            
            if (message === null) return; // User cancelled
            
            // Show loading state
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            fetch('admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=process_refund&refund_id=${refundId}&status=${status}&admin_message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload refunds
                    loadRefunds();
                    alert(`Refund request ${status} successfully!`);
                } else {
                    alert(`Error: ${data.message}`);
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing refund request');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
            // Load refunds when refunds tab is shown
            document.addEventListener('DOMContentLoaded', function() {
                // Override the refunds tab button click to load refunds
                const refundsTabButton = document.querySelector('button[onclick*="refunds"]');
                if (refundsTabButton) {
                    const originalOnclick = refundsTabButton.getAttribute('onclick');
                    refundsTabButton.setAttribute('onclick', originalOnclick + '; loadRefunds();');
                }
                
                // Override the user concerns tab button click to load concerns
                const concernsTabButton = document.querySelector('button[onclick*="user-concerns"]');
                if (concernsTabButton) {
                    const originalOnclick = concernsTabButton.getAttribute('onclick');
                    concernsTabButton.setAttribute('onclick', originalOnclick + '; loadConcerns();');
                }
            });
            
            // User Concerns Management Functions
            function loadConcerns(status = 'all') {
                const concernsList = document.getElementById('concerns-list');
                
                // Show loading state
                concernsList.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #666;"></i>
                        <p style="color: #666; margin-top: 10px;">Loading user concerns...</p>
                    </div>
                `;
                
                // Fetch concerns via AJAX
                fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_concerns&status=${status}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayConcerns(data.concerns);
                    } else {
                        concernsList.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                                <p>Error loading user concerns: ${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    concernsList.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                            <p>Error loading user concerns</p>
                        </div>
                    `;
                });
            }
            
            function displayConcerns(concerns) {
                const concernsList = document.getElementById('concerns-list');
                
                if (concerns.length === 0) {
                    concernsList.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-comments" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                            <p>No user concerns found</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="concerns-grid" style="display: grid; gap: 20px;">';
                
                concerns.forEach(concern => {
                    const statusClass = `status-${concern.status}`;
                    const statusColor = getConcernStatusColor(concern.status);
                    const isUnread = concern.status === 'new';
                    
                    html += `
                        <div class="concern-card" style="border: 1px solid #eee; border-radius: 8px; padding: 20px; background: white; ${isUnread ? 'border-left: 4px solid #ffc107;' : ''}">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0; color: #2c3e50;">${concern.subject}</h4>
                                    <p style="margin: 0; color: #666; font-size: 14px;">From: ${concern.name} (${concern.email})</p>
                                    ${concern.user_id ? `<p style="margin: 0; color: #666; font-size: 14px;">User ID: ${concern.user_id}</p>` : ''}
                                </div>
                                <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                                    ${concern.status.replace('_', ' ')}
                                </span>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 10px;">
                                    <strong>Message:</strong><br>
                                    <div style="margin-top: 8px; line-height: 1.5;">${concern.message.replace(/\n/g, '<br>')}</div>
                                </div>
                                
                                ${concern.admin_reply ? `
                                    <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007cba; margin-bottom: 10px;">
                                        <strong>Admin Reply:</strong><br>
                                        <div style="margin-top: 8px; line-height: 1.5;">${concern.admin_reply.replace(/\n/g, '<br>')}</div>
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #eee;">
                                <span style="color: #666; font-size: 14px;">
                                    Submitted: ${new Date(concern.created_at).toLocaleDateString()} at ${new Date(concern.created_at).toLocaleTimeString()}
                                </span>
                                
                                <div style="display: flex; gap: 10px;">
                                    ${concern.status !== 'closed' ? `
                                        <button class="btn btn-success" style="padding: 8px 16px; font-size: 14px;" onclick="updateConcern(${concern.concern_id}, 'closed')">
                                            <i class="fas fa-check"></i> Close & Reply
                                        </button>
                                        <button class="btn btn-warning" style="padding: 8px 16px; font-size: 14px;" onclick="updateConcern(${concern.concern_id}, 'in_progress')">
                                            <i class="fas fa-clock"></i> Mark In Progress
                                        </button>
                                        <button class="btn btn-info" style="padding: 8px 16px; font-size: 14px;" onclick="updateConcern(${concern.concern_id}, 'resolved')">
                                            <i class="fas fa-check-circle"></i> Mark Resolved
                                        </button>
                                    ` : `
                                        <span style="color: #28a745; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> Closed
                                        </span>
                                    `}
                                    <button class="btn btn-danger" style="padding: 8px 16px; font-size: 14px;" onclick="deleteConcern(${concern.concern_id})">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                concernsList.innerHTML = html;
            }
            
            function getConcernStatusColor(status) {
                const colors = {
                    'new': '#ffc107',
                    'in_progress': '#17a2b8',
                    'resolved': '#28a745',
                    'closed': '#6c757d'
                };
                return colors[status] || '#6c757d';
            }
            
            function filterConcerns(status) {
                // Update active filter button
                document.querySelectorAll('.concerns-filters .btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById(`filter-concerns-${status}`).classList.add('active');
                
                // Load concerns with filter
                loadConcerns(status);
            }
            
            function updateConcern(concernId, status) {
                let adminReply = '';
                
                if (status === 'closed') {
                    adminReply = prompt('Add a reply to the user (optional):');
                    if (adminReply === null) return; // User cancelled
                }
                
                // Show loading state
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                button.disabled = true;
                
                fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_concern&concern_id=${concernId}&status=${status}&admin_reply=${encodeURIComponent(adminReply)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload concerns
                        loadConcerns();
                        alert(`Concern ${status} successfully!`);
                    } else {
                        alert(`Error: ${data.message}`);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating concern');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
            
            function deleteConcern(concernId) {
                // Confirm deletion
                if (!confirm('Are you sure you want to delete this concern? This action cannot be undone.')) {
                    return;
                }
                
                // Show loading state
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                button.disabled = true;
                
                fetch('admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_concern&concern_id=${concernId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload concerns
                        loadConcerns();
                        alert('Concern deleted successfully!');
                    } else {
                        alert(`Error: ${data.message}`);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting concern');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
    </script>

    <script>
        // Fix for stuck modals - ensure all modals are hidden on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Hide all modals
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            
            // Ensure body is not blocked
            document.body.style.overflow = 'auto';
            document.body.style.pointerEvents = 'auto';
            
            // Remove any overlay that might be blocking clicks
            const overlays = document.querySelectorAll('.modal-backdrop, .overlay, .backdrop');
            overlays.forEach(overlay => overlay.remove());
            
            // Test if buttons are clickable
            const tabButtons = document.querySelectorAll('.tab-button');
            // console.log('Found', tabButtons.length, 'tab buttons');
            
            tabButtons.forEach((btn, index) => {
                btn.addEventListener('click', function(e) {
                    // console.log('Button clicked:', this.textContent.trim());
                    // Don't prevent default - let the onclick work
                });
            });
            
            // console.log('Admin panel initialized - all modals hidden');
        });
        
        // Emergency fix function
        function fixAdminPanel() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            document.body.style.overflow = 'auto';
            document.body.style.pointerEvents = 'auto';
            alert('Admin panel fixed - all modals hidden');
        }
        
        // Add keyboard shortcut to fix admin panel (Ctrl+Shift+F)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'F') {
                fixAdminPanel();
            }
        });
    </script>
</body>
</html>
