<?php
// Analytics Module for Admin Panel
require_once 'includes/config.php';
require_once 'includes/database.php';

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-complete.php');
    exit;
}

$db = new Database();

// Handle AJAX requests for analytics
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'get_analytics_data':
                $period = $_POST['period'] ?? '30';
                $report_type = $_POST['report_type'] ?? 'sales';
                
                $data = [];
                
                switch ($report_type) {
                    case 'sales':
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
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                
                if (!empty($report_data)) {
                    fputcsv($output, array_keys($report_data[0]));
                    foreach ($report_data as $row) {
                        fputcsv($output, $row);
                    }
                }
                
                fclose($output);
                exit;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Get dashboard data
$stats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders")['total'],
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'],
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status_id = 1")['count'],
    'low_stock_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= low_stock_threshold AND is_active = 1")['count']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - EyeLux Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: var(--bg-primary);
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border-light);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 1.8rem;
        }
        .back-btn {
            background: var(--sage);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .back-btn:hover {
            background: #5a6fd8;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        .stat-icon.orders { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.revenue { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-icon.products { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-icon.users { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .stat-content h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #333;
        }
        .stat-content p {
            color: #666;
            font-size: 0.9rem;
        }
        .analytics-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .analytics-tab {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #e9ecef;
            color: #495057;
        }
        .analytics-tab.active {
            background: #667eea;
            color: white;
        }
        .analytics-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        .controls select {
            padding: 8px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> Analytics Dashboard</h1>
            <a href="admin.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
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
        </div>
        
        <!-- Analytics Controls -->
        <div class="controls">
            <select id="analytics-period" onchange="loadAnalytics()">
                <option value="7">Last 7 Days</option>
                <option value="30" selected>Last 30 Days</option>
                <option value="90">Last 90 Days</option>
                <option value="365">Last Year</option>
            </select>
            <button class="btn btn-success" onclick="exportReport()">
                <i class="fas fa-download"></i> Export Report
            </button>
        </div>
        
        <!-- Analytics Tabs -->
        <div class="analytics-tabs">
            <button class="analytics-tab active" onclick="showAnalyticsTab('sales')">
                <i class="fas fa-chart-line"></i> Sales Analytics
            </button>
            <button class="analytics-tab" onclick="showAnalyticsTab('products')">
                <i class="fas fa-box"></i> Product Performance
            </button>
            <button class="analytics-tab" onclick="showAnalyticsTab('customers')">
                <i class="fas fa-users"></i> Customer Analytics
            </button>
            <button class="analytics-tab" onclick="showAnalyticsTab('categories')">
                <i class="fas fa-tags"></i> Category Performance
            </button>
        </div>
        
        <!-- Sales Analytics -->
        <div id="sales-analytics" class="analytics-content">
            <h3>Sales Trend</h3>
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
            
            <h3>Revenue Trend</h3>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        
        <!-- Product Performance -->
        <div id="products-analytics" class="analytics-content" style="display: none;">
            <h3>Top Selling Products</h3>
            <div id="products-loading" class="loading">
                <div class="spinner"></div> Loading product data...
            </div>
            <div class="table-container" id="products-table" style="display: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Brand</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Customer Analytics -->
        <div id="customers-analytics" class="analytics-content" style="display: none;">
            <h3>Top Customers</h3>
            <div id="customers-loading" class="loading">
                <div class="spinner"></div> Loading customer data...
            </div>
            <div class="table-container" id="customers-table" style="display: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody id="customers-tbody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Category Performance -->
        <div id="categories-analytics" class="analytics-content" style="display: none;">
            <h3>Category Performance</h3>
            <div id="categories-loading" class="loading">
                <div class="spinner"></div> Loading category data...
            </div>
            <div class="table-container" id="categories-table" style="display: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Products</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody id="categories-tbody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        let salesChart, revenueChart;
        
        // Initialize charts
        function initCharts() {
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Orders',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Revenue ($)',
                        data: [],
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Load analytics data
        function loadAnalytics() {
            const period = document.getElementById('analytics-period').value;
            const activeTab = document.querySelector('.analytics-tab.active').onclick.toString();
            
            if (activeTab.includes('sales')) {
                loadSalesData(period);
            } else if (activeTab.includes('products')) {
                loadProductsData(period);
            } else if (activeTab.includes('customers')) {
                loadCustomersData(period);
            } else if (activeTab.includes('categories')) {
                loadCategoriesData(period);
            }
        }
        
        // Load sales data
        function loadSalesData(period) {
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_analytics_data&period=${period}&report_type=sales`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    salesChart.data.labels = data.data.labels;
                    salesChart.data.datasets[0].data = data.data.orders;
                    salesChart.update();
                    
                    revenueChart.data.labels = data.data.labels;
                    revenueChart.data.datasets[0].data = data.data.revenue;
                    revenueChart.update();
                }
            });
        }
        
        // Load products data
        function loadProductsData(period) {
            document.getElementById('products-loading').style.display = 'block';
            document.getElementById('products-table').style.display = 'none';
            
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_analytics_data&period=${period}&report_type=products`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('products-tbody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(product => {
                        const row = tbody.insertRow();
                        row.insertCell(0).textContent = product.product_name;
                        row.insertCell(1).textContent = product.brand;
                        row.insertCell(2).textContent = product.total_sold || 0;
                        row.insertCell(3).textContent = '$' + (product.total_revenue || 0).toFixed(2);
                    });
                    
                    document.getElementById('products-loading').style.display = 'none';
                    document.getElementById('products-table').style.display = 'block';
                }
            });
        }
        
        // Load customers data
        function loadCustomersData(period) {
            document.getElementById('customers-loading').style.display = 'block';
            document.getElementById('customers-table').style.display = 'none';
            
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_analytics_data&period=${period}&report_type=customers`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('customers-tbody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(customer => {
                        const row = tbody.insertRow();
                        row.insertCell(0).textContent = customer.first_name + ' ' + customer.last_name;
                        row.insertCell(1).textContent = customer.email;
                        row.insertCell(2).textContent = customer.total_orders || 0;
                        row.insertCell(3).textContent = '$' + (customer.total_spent || 0).toFixed(2);
                        row.insertCell(4).textContent = customer.last_order ? new Date(customer.last_order).toLocaleDateString() : 'N/A';
                    });
                    
                    document.getElementById('customers-loading').style.display = 'none';
                    document.getElementById('customers-table').style.display = 'block';
                }
            });
        }
        
        // Load categories data
        function loadCategoriesData(period) {
            document.getElementById('categories-loading').style.display = 'block';
            document.getElementById('categories-table').style.display = 'none';
            
            fetch('analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_analytics_data&period=${period}&report_type=categories`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('categories-tbody');
                    tbody.innerHTML = '';
                    
                    data.data.forEach(category => {
                        const row = tbody.insertRow();
                        row.insertCell(0).textContent = category.category_name;
                        row.insertCell(1).textContent = category.product_count || 0;
                        row.insertCell(2).textContent = category.total_sold || 0;
                        row.insertCell(3).textContent = '$' + (category.total_revenue || 0).toFixed(2);
                    });
                    
                    document.getElementById('categories-loading').style.display = 'none';
                    document.getElementById('categories-table').style.display = 'block';
                }
            });
        }
        
        // Show analytics tab
        function showAnalyticsTab(tabName) {
            // Hide all content
            document.querySelectorAll('.analytics-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.analytics-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected content
            document.getElementById(tabName + '-analytics').style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Load data for the selected tab
            const period = document.getElementById('analytics-period').value;
            if (tabName === 'sales') {
                loadSalesData(period);
            } else if (tabName === 'products') {
                loadProductsData(period);
            } else if (tabName === 'customers') {
                loadCustomersData(period);
            } else if (tabName === 'categories') {
                loadCategoriesData(period);
            }
        }
        
        // Export report
        function exportReport() {
            const period = document.getElementById('analytics-period').value;
            const activeTab = document.querySelector('.analytics-tab.active').onclick.toString();
            
            let reportType = 'sales';
            if (activeTab.includes('products')) reportType = 'products';
            else if (activeTab.includes('customers')) reportType = 'customers';
            else if (activeTab.includes('categories')) reportType = 'categories';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'analytics.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_report';
            form.appendChild(actionInput);
            
            const reportTypeInput = document.createElement('input');
            reportTypeInput.type = 'hidden';
            reportTypeInput.name = 'report_type';
            reportTypeInput.value = reportType;
            form.appendChild(reportTypeInput);
            
            const periodInput = document.createElement('input');
            periodInput.type = 'hidden';
            periodInput.name = 'period';
            periodInput.value = period;
            form.appendChild(periodInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            loadSalesData(30); // Load default data
        });
    </script>
</body>
</html>






