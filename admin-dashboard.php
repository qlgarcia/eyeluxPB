<?php
// Comprehensive Admin Panel Index
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
    LIMIT 5
");

// Low stock alerts
$low_stock_alerts = $db->fetchAll("
    SELECT p.*, c.category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.stock_quantity <= p.low_stock_threshold AND p.is_active = 1 
    ORDER BY p.stock_quantity ASC 
    LIMIT 5
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

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            letter-spacing: -0.01em;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: var(--bg-primary);
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-info span {
            color: var(--text-secondary);
            font-weight: 400;
        }
        .logout-btn {
            background: var(--terracotta);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .logout-btn:hover {
            background: var(--sage);
            transform: translateY(-2px);
            box-shadow: var(--shadow-subtle);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(139, 115, 85, 0.12);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .stat-icon.orders { background: var(--sage); }
        .stat-icon.revenue { background: var(--terracotta); }
        .stat-icon.products { background: var(--khaki-dark); }
        .stat-icon.users { background: var(--khaki-deep); }
        .stat-icon.pending { background: var(--sage); }
        .stat-icon.alerts { background: var(--terracotta); }
        .stat-content h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: 300;
        }
        .stat-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 400;
        }
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .module-card {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(139, 115, 85, 0.12);
            border-color: var(--sage);
        }
        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin-bottom: 15px;
        }
        .module-icon.dashboard { background: var(--sage); }
        .module-icon.orders { background: var(--terracotta); }
        .module-icon.products { background: var(--khaki-dark); }
        .module-icon.inventory { background: var(--khaki-deep); }
        .module-icon.users { background: var(--sage); }
        .module-icon.analytics { background: var(--terracotta); }
        .module-icon.cms { background: var(--khaki-dark); }
        .module-icon.settings { background: var(--khaki-deep); }
        .module-icon.notifications { background: var(--sage); }
        .module-icon.audit { background: var(--terracotta); }
        .module-card h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-weight: 400;
        }
        .module-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
            font-weight: 400;
        }
        .recent-section {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            margin-bottom: 25px;
        }
        .recent-section h3 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 400;
        }
        .recent-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recent-item:last-child {
            border-bottom: none;
        }
        .recent-item-info {
            flex: 1;
        }
        .recent-item-info h4 {
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 2px;
            font-weight: 400;
        }
        .recent-item-info p {
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 400;
        }
        .recent-item-action {
            margin-left: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .btn-primary {
            background: var(--sage);
            color: white;
        }
        .btn-info {
            background: var(--terracotta);
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-subtle);
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .stock-low { color: #ffc107; }
        .stock-out { color: #dc3545; }
        .quick-actions {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            margin-bottom: 25px;
        }
        .quick-actions h3 {
            color: var(--text-primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 400;
        }
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .quick-action {
            background: var(--bg-secondary);
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            border: 1px solid var(--border-light);
        }
        .quick-action:hover {
            background: var(--bg-accent);
            transform: translateY(-2px);
            border-color: var(--sage);
        }
        .quick-action i {
            font-size: 1.5rem;
            color: var(--sage);
            margin-bottom: 8px;
        }
        .quick-action h4 {
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 5px;
            font-weight: 400;
        }
        .quick-action p {
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 400;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> EyeLux Admin Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                <a href="?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['total_orders']) ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>$<?= number_format($stats['total_revenue'], 2) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon products">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['total_products']) ?></h3>
                    <p>Active Products</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['total_users']) ?></h3>
                    <p>Registered Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['pending_orders']) ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon alerts">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['low_stock_products']) ?></h3>
                    <p>Low Stock Alerts</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="quick-actions-grid">
                <a href="admin.php" class="quick-action">
                    <i class="fas fa-plus"></i>
                    <h4>Add Product</h4>
                    <p>Create new product</p>
                </a>
                <a href="analytics.php" class="quick-action">
                    <i class="fas fa-chart-bar"></i>
                    <h4>View Reports</h4>
                    <p>Sales analytics</p>
                </a>
                <a href="cms.php" class="quick-action">
                    <i class="fas fa-file-alt"></i>
                    <h4>Manage Pages</h4>
                    <p>Content management</p>
                </a>
                <a href="notifications.php" class="quick-action">
                    <i class="fas fa-bell"></i>
                    <h4>Send Notification</h4>
                    <p>Notify users</p>
                </a>
            </div>
        </div>
        
        <!-- Admin Modules -->
        <div class="modules-grid">
            <a href="admin.php" class="module-card">
                <div class="module-icon dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <h3>Product Management</h3>
                <p>Add, edit, and manage products. Update stock levels and product information.</p>
            </a>
            
            <a href="analytics.php" class="module-card">
                <div class="module-icon analytics">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Analytics & Reports</h3>
                <p>View sales analytics, product performance, and customer insights with interactive charts.</p>
            </a>
            
            <a href="cms.php" class="module-card">
                <div class="module-icon cms">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Content Management</h3>
                <p>Manage website pages, site settings, and content. Update About Us, Contact, FAQ pages.</p>
            </a>
            
            <a href="notifications.php" class="module-card">
                <div class="module-icon notifications">
                    <i class="fas fa-bell"></i>
                </div>
                <h3>Notification System</h3>
                <p>Send notifications to users, manage email templates, and track communication.</p>
            </a>
            
            <div class="module-card" onclick="showComingSoon('Order Management')">
                <div class="module-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Order Management</h3>
                <p>View and manage customer orders. Update order status and track shipments.</p>
            </div>
            
            <div class="module-card" onclick="showComingSoon('Inventory Management')">
                <div class="module-icon inventory">
                    <i class="fas fa-warehouse"></i>
                </div>
                <h3>Inventory Management</h3>
                <p>Track stock levels, manage inventory alerts, and monitor product availability.</p>
            </div>
            
            <div class="module-card" onclick="showComingSoon('User Management')">
                <div class="module-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <h3>User Management</h3>
                <p>Manage customer accounts, view user profiles, and handle user-related issues.</p>
            </div>
            
            <div class="module-card" onclick="showComingSoon('Settings & Configuration')">
                <div class="module-icon settings">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Settings & Configuration</h3>
                <p>Configure site settings, payment options, shipping settings, and system preferences.</p>
            </div>
            
            <div class="module-card" onclick="showComingSoon('Audit Trail')">
                <div class="module-icon audit">
                    <i class="fas fa-history"></i>
                </div>
                <h3>Audit Trail</h3>
                <p>View system logs, track admin actions, and monitor security events.</p>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Recent Orders -->
            <div class="recent-section">
                <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                <?php foreach ($recent_orders as $order): ?>
                <div class="recent-item">
                    <div class="recent-item-info">
                        <h4>#<?= $order['order_number'] ?></h4>
                        <p><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?> - $<?= number_format($order['total_amount'], 2) ?></p>
                    </div>
                    <div class="recent-item-action">
                        <span class="status-badge status-<?= strtolower($order['status_name']) ?>">
                            <?= htmlspecialchars($order['status_name']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Low Stock Alerts -->
            <div class="recent-section">
                <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h3>
                <?php foreach ($low_stock_alerts as $product): ?>
                <div class="recent-item">
                    <div class="recent-item-info">
                        <h4><?= htmlspecialchars($product['product_name']) ?></h4>
                        <p><?= htmlspecialchars($product['category_name']) ?> - Stock: <?= $product['stock_quantity'] ?></p>
                    </div>
                    <div class="recent-item-action">
                        <span class="<?= $product['stock_quantity'] == 0 ? 'stock-out' : 'stock-low' ?>">
                            <?= $product['stock_quantity'] == 0 ? 'Out of Stock' : 'Low Stock' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showComingSoon(moduleName) {
            alert(`${moduleName} module is coming soon! This feature will be available in the next update.`);
        }
        
        // Handle logout
        if (window.location.search.includes('logout=1')) {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'admin-complete.php?logout=1';
            }
        }
    </script>
</body>
</html>






