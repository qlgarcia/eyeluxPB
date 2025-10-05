<?php
require_once 'includes/header.php';

$page_title = 'Request Refund';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=refund-request.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$pre_selected_order = $_GET['order_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $order_item_id = (int)($_POST['order_item_id'] ?? 0);
    $refund_amount = (float)($_POST['refund_amount'] ?? 0);
    $refund_reason = sanitizeInput($_POST['refund_reason'] ?? '');
    $customer_message = sanitizeInput($_POST['customer_message'] ?? '');
    
    // Validation
    if (empty($order_id) || empty($product_id) || empty($refund_amount) || empty($refund_reason)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($refund_amount <= 0) {
        $error_message = 'Refund amount must be greater than 0.';
    } else {
        // Submit refund request
        $result = submitRefundRequest($user_id, $order_id, $product_id, $order_item_id, $refund_amount, $refund_reason, $customer_message);
        
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
        }
    }
}

// Get user's orders for refund request
$db = Database::getInstance();
$user_orders = $db->fetchAll(
    "SELECT o.*, 
            (SELECT COUNT(*) FROM refund_requests rr WHERE rr.order_id = o.order_id AND rr.user_id = o.user_id AND rr.status = 'pending') as refund_count
     FROM orders o 
     WHERE o.user_id = ? AND o.status = 'delivered' 
     ORDER BY o.order_date DESC",
    [$user_id]
);

