<?php
// Start session before any output
session_start();

require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Enhanced admin authentication with database
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_POST['admin_login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        try {
            $db = new Database();
            $admin = $db->fetchOne(
                "SELECT * FROM admin_users WHERE username = ? AND is_active = 1", 
                [$username]
            );
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // Update last login
                $db->query(
                    "UPDATE admin_users SET last_login = NOW() WHERE admin_id = ?", 
                    [$admin['admin_id']]
                );
                
                // Log login
                $db->query(
                    "INSERT INTO audit_trail (admin_id, action_type, table_name, ip_address, user_agent) VALUES (?, 'login', 'admin_users', ?, ?)",
                    [$admin['admin_id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
                );
            } else {
                $login_error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            $login_error = 'Login failed: ' . $e->getMessage();
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
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 400px;
            }
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .login-header h1 {
                color: #333;
                margin-bottom: 10px;
            }
            .form-group {
                margin-bottom: 20px;
            }
            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #333;
                font-weight: 500;
            }
            .form-group input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                font-size: 16px;
                transition: border-color 0.3s ease;
            }
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
            }
            .btn-login {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s ease;
            }
            .btn-login:hover {
                transform: translateY(-2px);
            }
            .error-message {
                background: #fee;
                color: #c33;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1><i class="fas fa-shield-alt"></i> Admin Login</h1>
                <p>EyeLux Management System</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="admin_login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
                Default: admin / admin123
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        $action = $_POST['action'];
        
        switch ($action) {
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
                $low_stock_threshold = intval($_POST['low_stock_threshold'] ?? 10);
                
                // Generate SKU
                $sku = strtoupper(substr($brand, 0, 3)) . rand(1000, 9999);
                
                $result = $db->insert(
                    "INSERT INTO products (product_name, description, price, sale_price, category_id, stock_quantity, image_url, brand, color, gender, is_featured, is_new_arrival, is_active, sku, low_stock_threshold, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())",
                    [$product_name, $description, $price, $sale_price, $category_id, $stock_quantity, $image_url, $brand, $color, $gender, $is_featured, $is_new_arrival, $sku, $low_stock_threshold]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'create', 'products', ?, ?)",
                        [$_SESSION['admin_id'], $result, json_encode(['product_name' => $product_name, 'brand' => $brand])]
                    );
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Product added successfully!",
                        'product_id' => $result
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add product']);
                }
                break;
                
            case 'update_order_status':
                $order_id = intval($_POST['order_id']);
                $status_id = intval($_POST['status_id']);
                $notes = $_POST['notes'] ?? '';
                
                $result = $db->query(
                    "UPDATE orders SET status_id = ?, notes = ?, updated_at = NOW() WHERE order_id = ?",
                    [$status_id, $notes, $order_id]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'update', 'orders', ?, ?)",
                        [$_SESSION['admin_id'], $order_id, json_encode(['status_id' => $status_id, 'notes' => $notes])]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
                }
                break;
                
            case 'update_stock':
                $product_id = intval($_POST['product_id']);
                $stock_quantity = intval($_POST['stock_quantity']);
                
                $result = $db->query(
                    "UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE product_id = ?",
                    [$stock_quantity, $product_id]
                );
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id, new_values) VALUES (?, 'update', 'products', ?, ?)",
                        [$_SESSION['admin_id'], $product_id, json_encode(['stock_quantity' => $stock_quantity])]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                $result = $db->query("DELETE FROM products WHERE product_id = ?", [$product_id]);
                
                if ($result) {
                    // Log the action
                    $db->query(
                        "INSERT INTO audit_trail (admin_id, action_type, table_name, record_id) VALUES (?, 'delete', 'products', ?)",
                        [$_SESSION['admin_id'], $product_id]
                    );
                    
                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
                }
                break;
                
            case 'get_analytics_data':
                $period = $_POST['period'] ?? '30'; // days
                $report_type = $_POST['report_type'] ?? 'sales';
                
                $data = [];
                
                switch ($report_type) {
                    case 'sales':
                        // Sales data for charts
                        $sales_data = $db->fetchAll("
                            SELECT 
                                DATE(order_date) as date,
                                COUNT(*) as orders_count,
                                SUM(total_amount) as total_revenue,
                                AVG(total_amount) as avg_order_value
                            FROM orders 
                            WHERE order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                            GROUP BY DATE(order_date)
                            ORDER BY date ASC
                        ", [$period]);
                        
                        $data = [
                            'labels' => array_column($sales_data, 'date'),
                            'orders' => array_column($sales_data, 'orders_count'),
                            'revenue' => array_column($sales_data, 'total_revenue'),
                            'avg_order_value' => array_column($sales_data, 'avg_order_value')
                        ];
                        break;
                        
                    case 'products':
                        // Top selling products
                        $top_products = $db->fetchAll("
                            SELECT 
                                p.product_name,
                                p.brand,
                                SUM(oi.quantity) as total_sold,
                                SUM(oi.total_price) as total_revenue
                            FROM order_items oi
                            JOIN products p ON oi.product_id = p.product_id
                            JOIN orders o ON oi.order_id = o.order_id
                            WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                            GROUP BY p.product_id
                            ORDER BY total_sold DESC
                            LIMIT 10
                        ", [$period]);
                        
                        $data = $top_products;
                        break;
                        
                    case 'customers':
                        // Customer analytics
                        $customer_data = $db->fetchAll("
                            SELECT 
                                u.first_name,
                                u.last_name,
                                u.email,
                                COUNT(o.order_id) as total_orders,
                                SUM(o.total_amount) as total_spent,
                                MAX(o.order_date) as last_order
                            FROM users u
                            LEFT JOIN orders o ON u.user_id = o.user_id
                            WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) OR o.order_date IS NULL
                            GROUP BY u.user_id
                            ORDER BY total_spent DESC
                            LIMIT 20
                        ", [$period]);
                        
                        $data = $customer_data;
                        break;
                        
                    case 'categories':
                        // Category performance
                        $category_data = $db->fetchAll("
                            SELECT 
                                c.category_name,
                                COUNT(DISTINCT p.product_id) as product_count,
                                SUM(oi.quantity) as total_sold,
                                SUM(oi.total_price) as total_revenue
                            FROM categories c
                            LEFT JOIN products p ON c.category_id = p.category_id
                            LEFT JOIN order_items oi ON p.product_id = oi.product_id
                            LEFT JOIN orders o ON oi.order_id = o.order_id
                            WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) OR o.order_date IS NULL
                            GROUP BY c.category_id
                            ORDER BY total_revenue DESC
                        ", [$period]);
                        
                        $data = $category_data;
                        break;
                }
                
                echo json_encode(['success' => true, 'data' => $data]);
                break;
                
            case 'export_report':
                $report_type = $_POST['report_type'] ?? 'sales';
                $period = $_POST['period'] ?? '30';
                $format = $_POST['format'] ?? 'csv';
                
                // Generate report data
                $report_data = [];
                $filename = '';
                
                switch ($report_type) {
                    case 'sales':
                        $report_data = $db->fetchAll("
                            SELECT 
                                o.order_number,
                                o.order_date,
                                CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                                u.email,
                                o.total_amount,
                                os.status_name as status
                            FROM orders o
                            LEFT JOIN users u ON o.user_id = u.user_id
                            LEFT JOIN order_status os ON o.status_id = os.status_id
                            WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                            ORDER BY o.order_date DESC
                        ", [$period]);
                        $filename = "sales_report_" . date('Y-m-d') . ".csv";
                        break;
                        
                    case 'products':
                        $report_data = $db->fetchAll("
                            SELECT 
                                p.product_name,
                                p.brand,
                                c.category_name,
                                p.price,
                                p.stock_quantity,
                                p.is_active,
                                COUNT(oi.item_id) as times_ordered,
                                SUM(oi.quantity) as total_sold
                            FROM products p
                            LEFT JOIN categories c ON p.category_id = c.category_id
                            LEFT JOIN order_items oi ON p.product_id = oi.product_id
                            LEFT JOIN orders o ON oi.order_id = o.order_id
                            WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) OR o.order_date IS NULL
                            GROUP BY p.product_id
                            ORDER BY total_sold DESC
                        ", [$period]);
                        $filename = "products_report_" . date('Y-m-d') . ".csv";
                        break;
                }
                
                if ($format === 'csv') {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    
                    if (!empty($report_data)) {
                        // Write headers
                        fputcsv($output, array_keys($report_data[0]));
                        
                        // Write data
                        foreach ($report_data as $row) {
                            fputcsv($output, $row);
                        }
                    }
                    
                    fclose($output);
                    exit;
                }
                
                echo json_encode(['success' => true, 'data' => $report_data]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Get dashboard data
$db = new Database();

// Dashboard statistics
$stats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders")['total'],
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'],
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status_id = 1")['count'],
    'low_stock_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= low_stock_threshold AND is_active = 1")['count']
];

// Recent orders
$recent_orders = $db->fetchAll("
    SELECT o.*, os.status_name, u.first_name, u.last_name, u.email 
    FROM orders o 
    LEFT JOIN order_status os ON o.status_id = os.status_id 
    LEFT JOIN users u ON o.user_id = u.user_id 
    ORDER BY o.order_date DESC 
    LIMIT 10
");

// Low stock alerts
$low_stock_alerts = $db->fetchAll("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.stock_quantity <= p.low_stock_threshold AND p.is_active = 1 
    ORDER BY p.stock_quantity ASC 
    LIMIT 10
");

// Order statuses
$order_statuses = $db->fetchAll("SELECT * FROM order_status ORDER BY status_id");

// Categories
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");

// Products
$products = $db->fetchAll("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    ORDER BY p.created_at DESC
");
?>
