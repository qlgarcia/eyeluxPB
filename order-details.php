<?php
require_once 'includes/header.php';

$page_title = 'Order Details';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=order-details.php');
}

$order_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    redirect('orders.php');
}

// Get order details
$db = Database::getInstance();
$order = $db->fetchOne(
    "SELECT o.*, sa.first_name as ship_first_name, sa.last_name as ship_last_name, 
            sa.address_line1 as ship_address, sa.address_line2 as ship_address2,
            sa.city as ship_city, sa.state as ship_state, 
            sa.postal_code as ship_postal_code, sa.country as ship_country, sa.phone as ship_phone,
            ba.first_name as bill_first_name, ba.last_name as bill_last_name,
            ba.address_line1 as bill_address, ba.address_line2 as bill_address2,
            ba.city as bill_city, ba.state as bill_state,
            ba.postal_code as bill_postal_code, ba.country as bill_country, ba.phone as bill_phone
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
        <div class="order-details-page">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="index.php">Home</a> > 
                <a href="orders.php">My Orders</a> > 
                <span>Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
            </nav>
            
            <div class="order-header">
                <div class="order-title">
                    <h1>Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                    <p>Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['order_date'])); ?></p>
                </div>
                
                <div class="order-status">
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="order-details-layout">
                <!-- Order Items -->
                <div class="order-items-section">
                    <h2>Order Items</h2>
                    
                    <div class="items-list">
                        <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <div class="item-image">
                                <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            
                            <div class="item-details">
                                <h3>
                                    <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                </h3>
                                <p class="item-sku">SKU: <?php echo htmlspecialchars($item['product_sku']); ?></p>
                                
                                <div class="item-quantity">
                                    <span>Quantity: <?php echo $item['quantity']; ?></span>
                                </div>
                                
                                <div class="item-price">
                                    <span class="unit-price"><?php echo formatPrice($item['unit_price']); ?> each</span>
                                    <span class="total-price"><?php echo formatPrice($item['total_price']); ?> total</span>
                                </div>
                            </div>
                            
                            <div class="item-actions">
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <button onclick="openProductReviewModal(<?php echo $item['product_id']; ?>, <?php echo $order_id; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>')" 
                                            class="btn btn-primary" type="button">
                                        <i class="fas fa-star"></i> Write Review
                                    </button>
                                <?php endif; ?>
                                
                                <a href="product.php?id=<?php echo $item['product_id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> View Product
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Order Information -->
                <div class="order-info-section">
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        
                        <div class="summary-details">
                            <div class="summary-line">
                                <span>Subtotal:</span>
                                <span><?php echo formatPrice($order['total_amount'] - $order['tax_amount'] - $order['shipping_amount']); ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Shipping:</span>
                                <span><?php echo $order['shipping_amount'] > 0 ? formatPrice($order['shipping_amount']) : 'Free'; ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Tax:</span>
                                <span><?php echo formatPrice($order['tax_amount']); ?></span>
                            </div>
                            
                            <div class="summary-line total">
                                <span>Total:</span>
                                <span><?php echo formatPrice($order['total_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Information -->
                    <div class="shipping-info">
                        <h3>Shipping Information</h3>
                        
                        <div class="address-details">
                            <p><strong><?php echo htmlspecialchars($order['ship_first_name'] . ' ' . $order['ship_last_name']); ?></strong></p>
                            <?php if ($order['ship_address']): ?>
                                <p><?php echo htmlspecialchars($order['ship_address']); ?></p>
                            <?php endif; ?>
                            <?php if ($order['ship_address2']): ?>
                                <p><?php echo htmlspecialchars($order['ship_address2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($order['ship_city'] . ', ' . $order['ship_state'] . ' ' . $order['ship_postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($order['ship_country']); ?></p>
                            <?php if ($order['ship_phone']): ?>
                                <p><?php echo htmlspecialchars($order['ship_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($order['tracking_number']): ?>
                        <div class="tracking-info">
                            <h4>Tracking Information</h4>
                            <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($order['tracking_number']); ?></p>
                            <a href="#" class="btn btn-outline">
                                <i class="fas fa-truck"></i> Track Package
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Billing Information -->
                    <div class="billing-info">
                        <h3>Billing Information</h3>
                        
                        <div class="address-details">
                            <p><strong><?php echo htmlspecialchars($order['bill_first_name'] . ' ' . $order['bill_last_name']); ?></strong></p>
                            <?php if ($order['bill_address']): ?>
                                <p><?php echo htmlspecialchars($order['bill_address']); ?></p>
                            <?php endif; ?>
                            <?php if ($order['bill_address2']): ?>
                                <p><?php echo htmlspecialchars($order['bill_address2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($order['bill_city'] . ', ' . $order['bill_state'] . ' ' . $order['bill_postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($order['bill_country']); ?></p>
                            <?php if ($order['bill_phone']): ?>
                                <p><?php echo htmlspecialchars($order['bill_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="payment-info">
                            <h4>Payment Information</h4>
                            <p><strong>Payment Method:</strong> <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
                            <p><strong>Payment Status:</strong> 
                                <span class="payment-status payment-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Order Notes -->
                    <?php if ($order['notes']): ?>
                    <div class="order-notes">
                        <h3>Order Notes</h3>
                        <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Actions -->
                    <div class="order-actions">
                        <a href="orders.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                        
                        <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                            <button class="btn btn-secondary" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                <i class="fas fa-times"></i> Cancel Order
                            </button>
                        <?php endif; ?>
                        
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print Order
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Order Timeline -->
            <div class="order-timeline">
                <h2>Order Timeline</h2>
                
                <div class="timeline">
                    <div class="timeline-item completed">
                        <div class="timeline-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Order Placed</h4>
                            <p><?php echo date('F j, Y \a\t g:i A', strtotime($order['order_date'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if (in_array($order['status'], ['processing', 'shipped', 'delivered'])): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Order Processing</h4>
                            <p>Your order is being prepared for shipment.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($order['status'], ['shipped', 'delivered'])): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Order Shipped</h4>
                            <p>Your order has been shipped and is on its way.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] === 'delivered'): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="timeline-content">
                            <h4>Order Delivered</h4>
                            <p>Your order has been successfully delivered.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Product Review Modal -->
<div id="productReviewModal" class="product-review-modal">
    <div class="product-review-modal-content">
        <div class="product-review-modal-header">
            <h3><i class="fas fa-star"></i> Write a Review</h3>
            <span class="product-review-close" onclick="closeProductReviewModal()">&times;</span>
        </div>
        <div class="product-review-modal-body">
            <div class="product-review-info">
                <h4 id="product-review-name">Product Name</h4>
                <p>Share your experience with this product</p>
            </div>
            
            <div class="product-star-rating">
                <h4 style="text-align: center; margin-bottom: 15px; color: #495057; font-weight: 600;">How would you rate this product?</h4>
                <div class="star-rating">
                    <span class="star" onclick="setRating(1)" title="Poor">★</span>
                    <span class="star" onclick="setRating(2)" title="Fair">★</span>
                    <span class="star" onclick="setRating(3)" title="Good">★</span>
                    <span class="star" onclick="setRating(4)" title="Very Good">★</span>
                    <span class="star" onclick="setRating(5)" title="Excellent">★</span>
                </div>
                <div id="rating-text" style="text-align: center; margin-top: 10px; color: #6c757d; font-style: italic; min-height: 20px;"></div>
            </div>
            
            <div class="product-review-form">
                <div class="product-review-form-group">
                    <label for="product-review-title">
                        <i class="fas fa-heading"></i> Review Title
                    </label>
                    <input type="text" id="product-review-title" placeholder="Give your review a catchy title..." required>
                </div>
                
                <div class="product-review-form-group">
                    <label for="product-review-comment">
                        <i class="fas fa-comment"></i> Your Review
                    </label>
                    <textarea id="product-review-comment" placeholder="Share your experience with this product. What did you like? Any suggestions for improvement?" required></textarea>
                    <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">Help other customers by sharing your honest experience</small>
                </div>
            </div>
            
            <div class="product-review-actions">
                <button class="product-review-submit-btn" id="product-review-submit-btn" onclick="submitProductReview()">
                    <i class="fas fa-paper-plane"></i> Submit Review
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.order-details-page {
    padding: 20px 0;
}

.breadcrumb {
    margin-bottom: 20px;
    font-size: 14px;
    color: #666;
}

.breadcrumb a {
    color: #e74c3c;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.order-title h1 {
    font-size: 28px;
    color: #2c3e50;
    margin-bottom: 5px;
}

.order-title p {
    color: #666;
}

.status-badge {
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-processing { background: #d1ecf1; color: #0c5460; }
.status-shipped { background: #e2e3e5; color: #383d41; }
.status-delivered { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }

.order-details-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

.order-items-section,
.order-info-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-items-section h2,
.order-info-section h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 20px;
}

.items-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order-item {
    display: grid;
    grid-template-columns: 100px 1fr auto;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid #eee;
    align-items: center;
}

.order-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 100px;
    height: 100px;
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
    font-size: 24px;
}

.item-details h3 {
    margin-bottom: 10px;
}

.item-details h3 a {
    color: #2c3e50;
    text-decoration: none;
}

.item-details h3 a:hover {
    color: #e74c3c;
}

.item-sku {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.item-quantity {
    margin-bottom: 10px;
}

.item-price {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.unit-price {
    color: #666;
    font-size: 14px;
}

.total-price {
    font-weight: bold;
    color: #e74c3c;
    font-size: 16px;
}

.item-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.summary-details {
    margin-bottom: 20px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

.summary-line.total {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    border-bottom: 2px solid #e74c3c;
    padding-top: 10px;
}

.shipping-info,
.billing-info,
.order-notes {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.shipping-info:last-child,
.billing-info:last-child,
.order-notes:last-child {
    border-bottom: none;
}

.address-details p {
    margin-bottom: 5px;
    color: #666;
}

.tracking-info {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.tracking-info h4 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.payment-info {
    margin-top: 15px;
}

.payment-info h4 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.payment-status {
    font-weight: 600;
}

.payment-pending { color: #f39c12; }
.payment-paid { color: #27ae60; }
.payment-failed { color: #e74c3c; }
.payment-refunded { color: #95a5a6; }

.order-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.order-timeline {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-timeline h2 {
    color: #2c3e50;
    margin-bottom: 25px;
    font-size: 20px;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #eee;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding-left: 30px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -15px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #eee;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.timeline-item.completed .timeline-icon {
    background: #27ae60;
    color: white;
}

.timeline-content h4 {
    color: #2c3e50;
    margin-bottom: 5px;
}

.timeline-content p {
    color: #666;
    font-size: 14px;
}

@media (max-width: 768px) {
    .order-details-layout {
        grid-template-columns: 1fr;
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .order-item {
        grid-template-columns: 80px 1fr;
        gap: 15px;
    }
    
    .item-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        margin-top: 10px;
    }
    
    .item-image {
        width: 80px;
        height: 80px;
    }
    
    .order-actions {
        flex-direction: row;
        flex-wrap: wrap;
    }
}

/* Product Review Modal Styles */
.product-review-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.5) 100%);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.4s ease;
}

.product-review-modal-content {
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    margin: 3% auto;
    padding: 0;
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideInUp 0.4s ease;
    box-shadow: 
        0 25px 80px rgba(0,0,0,0.3),
        0 0 0 1px rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
}

.product-review-modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px 30px;
    border-radius: 20px 20px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.product-review-modal-header h3 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

.product-review-close {
    color: white;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
}

.product-review-close:hover {
    background: rgba(255,255,255,0.2);
    transform: scale(1.1);
}

.product-review-modal-body {
    padding: 35px;
    background: white;
}

.product-review-info {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    border: 1px solid #dee2e6;
}

.product-review-info h4 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 1.3rem;
}

.product-review-info p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
}

.product-star-rating {
    text-align: center;
    margin-bottom: 30px;
}

.star-rating {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin: 20px 0;
    padding: 15px;
    background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
    border-radius: 12px;
    border: 2px solid #ffeaa7;
}

.star {
    font-size: 35px;
    color: #ddd;
    cursor: pointer;
    transition: all 0.3s ease;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
}

.star:hover {
    transform: scale(1.2);
    color: #ffc107;
}

.star.active {
    color: #ffc107;
    transform: scale(1.1);
    filter: drop-shadow(0 4px 8px rgba(255,193,7,0.3));
}

.product-review-form {
    display: grid;
    gap: 20px;
}

.product-review-form-group {
    margin-bottom: 20px;
}

.product-review-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.product-review-form-group label i {
    margin-right: 8px;
    color: #667eea;
}

.product-review-form-group input,
.product-review-form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: #f8f9fa;
    font-family: inherit;
    position: relative;
    z-index: 1;
    pointer-events: auto;
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

.product-review-form-group input:focus,
.product-review-form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    transform: translateY(-2px);
}

.product-review-form-group textarea {
    height: 100px;
    resize: vertical;
    line-height: 1.5;
}

.product-review-actions {
    text-align: center;
    margin-top: 30px;
}

.product-review-submit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 18px 35px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.3);
    position: relative;
    overflow: hidden;
    width: 100%;
    max-width: 300px;
}

.product-review-submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.product-review-submit-btn:hover::before {
    left: 100%;
}

.product-review-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.4);
}

.product-review-submit-btn:active {
    transform: translateY(0);
}

.product-review-submit-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.product-review-submit-btn i {
    margin-right: 8px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInUp {
    from { 
        transform: translateY(50px) scale(0.9); 
        opacity: 0; 
    }
    to { 
        transform: translateY(0) scale(1); 
        opacity: 1; 
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

// Product Review Modal Functions
let currentProductId = 0;
let currentOrderId = 0;
let currentRating = 0;

function openProductReviewModal(productId, orderId, productName) {
    currentProductId = productId;
    currentOrderId = orderId;
    currentRating = 0;
    
    document.getElementById('product-review-name').textContent = productName;
    document.getElementById('product-review-title').value = '';
    document.getElementById('product-review-comment').value = '';
    document.getElementById('rating-text').textContent = '';
    
    // Reset stars
    const stars = document.querySelectorAll('.star');
    stars.forEach(star => star.classList.remove('active'));
    
    // Ensure input fields are enabled and focusable
    const titleInput = document.getElementById('product-review-title');
    const commentInput = document.getElementById('product-review-comment');
    
    titleInput.disabled = false;
    commentInput.disabled = false;
    titleInput.readOnly = false;
    commentInput.readOnly = false;
    
    // Remove any event listeners that might be preventing input
    titleInput.onkeydown = null;
    commentInput.onkeydown = null;
    titleInput.onkeyup = null;
    commentInput.onkeyup = null;
    
    document.getElementById('productReviewModal').style.display = 'block';
    
    // Focus on the title input after a short delay
    setTimeout(() => {
        titleInput.focus();
        
        // Debug: Check if input fields are working
        console.log('Input fields debug:');
        console.log('Title input disabled:', titleInput.disabled);
        console.log('Title input readOnly:', titleInput.readOnly);
        console.log('Comment input disabled:', commentInput.disabled);
        console.log('Comment input readOnly:', commentInput.readOnly);
        console.log('Title input style pointer-events:', window.getComputedStyle(titleInput).pointerEvents);
        console.log('Comment input style pointer-events:', window.getComputedStyle(commentInput).pointerEvents);
    }, 100);
}

function closeProductReviewModal() {
    document.getElementById('productReviewModal').style.display = 'none';
}

function setRating(rating) {
    currentRating = rating;
    const stars = document.querySelectorAll('.star');
    const ratingText = document.getElementById('rating-text');
    
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
    
    const ratingTexts = {
        1: 'Poor - Not satisfied',
        2: 'Fair - Could be better', 
        3: 'Good - Met expectations',
        4: 'Very Good - Exceeded expectations',
        5: 'Excellent - Outstanding!'
    };
    
    ratingText.textContent = ratingTexts[rating] || '';
}

function submitProductReview() {
    const title = document.getElementById('product-review-title').value.trim();
    const comment = document.getElementById('product-review-comment').value.trim();
    
    if (currentRating === 0) {
        alert('Please select a rating before submitting.');
        return;
    }
    
    if (!title) {
        alert('Please enter a review title.');
        return;
    }
    
    if (!comment) {
        alert('Please enter your review comment.');
        return;
    }
    
    const submitBtn = document.getElementById('product-review-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    // Submit review via AJAX
    fetch('ajax-submit-review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: currentProductId,
            order_id: currentOrderId,
            rating: currentRating,
            title: title,
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Review submitted successfully! Thank you for your feedback.');
            closeProductReviewModal();
            // Optionally reload the page to show updated review count
            location.reload();
        } else {
            let errorMessage = 'Error submitting review: ' + (data.message || 'Unknown error');
            if (data.debug) {
                errorMessage += '\n\nDebug Info:\n';
                errorMessage += 'Order ID: ' + data.debug.order_id + '\n';
                errorMessage += 'Product ID: ' + data.debug.product_id + '\n';
                errorMessage += 'User ID: ' + data.debug.user_id + '\n';
                errorMessage += 'Product exists: ' + data.debug.product_exists + '\n';
                errorMessage += 'Order items: ' + JSON.stringify(data.debug.order_items);
            }
            alert(errorMessage);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting review. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('productReviewModal');
    if (event.target === modal) {
        closeProductReviewModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProductReviewModal();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>