// Get user's refund requests
$refund_requests = getUserRefundRequests($user_id);
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>Request Refund</h1>
            <p>Submit a refund request for your delivered orders</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="refund-page">
            <!-- Refund Request Form -->
            <div class="refund-section">
                <h2>Submit Refund Request</h2>
                
                <?php if (empty($user_orders)): ?>
                    <div class="no-orders">
                        <div class="no-orders-content">
                            <i class="fas fa-shopping-bag" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                            <h3>No Eligible Orders</h3>
                            <p>You don't have any delivered orders eligible for refund.</p>
                            <a href="orders.php" class="btn btn-primary">View My Orders</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" class="refund-form" id="refundForm">
                        <div class="form-group">
                            <label for="order_id">Select Order *</label>
                            <select name="order_id" id="order_id" required onchange="loadOrderItems(this.value)">
                                <option value="">Choose an order...</option>
                                <?php foreach ($user_orders as $order): ?>
                                    <option value="<?php echo $order['order_id']; ?>" data-refund-count="<?php echo $order['refund_count']; ?>" <?php echo ($pre_selected_order == $order['order_id']) ? 'selected' : ''; ?>>
                                        Order #<?php echo htmlspecialchars($order['order_number']); ?> 
                                        - <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                                        - ₱<?php echo number_format($order['total_amount'], 2); ?>
                                        <?php if ($order['refund_count'] > 0): ?>
                                            (<?php echo $order['refund_count']; ?> refund<?php echo $order['refund_count'] > 1 ? 's' : ''; ?> pending)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="product-group" style="display: none;">
                            <label for="product_id">Select Product *</label>
                            <select name="product_id" id="product_id" required onchange="loadProductDetails()">
                                <option value="">Choose a product...</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="refund-amount-group" style="display: none;">
                            <label for="refund_amount">Refund Amount *</label>
                            <div class="amount-input">
                                <span class="currency">₱</span>
                                <input type="number" id="refund_amount" name="refund_amount" step="0.01" min="0.01" required readonly>
                            </div>
                            <small>Amount will be auto-filled based on product price</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="refund_reason">Reason for Refund *</label>
                            <select name="refund_reason" id="refund_reason" required>
                                <option value="">Select a reason...</option>
                                <option value="defective">Product is defective or damaged</option>
                                <option value="wrong_item">Wrong item received</option>
                                <option value="not_as_described">Product not as described</option>
                                <option value="size_issue">Size doesn't fit</option>
                                <option value="quality_issue">Quality not satisfactory</option>
                                <option value="changed_mind">Changed my mind</option>
                                <option value="other">Other reason</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_message">Additional Message</label>
                            <textarea name="customer_message" id="customer_message" rows="4" placeholder="Please provide any additional details about your refund request..."></textarea>
                        </div>
                        
                        <input type="hidden" name="order_item_id" id="order_item_id">
                        
                        <button type="submit" class="btn btn-primary">Submit Refund Request</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- My Refund Requests -->
            <div class="refund-section">
                <h2>My Refund Requests</h2>
                
                <?php if (empty($refund_requests)): ?>
                    <div class="no-refunds">
                        <div class="no-refunds-content">
                            <i class="fas fa-receipt" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                            <h3>No Refund Requests</h3>
                            <p>You haven't submitted any refund requests yet.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="refund-requests-list">
                        <?php foreach ($refund_requests as $request): ?>
                            <div class="refund-request-card">
                                <div class="refund-header">
                                    <div class="refund-info">
                                        <h3>Refund Request #<?php echo $request['refund_id']; ?></h3>
                                        <p>Order: <?php echo htmlspecialchars($request['order_number']); ?></p>
                                        <p>Product: <?php echo htmlspecialchars($request['product_name']); ?></p>
                                    </div>
                                    <div class="refund-status">
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="refund-details">
                                    <div class="refund-amount">
                                        <strong>Amount: ₱<?php echo number_format($request['refund_amount'], 2); ?></strong>
                                    </div>
                                    <div class="refund-reason">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($request['refund_reason']); ?>
                                    </div>
                                    <?php if ($request['customer_message']): ?>
                                        <div class="refund-message">
                                            <strong>Your Message:</strong> <?php echo htmlspecialchars($request['customer_message']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($request['admin_message']): ?>
                                        <div class="admin-message">
                                            <strong>Admin Response:</strong> <?php echo htmlspecialchars($request['admin_message']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="refund-footer">
                                    <span class="refund-date">
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                    </span>
                                    <?php if ($request['processed_at']): ?>
                                        <span class="processed-date">
                                            Processed: <?php echo date('M j, Y g:i A', strtotime($request['processed_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
.refund-page {
    max-width: 800px;
    margin: 0 auto;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
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

.refund-section {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.refund-section h2 {
    color: #2c3e50;
    margin-bottom: 25px;
    font-size: 24px;
}

.refund-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #e74c3c;
}

.amount-input {
    position: relative;
}

.amount-input .currency {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    font-weight: 600;
}

.amount-input input {
    padding-left: 35px;
}

.form-group small {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.no-orders,
.no-refunds {
    text-align: center;
    padding: 60px 20px;
}

.no-orders-content,
.no-refunds-content {
    color: #666;
}

.no-orders-content h3,
.no-refunds-content h3 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.no-orders-content p,
.no-refunds-content p {
    margin-bottom: 30px;
}

.refund-requests-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.refund-request-card {
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 20px;
    transition: box-shadow 0.3s;
}

.refund-request-card:hover {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.refund-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.refund-info h3 {
    color: #2c3e50;
    margin-bottom: 5px;
}

.refund-info p {
    color: #666;
    margin-bottom: 5px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-declined { background: #f8d7da; color: #721c24; }
.status-processing { background: #d1ecf1; color: #0c5460; }
.status-completed { background: #d4edda; color: #155724; }

.refund-details {
    margin-bottom: 15px;
}

.refund-details > div {
    margin-bottom: 10px;
}

.refund-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #eee;
    font-size: 14px;
    color: #666;
}

.admin-message {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    border-left: 4px solid #e74c3c;
}

.alert {
    padding: 15px;
    border-radius: 5px;
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
    .refund-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .refund-footer {
        flex-direction: column;
        gap: 5px;
        text-align: center;
    }
}
</style>

<script>
function loadOrderItems(orderId) {
    const productGroup = document.getElementById('product-group');
    const productSelect = document.getElementById('product_id');
    const refundAmountGroup = document.getElementById('refund-amount-group');
    
    if (!orderId) {
        productGroup.style.display = 'none';
        refundAmountGroup.style.display = 'none';
        return;
    }
    
    // Show loading state
    productSelect.innerHTML = '<option value="">Loading products...</option>';
    productGroup.style.display = 'block';
    
    // Fetch order items via AJAX
    fetch('ajax-get-order-items.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            productSelect.innerHTML = '<option value="">Choose a product...</option>';
            
            // Filter out items that already have pending refund requests
            const availableItems = data.items.filter(item => !item.has_refund_request);
            
            if (availableItems.length === 0) {
                productSelect.innerHTML = '<option value="">No products available for refund</option>';
                return;
            }
            
            availableItems.forEach(item => {
                const option = document.createElement('option');
                option.value = item.product_id;
                const price = parseFloat(item.unit_price || item.total_price || 0);
                option.textContent = `${item.product_name} (Qty: ${item.quantity}) - ₱${price.toFixed(2)}`;
                option.dataset.price = price;
                option.dataset.orderItemId = item.order_item_id;
                productSelect.appendChild(option);
            });
        } else {
            productSelect.innerHTML = '<option value="">No products found</option>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        productSelect.innerHTML = '<option value="">Error loading products</option>';
    });
}

function loadProductDetails() {
    const productSelect = document.getElementById('product_id');
    const refundAmountGroup = document.getElementById('refund-amount-group');
    const refundAmountInput = document.getElementById('refund_amount');
    const orderItemIdInput = document.getElementById('order_item_id');
    
    if (!productSelect.value) {
        refundAmountGroup.style.display = 'none';
        return;
    }
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const price = selectedOption.dataset.price;
    const orderItemId = selectedOption.dataset.orderItemId;
    
    refundAmountInput.value = parseFloat(price).toFixed(2);
    orderItemIdInput.value = orderItemId;
    refundAmountGroup.style.display = 'block';
}

// Auto-load order items if order is pre-selected
document.addEventListener('DOMContentLoaded', function() {
    const orderSelect = document.getElementById('order_id');
    if (orderSelect && orderSelect.value) {
        loadOrderItems(orderSelect.value);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
