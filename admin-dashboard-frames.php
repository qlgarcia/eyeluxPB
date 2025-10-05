<?php
// Updated Admin Panel for Frames-Only Business Model
require_once 'includes/config.php';
require_once 'includes/database.php';

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-complete.php');
    exit;
}

$db = new Database();

// Get dashboard data
$stats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders")['total'],
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'],
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'],
    'low_stock_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= 5 AND is_active = 1")['count']
];

// Recent orders
$recent_orders = $db->fetchAll("
    SELECT o.*, u.first_name, u.last_name, u.email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.user_id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");

// Low stock alerts
$low_stock_alerts = $db->fetchAll("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.stock_quantity <= 5 AND p.is_active = 1 
    ORDER BY p.stock_quantity ASC 
    LIMIT 5
");

// Category statistics
$category_stats = $db->fetchAll("
    SELECT c.category_name, COUNT(p.product_id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.category_id, c.category_name
    ORDER BY product_count DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EyeLux</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 1.8rem;
        }
        .business-model-notice {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .business-model-notice i {
            margin-right: 8px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-card .currency {
            color: #e74c3c;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .order-info {
            flex: 1;
        }
        .order-info .order-number {
            font-weight: bold;
            color: #333;
        }
        .order-info .customer {
            color: #666;
            font-size: 0.9rem;
        }
        .order-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-info {
            flex: 1;
        }
        .product-info .product-name {
            font-weight: bold;
            color: #333;
        }
        .product-info .category {
            color: #666;
            font-size: 0.9rem;
        }
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .category-stats {
            margin-top: 20px;
        }
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .category-item:last-child {
            border-bottom: none;
        }
        .category-name {
            font-weight: bold;
            color: #333;
        }
        .category-count {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .action-btn {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .action-btn:hover {
            background: #c0392b;
        }
        .action-btn.secondary {
            background: #6c757d;
        }
        .action-btn.secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-glasses"></i> EyeLux Admin Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                <a href="admin.php?action=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>


        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="number"><?php echo number_format($stats['total_orders']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="number currency">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Frame Products</h3>
                <div class="number"><?php echo number_format($stats['total_products']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="number"><?php echo number_format($stats['total_users']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Orders</h3>
                <div class="number"><?php echo number_format($stats['pending_orders']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Low Stock Frames</h3>
                <div class="number"><?php echo number_format($stats['low_stock_products']); ?></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3><i class="fas fa-shopping-cart"></i> Recent Orders</h3>
                <?php if (empty($recent_orders)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No recent orders</p>
                <?php else: ?>
                    <?php foreach($recent_orders as $order): ?>
                    <div class="order-item">
                        <div class="order-info">
                            <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                            <div class="customer"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                        </div>
                        <div class="order-status status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h3>
                <?php if (empty($low_stock_alerts)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">All frames are well stocked</p>
                <?php else: ?>
                    <?php foreach($low_stock_alerts as $product): ?>
                    <div class="product-item">
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                        </div>
                        <div class="stock-warning">
                            <?php echo $product['stock_quantity']; ?> left
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-chart-pie"></i> Frame Categories</h3>
            <div class="category-stats">
                <?php foreach($category_stats as $category): ?>
                <div class="category-item">
                    <span class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></span>
                    <span class="category-count"><?php echo $category['product_count']; ?> frames</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="quick-actions">
            <a href="admin.php?action=products" class="action-btn">
                <i class="fas fa-glasses"></i> Manage Frame Products
            </a>
            <a href="admin.php?action=categories" class="action-btn">
                <i class="fas fa-tags"></i> Manage Categories
            </a>
            <a href="admin.php?action=orders" class="action-btn">
                <i class="fas fa-shopping-cart"></i> View Orders
            </a>
            <a href="admin.php?action=users" class="action-btn secondary">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="products.php" class="action-btn secondary" target="_blank">
                <i class="fas fa-external-link-alt"></i> View Store
            </a>
        </div>
    </div>
</body>
</html>
