<?php
require_once 'includes/header.php';

$page_title = 'Order Confirmation';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$order_id = (int)($_GET['order_id'] ?? 0);

if (!$order_id) {
    redirect('orders.php');
}

$user_id = $_SESSION['user_id'];

// Get order details
$db = Database::getInstance();
$order = $db->fetchOne(
    "SELECT o.*, sa.first_name as ship_first_name, sa.last_name as ship_last_name, 
            sa.address_line1 as ship_address, sa.city as ship_city, sa.state as ship_state, 
            sa.postal_code as ship_postal_code, sa.country as ship_country,
            ba.first_name as bill_first_name, ba.last_name as bill_last_name,
            ba.address_line1 as bill_address, ba.city as bill_city, ba.state as bill_state,
            ba.postal_code as bill_postal_code, ba.country as bill_country
     FROM orders o
     LEFT JOIN addresses sa ON o.shipping_address_id = sa.address_id
     LEFT JOIN addresses ba ON o.billing_address_id = ba.address_id
     WHERE o.order_id = ? AND o.user_id = ?",
    [$order_id, $user_id]
);

if (!$order) {
    redirect('orders.php');
}

// Get order items
$order_items = $db->fetchAll(
    "SELECT oi.*, p.image_url FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?",
    [$order_id]
);
?>

<main>
    <div class="container">
        <div class="order-confirmation">
            <div class="confirmation-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Order Confirmed!</h1>
                <p>Thank you for your purchase. Your order has been successfully placed.</p>
            </div>
            
            <div class="order-details-layout">
                <!-- Order Summary -->
                <div class="order-summary">
                    <h2>Order Summary</h2>
                    
                    <div class="order-info">
                        <div class="info-row">
                            <span class="label">Order Number:</span>
                            <span class="value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Order Date:</span>
                            <span class="value"><?php echo date('F j, Y \a\t g:i A', strtotime($order['order_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span class="value status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">Payment Method:</span>
                            <span class="value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Payment Status:</span>
                            <span class="value payment-<?php echo $order['payment_status']; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h3>Items Ordered</h3>
                        <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <p>SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                <p>Quantity: <?php echo $item['quantity']; ?></p>
                                <p>Price: <?php echo formatPrice($item['unit_price']); ?> each</p>
                            </div>
                            
                            <div class="item-total">
                                <?php echo formatPrice($item['total_price']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-totals">
                        <div class="total-line">
                            <span>Subtotal:</span>
                            <span><?php echo formatPrice($order['total_amount'] - $order['tax_amount'] - $order['shipping_amount']); ?></span>
                        </div>
                        <div class="total-line">
                            <span>Shipping:</span>
                            <span><?php echo $order['shipping_amount'] > 0 ? formatPrice($order['shipping_amount']) : 'Free'; ?></span>
                        </div>
                        <div class="total-line">
                            <span>Tax:</span>
                            <span><?php echo formatPrice($order['tax_amount']); ?></span>
                        </div>
                        <div class="total-line total">
                            <span>Total:</span>
                            <span><?php echo formatPrice($order['total_amount']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping & Billing Info -->
                <div class="address-info">
                    <div class="address-section">
                        <h3>Shipping Address</h3>
                        <div class="address-details">
                            <p><strong><?php echo htmlspecialchars($order['ship_first_name'] . ' ' . $order['ship_last_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($order['ship_address']); ?></p>
                            <p><?php echo htmlspecialchars($order['ship_city'] . ', ' . $order['ship_state'] . ' ' . $order['ship_postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($order['ship_country']); ?></p>
                        </div>
                    </div>
                    
                    <div class="address-section">
                        <h3>Billing Address</h3>
                        <div class="address-details">
                            <p><strong><?php echo htmlspecialchars($order['bill_first_name'] . ' ' . $order['bill_last_name']); ?></strong></p>
                            <p><?php echo htmlspecialchars($order['bill_address']); ?></p>
                            <p><?php echo htmlspecialchars($order['bill_city'] . ', ' . $order['bill_state'] . ' ' . $order['bill_postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($order['bill_country']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Next Steps -->
            <div class="next-steps">
                <h2>What's Next?</h2>
                <div class="steps-grid">
                    <div class="step-item">
                        <div class="step-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h4>Order Confirmation</h4>
                        <p>You'll receive an email confirmation with your order details.</p>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <h4>Processing</h4>
                        <p>We'll prepare your order and get it ready for shipment.</p>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h4>Shipping</h4>
                        <p>Your order will be shipped and you'll receive tracking information.</p>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h4>Delivery</h4>
                        <p>Your eyewear will be delivered to your specified address.</p>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="orders.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Orders
                </a>
                <a href="products.php" class="btn btn-outline">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print Order
                </button>
            </div>
            
            <?php if ($order['notes']): ?>
            <div class="order-notes">
                <h3>Order Notes</h3>
                <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.order-confirmation {
    padding: 20px 0;
}

.confirmation-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 40px;
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    border-radius: 10px;
}

.success-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.confirmation-header h1 {
    font-size: 36px;
    margin-bottom: 15px;
}

.confirmation-header p {
    font-size: 18px;
    opacity: 0.9;
}

.order-details-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.order-summary,
.address-info {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-summary h2,
.address-section h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 20px;
}

.order-info {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.info-row .label {
    font-weight: 600;
    color: #666;
}

.info-row .value {
    color: #2c3e50;
}

.status-pending { color: #f39c12; }
.status-processing { color: #3498db; }
.status-shipped { color: #9b59b6; }
.status-delivered { color: #27ae60; }
.status-cancelled { color: #e74c3c; }

.payment-pending { color: #f39c12; }
.payment-paid { color: #27ae60; }
.payment-failed { color: #e74c3c; }
.payment-refunded { color: #95a5a6; }

.order-items h3 {
    margin-bottom: 20px;
    color: #2c3e50;
}

.order-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
    align-items: center;
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 80px;
    height: 80px;
    border-radius: 5px;
    overflow: hidden;
    background: #f8f9fa;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 20px;
}

.item-details {
    flex: 1;
}

.item-details h4 {
    color: #2c3e50;
    margin-bottom: 5px;
}

.item-details p {
    color: #666;
    font-size: 14px;
    margin-bottom: 3px;
}

.item-total {
    font-weight: bold;
    color: #e74c3c;
    font-size: 16px;
}

.order-totals {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.total-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.total-line.total {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    border-top: 2px solid #e74c3c;
    padding-top: 10px;
}

.address-info {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.address-section {
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.address-section:last-child {
    border-bottom: none;
}

.address-details p {
    margin-bottom: 5px;
    color: #666;
}

.next-steps {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.next-steps h2 {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 30px;
    font-size: 24px;
}

.steps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 25px;
}

.step-item {
    text-align: center;
}

.step-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #e74c3c;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 15px;
}

.step-item h4 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.step-item p {
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 30px;
}

.order-notes {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-notes h3 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.order-notes p {
    color: #666;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .order-details-layout {
        grid-template-columns: 1fr;
    }
    
    .confirmation-header h1 {
        font-size: 28px;
    }
    
    .confirmation-header p {
        font-size: 16px;
    }
    
    .order-item {
        flex-direction: column;
        text-align: center;
    }
    
    .item-image {
        align-self: center;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .steps-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .action-buttons,
    .next-steps {
        display: none;
    }
    
    .confirmation-header {
        background: white !important;
        color: black !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>



