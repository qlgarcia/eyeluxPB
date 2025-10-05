<?php
require_once 'includes/header.php';

$page_title = 'My Orders';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=orders.php');
}

$user_id = $_SESSION['user_id'];

// Get orders for the user
$db = Database::getInstance();
$orders = $db->fetchAll(
    "SELECT o.*, COUNT(oi.order_item_id) as item_count 
     FROM orders o
     LEFT JOIN order_items oi ON o.order_id = oi.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id
     ORDER BY o.order_date DESC",
    [$user_id]
);

// Get order status counts for summary
$status_counts = $db->fetchAll(
    "SELECT status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY status",
    [$user_id]
);

$status_summary = [];
foreach ($status_counts as $status) {
    $status_summary[$status['status']] = $status['count'];
}
?>

<main>
    <div class="container">
        <div class="orders-page">
            <div class="page-header">
                <h1>My Orders</h1>
                <p>Track and manage your orders</p>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary-cards">
                <div class="summary-card">
                    <div class="card-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo array_sum($status_summary); ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $status_summary['pending'] ?? 0; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon processing">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $status_summary['processing'] ?? 0; ?></h3>
                        <p>Processing</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon shipped">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $status_summary['shipped'] ?? 0; ?></h3>
                        <p>Shipped</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="card-icon delivered">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $status_summary['delivered'] ?? 0; ?></h3>
                        <p>Delivered</p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-shopping-bag" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                    <h2>No orders yet</h2>
                    <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
                    <a href="products.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php else: ?>
                <!-- Orders List -->
                <div class="orders-list">
                    <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                                <p class="order-date">Placed on <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                            </div>
                            
                            <div class="order-status">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="order-details">
                            <div class="order-summary">
                                <div class="summary-item">
                                    <span class="label">Items:</span>
                                    <span class="value"><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Total:</span>
                                    <span class="value"><?php echo formatPrice($order['total_amount']); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Payment:</span>
                                    <span class="value payment-<?php echo $order['payment_status']; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($order['tracking_number']): ?>
                            <div class="tracking-info">
                                <span class="label">Tracking Number:</span>
                                <span class="value"><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-actions">
                            <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <?php if ($order['status'] === 'delivered'): ?>
                                <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-star"></i> Write Review
                                </a>
                                <a href="refund-request.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-undo"></i> Request Refund
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                                <button class="btn btn-secondary" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                    <i class="fas fa-times"></i> Cancel Order
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Order Progress -->
                        <div class="order-progress">
                            <div class="progress-step <?php echo in_array($order['status'], ['pending', 'processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                                <div class="step-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <span>Order Placed</span>
                            </div>
                            
                            <div class="progress-step <?php echo in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'completed' : ''; ?>">
                                <div class="step-icon">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <span>Processing</span>
                            </div>
                            
                            <div class="progress-step <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'completed' : ''; ?>">
                                <div class="step-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <span>Shipped</span>
                            </div>
                            
                            <div class="progress-step <?php echo $order['status'] === 'delivered' ? 'completed' : ''; ?>">
                                <div class="step-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <span>Delivered</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>


<style>
.orders-page {
    padding: 20px 0;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.page-header p {
    color: #666;
    font-size: 16px;
}

.order-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.summary-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #e74c3c;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.card-icon.pending { background: #f39c12; }
.card-icon.processing { background: #3498db; }
.card-icon.shipped { background: #9b59b6; }
.card-icon.delivered { background: #27ae60; }

.card-content h3 {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

.card-content p {
    color: #666;
    font-size: 14px;
}

.no-orders {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.no-orders h2 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.no-orders p {
    color: #666;
    margin-bottom: 30px;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.order-info h3 {
    color: #2c3e50;
    margin-bottom: 5px;
    font-size: 20px;
}

.order-date {
    color: #666;
    font-size: 14px;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #d1ecf1; color: #0c5460; }
.status-shipped { background: #e2e3e5; color: #383d41; }
.status-delivered { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.payment-pending { color: #f39c12; }
.payment-paid { color: #27ae60; }
.payment-failed { color: #e74c3c; }
.payment-refunded { color: #95a5a6; }

.order-details {
    margin-bottom: 20px;
}

.order-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.summary-item .label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.summary-item .value {
    font-weight: 600;
    color: #2c3e50;
}

.tracking-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
}

.tracking-info .label {
    font-weight: 600;
    color: #666;
}

.tracking-info .value {
    font-family: monospace;
    color: #2c3e50;
}

.order-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.order-progress {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    padding: 20px 0;
}

.order-progress::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: #eee;
    z-index: 1;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    position: relative;
    z-index: 2;
    background: white;
    padding: 0 10px;
}

.progress-step.completed .step-icon {
    background: #27ae60;
    color: white;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #eee;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.3s;
}

.progress-step span {
    font-size: 12px;
    color: #666;
    text-align: center;
}

.progress-step.completed span {
    color: #27ae60;
    font-weight: 600;
}

@media (max-width: 768px) {
    .order-summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .order-summary {
        grid-template-columns: 1fr;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .order-progress {
        flex-direction: column;
        gap: 20px;
    }
    
    .order-progress::before {
        display: none;
    }
    
    .summary-card {
        flex-direction: column;
        text-align: center;
    }
}

</style>

<script>
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        // This would typically make an AJAX request to cancel the order
        alert('Order cancellation functionality would be implemented with AJAX');
    }
}

</script>

</script>

<?php require_once 'includes/footer.php'; ?>



