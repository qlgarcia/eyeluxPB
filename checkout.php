<?php
require_once 'includes/header.php';

$page_title = 'Checkout';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=checkout.php');
}

$user_id = $_SESSION['user_id'];
$session_id = session_id();

// Check if we have selected items from cart
$selected_items = [];
if (isset($_POST['selected_items'])) {
    // Handle POST data from cart
    $selected_items = json_decode($_POST['selected_items'], true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Checkout Debug - JSON decode error: " . json_last_error_msg());
        $selected_items = [];
    } else {
        error_log("Checkout Debug - Selected items: " . json_encode($selected_items));
        error_log("Checkout Debug - Selected items count: " . count($selected_items));
    }
}

// If no selected items, get all cart items (fallback)
// Additional check: ensure it's not just null from json_decode error
if (empty($selected_items) || !is_array($selected_items)) {
    error_log("Checkout Debug - No selected items, using fallback to get all cart items");
    $cart_items = getCartItems($user_id, $session_id);
    if (empty($cart_items)) {
        redirect('cart.php');
    }
    error_log("Checkout Debug - Fallback: Using " . count($cart_items) . " cart items");
} else {
    error_log("Checkout Debug - Processing selected items only");
    error_log("Checkout Debug - Selected items is not empty, count: " . count($selected_items));
    error_log("Checkout Debug - Selected items content: " . json_encode($selected_items));
    // Get full item details for selected items
    $cart_items = [];
    $db = Database::getInstance();
    foreach ($selected_items as $selected_item) {
        $item = $db->fetchOne(
            "SELECT p.*, c.quantity FROM products p 
             LEFT JOIN cart c ON p.product_id = c.product_id 
             WHERE p.product_id = ? AND c.user_id = ?",
            [$selected_item['product_id'], $user_id]
        );
        
        if ($item) {
            $item['quantity'] = $selected_item['quantity'];
            $cart_items[] = $item;
        }
    }
    
    if (empty($cart_items)) {
        redirect('cart.php');
    }
}

error_log("Checkout Debug - Items to be processed: " . json_encode(array_map(function($item) {
    return ['product_id' => $item['product_id'], 'product_name' => $item['product_name'], 'quantity' => $item['quantity']];
}, $cart_items)));

$cart_total = calculateCartTotal($cart_items);
$tax_rate = 0.08; // 8% tax
$tax_amount = $cart_total * $tax_rate;
$shipping_amount = $cart_total >= 50 ? 0 : 9.99; // Free shipping over $50
$final_total = $cart_total + $tax_amount + $shipping_amount;

$error_message = '';
$success_message = '';

// Get user addresses
$db = Database::getInstance();
$addresses = $db->fetchAll("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC", [$user_id]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Checkout Debug - Form submission: selected_items preserved = " . (isset($_POST['selected_items']) ? 'YES' : 'NO'));
    if (isset($_POST['selected_items'])) {
        error_log("Checkout Debug - Form submission: selected_items value = " . $_POST['selected_items']);
    }
    error_log("Checkout Debug - Form submission: cart_items count = " . count($cart_items));
    error_log("Checkout Debug - Form submission: cart_items content = " . json_encode(array_map(function($item) {
        return ['product_id' => $item['product_id'], 'product_name' => $item['product_name'], 'quantity' => $item['quantity']];
    }, $cart_items)));
    $shipping_address_id = (int)($_POST['shipping_address'] ?? 0);
    $billing_address_id = (int)($_POST['billing_address'] ?? 0);
    $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Validation
    if (!$shipping_address_id) {
        $error_message = 'Please select a shipping address.';
    } elseif (!$billing_address_id) {
        $error_message = 'Please select a billing address.';
    } elseif (!$payment_method) {
        $error_message = 'Please select a payment method.';
    } else {
        try {
            $db->beginTransaction();
            
            // Create order
            $order_number = generateOrderNumber();
            $order_id = $db->insert(
                "INSERT INTO orders (user_id, order_number, total_amount, tax_amount, shipping_amount, 
                 shipping_address_id, billing_address_id, payment_method, payment_status, notes) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$user_id, $order_number, $final_total, $tax_amount, $shipping_amount, 
                 $shipping_address_id, $billing_address_id, $payment_method, 'pending', $notes]
            );
            
            // Add order items
            error_log("Checkout Debug - Order creation: About to create order with " . count($cart_items) . " items");
            foreach ($cart_items as $item) {
                error_log("Checkout Debug - Adding to order: " . $item['product_name'] . " (ID: " . $item['product_id'] . ", Qty: " . $item['quantity'] . ")");
                $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
                $db->insert(
                    "INSERT INTO order_items (order_id, product_id, product_name, product_sku, 
                     quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$order_id, $item['product_id'], $item['product_name'], $item['sku'] ?? 'N/A', 
                     $item['quantity'], $price, $price * $item['quantity']]
                );
                
                // Update product stock
                $db->execute(
                    "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?",
                    [$item['quantity'], $item['product_id']]
                );
                
                // Update sales count for best sellers tracking
                $db->execute(
                    "UPDATE products SET sales_count = COALESCE(sales_count, 0) + ? WHERE product_id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }
            
            // Clear only the selected items from cart after successful order
            error_log("Checkout Debug - Cart clearing: About to clear " . count($cart_items) . " items from cart");
            foreach ($cart_items as $item) {
                error_log("Checkout Debug - Clearing cart item: " . $item['product_name'] . " (ID: " . $item['product_id'] . ")");
                $db->execute(
                    "DELETE FROM cart WHERE user_id = ? AND product_id = ?",
                    [$user_id, $item['product_id']]
                );
            }
            
            $db->commit();
            
            // Create order placed notification
            createOrderPlacedNotification($order_id);
            
            // Redirect to order confirmation
            redirect("order-confirmation.php?order_id={$order_id}");
            
        } catch (Exception $e) {
            $db->rollback();
            $error_message = 'Failed to process order: ' . $e->getMessage();
            error_log("Checkout error: " . $e->getMessage() . " - " . $e->getTraceAsString());
        }
    }
}
?>

<main>
    <div class="container">
        <div class="checkout-page">
            <h1>Checkout</h1>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="checkout-layout">
                <!-- Checkout Steps -->
                <div class="checkout-steps">
                    <div class="step active">
                        <span class="step-number">1</span>
                        <span class="step-title">Shipping</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span class="step-title">Payment</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span class="step-title">Review</span>
                    </div>
                </div>
                
                <form method="POST" class="checkout-form">
                    <!-- Preserve selected items data -->
                    <input type="hidden" name="selected_items" value="<?php echo htmlspecialchars(json_encode($selected_items)); ?>">
                    <div class="checkout-content">
                        <!-- Shipping Address -->
                        <section class="checkout-section">
                            <h2>Shipping Address</h2>
                            
                            <?php if (!empty($addresses)): ?>
                                <div class="address-options">
                                    <?php foreach ($addresses as $address): ?>
                                    <div class="address-option">
                                        <input type="radio" name="shipping_address" value="<?php echo $address['address_id']; ?>" 
                                               id="ship_<?php echo $address['address_id']; ?>" 
                                               <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                        <label for="ship_<?php echo $address['address_id']; ?>">
                                            <div class="address-content">
                                                <strong><?php echo htmlspecialchars($address['first_name'] . ' ' . $address['last_name']); ?></strong>
                                                <?php if ($address['company']): ?>
                                                    <br><?php echo htmlspecialchars($address['company']); ?>
                                                <?php endif; ?>
                                                <br><?php echo htmlspecialchars($address['address_line1']); ?>
                                                <?php if ($address['address_line2']): ?>
                                                    <br><?php echo htmlspecialchars($address['address_line2']); ?>
                                                <?php endif; ?>
                                                <br><?php 
                                                $address_parts = [];
                                                if ($address['city']) $address_parts[] = $address['city'];
                                                if ($address['province']) $address_parts[] = $address['province'];
                                                elseif ($address['state']) $address_parts[] = $address['state'];
                                                if ($address['postal_code']) $address_parts[] = $address['postal_code'];
                                                echo htmlspecialchars(implode(', ', $address_parts));
                                                ?>
                                                <br>Philippines
                                                <?php if ($address['phone']): ?>
                                                    <br><?php echo htmlspecialchars($address['phone']); ?>
                                                <?php endif; ?>
                                                <?php if ($address['is_default']): ?>
                                                    <span class="default-badge">Default</span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="add-address">
                                <a href="profile.php?tab=addresses" class="btn btn-outline">
                                    <i class="fas fa-plus"></i> Add New Address
                                </a>
                            </div>
                        </section>
                        
                        <!-- Billing Address -->
                        <section class="checkout-section">
                            <h2>Billing Address</h2>
                            
                            <div class="billing-options">
                                <label class="checkbox-option">
                                    <input type="checkbox" id="same_as_shipping" checked>
                                    <span>Same as shipping address</span>
                                </label>
                            </div>
                            
                            <div id="billing-address-section" style="display: none;">
                                <?php if (!empty($addresses)): ?>
                                    <div class="address-options">
                                        <?php foreach ($addresses as $address): ?>
                                        <div class="address-option">
                                            <input type="radio" name="billing_address" value="<?php echo $address['address_id']; ?>" 
                                                   id="bill_<?php echo $address['address_id']; ?>" 
                                                   <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                            <label for="bill_<?php echo $address['address_id']; ?>">
                                                <div class="address-content">
                                                    <strong><?php echo htmlspecialchars($address['first_name'] . ' ' . $address['last_name']); ?></strong>
                                                    <?php if ($address['company']): ?>
                                                        <br><?php echo htmlspecialchars($address['company']); ?>
                                                    <?php endif; ?>
                                                    <br><?php echo htmlspecialchars($address['address_line1']); ?>
                                                    <?php if ($address['address_line2']): ?>
                                                        <br><?php echo htmlspecialchars($address['address_line2']); ?>
                                                    <?php endif; ?>
                                                    <br><?php 
                                                    $address_parts = [];
                                                    if ($address['city']) $address_parts[] = $address['city'];
                                                    if ($address['province']) $address_parts[] = $address['province'];
                                                    elseif ($address['state']) $address_parts[] = $address['state'];
                                                    if ($address['postal_code']) $address_parts[] = $address['postal_code'];
                                                    echo htmlspecialchars(implode(', ', $address_parts));
                                                    ?>
                                                    <br>Philippines
                                                    <?php if ($address['phone']): ?>
                                                        <br><?php echo htmlspecialchars($address['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        
                        <!-- Payment Method -->
                        <section class="checkout-section">
                            <h2>Payment Method</h2>
                            
                            <div class="payment-options">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="credit_card" id="credit_card" checked>
                                    <label for="credit_card">
                                        <i class="fas fa-credit-card"></i>
                                        <span>Credit Card</span>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="paypal" id="paypal">
                                    <label for="paypal">
                                        <i class="fab fa-paypal"></i>
                                        <span>PayPal</span>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="apple_pay" id="apple_pay">
                                    <label for="apple_pay">
                                        <i class="fab fa-apple-pay"></i>
                                        <span>Apple Pay</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="credit-card-form" class="payment-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="card_number">Card Number</label>
                                        <input type="text" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expiry_date">Expiry Date</label>
                                        <input type="text" id="expiry_date" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="form-group">
                                        <label for="cvv">CVV</label>
                                        <input type="text" id="cvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="card_name">Name on Card</label>
                                        <input type="text" id="card_name" placeholder="John Doe">
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- Order Notes -->
                        <section class="checkout-section">
                            <h2>Order Notes (Optional)</h2>
                            <div class="form-group">
                                <textarea name="notes" placeholder="Any special instructions for your order..."></textarea>
                            </div>
                        </section>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h3>Order Summary</h3>
                        
                        <div class="summary-items">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="summary-item">
                                <div class="item-info">
                                    <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    <span class="item-qty">Qty: <?php echo $item['quantity']; ?></span>
                                </div>
                                <span class="item-price">
                                    <?php 
                                    $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
                                    echo formatPrice($price * $item['quantity']); 
                                    ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-totals">
                            <div class="summary-line">
                                <span>Subtotal</span>
                                <span><?php echo formatPrice($cart_total); ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Shipping</span>
                                <span><?php echo $shipping_amount > 0 ? formatPrice($shipping_amount) : 'Free'; ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Tax</span>
                                <span><?php echo formatPrice($tax_amount); ?></span>
                            </div>
                            
                            <div class="summary-line total">
                                <span>Total</span>
                                <span><?php echo formatPrice($final_total); ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary place-order-btn">
                            <i class="fas fa-lock"></i> Place Order
                        </button>
                        
                        <div class="security-info">
                            <i class="fas fa-shield-alt"></i>
                            <span>Your payment information is secure and encrypted</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
.checkout-page {
    padding: 20px 0;
}

.checkout-page h1 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 30px;
}

.checkout-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.checkout-steps {
    display: flex;
    justify-content: center;
    margin-bottom: 40px;
    grid-column: 1 / -1;
}

.step {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 25px;
    background: #f8f9fa;
    border-radius: 25px;
    margin: 0 10px;
    color: #666;
}

.step.active {
    background: #e74c3c;
    color: white;
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.step.active .step-number {
    background: rgba(255,255,255,0.9);
    color: #e74c3c;
}

.checkout-content {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.checkout-section {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid #eee;
}

.checkout-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.checkout-section h2 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 20px;
}

.address-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.address-option {
    position: relative;
}

.address-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.address-option label {
    display: block;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.address-option input[type="radio"]:checked + label {
    border-color: #e74c3c;
    background: #fff5f5;
}

.address-content {
    font-size: 14px;
    line-height: 1.5;
}

.default-badge {
    background: #e74c3c;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    margin-left: 10px;
}

.add-address {
    text-align: center;
}

.billing-options {
    margin-bottom: 20px;
}

.checkbox-option {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-option input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.payment-options {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.payment-option {
    flex: 1;
}

.payment-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.payment-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-option input[type="radio"]:checked + label {
    border-color: #e74c3c;
    background: #fff5f5;
}

.payment-option i {
    font-size: 24px;
    margin-bottom: 10px;
    color: #666;
}

.payment-option input[type="radio"]:checked + label i {
    color: #e74c3c;
}

.payment-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row:first-child {
    grid-template-columns: 1fr;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.form-group textarea {
    height: 80px;
    resize: vertical;
}

.order-summary {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    height: fit-content;
    position: sticky;
    top: 20px;
}

.order-summary h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 20px;
}

.summary-items {
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.item-info {
    display: flex;
    flex-direction: column;
}

.item-name {
    font-weight: 600;
    color: #2c3e50;
}

.item-qty {
    font-size: 12px;
    color: #666;
}

.item-price {
    font-weight: 600;
    color: #e74c3c;
}

.summary-totals {
    margin-bottom: 25px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.summary-line.total {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    border-top: 2px solid #e74c3c;
    padding-top: 10px;
}

.place-order-btn {
    width: 100%;
    padding: 15px;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
}

.security-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #666;
    font-size: 14px;
    text-align: center;
}

.security-info i {
    color: #27ae60;
}

.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    
    .checkout-steps {
        flex-direction: column;
        gap: 10px;
    }
    
    .step {
        margin: 0;
    }
    
    .payment-options {
        flex-direction: column;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .order-summary {
        position: static;
    }
}
</style>

<script>
// Handle billing address toggle
document.getElementById('same_as_shipping').addEventListener('change', function() {
    const billingSection = document.getElementById('billing-address-section');
    if (this.checked) {
        billingSection.style.display = 'none';
        // Copy shipping address to billing
        const shippingAddress = document.querySelector('input[name="shipping_address"]:checked');
        if (shippingAddress) {
            document.querySelector('input[name="billing_address"]').value = shippingAddress.value;
        }
    } else {
        billingSection.style.display = 'block';
    }
});

// Format card number
document.getElementById('card_number').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Format expiry date
document.getElementById('expiry_date').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }
    e.target.value = value;
});

// Format CVV
document.getElementById('cvv').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});

// Handle payment method changes
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const creditCardForm = document.getElementById('credit-card-form');
        if (this.value === 'credit_card') {
            creditCardForm.style.display = 'block';
        } else {
            creditCardForm.style.display = 'none';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

