<?php
require_once 'includes/header.php';
require_once 'includes/paypal_config.php';

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
                                    <input type="radio" name="payment_method" value="paypal" id="paypal" checked>
                                    <label for="paypal">
                                        <i class="fab fa-paypal"></i>
                                        <span>PayPal</span>
                                    </label>
                                </div>
                            </div>

                            <div id="paypal-button-container" style="display: block; margin-top: 20px;"></div>
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
                        
                        <!-- PayPal SDK will render buttons in the container above -->
                        <div style="display: none;">
                            <button type="button" class="btn btn-primary place-order-btn" disabled>
                                <i class="fas fa-lock"></i> Pay with PayPal
                            </button>
                        </div>
                        
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

// Only PayPal is available; ensure container is visible
document.addEventListener('DOMContentLoaded', function() {
    const paypalContainer = document.getElementById('paypal-button-container');
    if (paypalContainer) paypalContainer.style.display = 'block';
});
</script>

<!-- PayPal SDK will be loaded dynamically to avoid conflicts -->
<script>
// PayPal Integration with Fallback
(function() {
    'use strict';
    
    const PAYPAL_CLIENT_ID = '<?php echo PAYPAL_CLIENT_ID; ?>';
    const paypalContainer = document.getElementById('paypal-button-container');
    
    if (!paypalContainer) {
        console.error('PayPal container not found');
        return;
    }
    
    // Show loading state
    
    // If a clean global is already present, use it directly to avoid re-loading conflicts
    if (typeof window.paypal !== 'undefined' && window.paypal && window.paypal.Buttons) {
        console.log('Using existing PayPal global');
        initializePayPalButtons(window.paypal);
        return;
    }

    // Create script element and load PayPal SDK under a unique namespace to avoid collisions
    const script = document.createElement('script');
    // Explicitly request buttons component and enable debug logging for easier diagnostics
    // Note: PAYPAL_CLIENT_ID should be a sandbox client ID for demo/testing
    const PAYPAL_NAMESPACE = 'paypal_sdk';
    script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(PAYPAL_CLIENT_ID)}&components=buttons&currency=USD&intent=capture&disable-funding=credit,card&debug=true`;
    script.async = true;
    script.crossOrigin = 'anonymous';
    script.setAttribute('data-namespace', PAYPAL_NAMESPACE);
    
    // Suppress PayPal SDK internal errors that don't affect functionality
    const originalConsoleError = console.error;
    console.error = function(...args) {
        if (args[0] && args[0].includes && args[0].includes('startsWith')) {
            // Suppress the known PayPal SDK internal error
            return;
        }
        originalConsoleError.apply(console, args);
    };
    
    script.onload = function() {
        console.log('PayPal SDK script tag loaded');
        
        // Restore original console.error after PayPal loads
        setTimeout(() => {
            console.error = originalConsoleError;
        }, 2000);
        
        // The SDK may attach the global a tick later; poll briefly before failing
        const maxAttempts = 10; // ~2s total
        let attempts = 0;
        const waitForPaypal = setInterval(() => {
            attempts++;
            // Prefer namespaced object; fallback to default if provided
            const paypalRef = (window[PAYPAL_NAMESPACE]) ? window[PAYPAL_NAMESPACE] : window.paypal;
            if (paypalRef && paypalRef.Buttons) {
                clearInterval(waitForPaypal);
                initializePayPalButtons(paypalRef);
            } else if (attempts >= maxAttempts) {
                clearInterval(waitForPaypal);
                console.error('PayPal SDK global not available after load');
                showPayPalError('PayPal SDK not available. Please refresh the page and try again.');
            }
        }, 200);
    };
    
    script.onerror = function() {
        console.error('Failed to load PayPal SDK');
        showPayPalError('Failed to load PayPal SDK. Please refresh the page and try again.');
    };
    
    // Add script to head
    document.head.appendChild(script);
    
    function initializePayPalButtons(paypalRef) {
        if (!paypalRef || !paypalRef.Buttons) {
            showPayPalError('PayPal SDK not available. Please refresh the page and try again.');
            return;
        }
        
        try {
            paypalRef.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal'
                },
                createOrder: function(data, actions) {
                    console.log('Creating PayPal order...');
                    const form = document.querySelector('.checkout-form');
                    if (!form) {
                        throw new Error('Checkout form not found');
                    }
                    
                    const formData = new FormData(form);
                    formData.append('payment_method', 'paypal');
                    
                    // Ensure required address fields are present in POST
                    try {
                        const shippingChecked = document.querySelector('input[name="shipping_address"]:checked');
                        const sameAsShipping = document.getElementById('same_as_shipping');
                        if (sameAsShipping && sameAsShipping.checked && shippingChecked) {
                            // Force billing_address to match shipping if checkbox is enabled
                            formData.set('billing_address', shippingChecked.value);
                        }
                    } catch (e) {
                        console.warn('Address sync warning:', e);
                    }
                    
                    return fetch('paypal-create-order.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                        body: formData
                    })
                    .then(async (res) => {
                        const text = await res.text();
                        let json;
                        try {
                            json = JSON.parse(text);
                        } catch (err) {
                            console.error('Create order returned non-JSON. Raw response snippet:', text.slice(0, 1000));
                            throw new Error('Create order failed: Non-JSON response from server');
                        }
                        if (!res.ok || json.error) {
                            const msg = json.error || 'Create order failed';
                            throw new Error(msg);
                        }
                        return json;
                    })
                    .then(data => {
                        paypalContainer.setAttribute('data-local-order-id', data.order_id);
                        return data.id;
                    });
                },
                onApprove: function(data, actions) {
                    console.log('PayPal order approved:', data);
                    const localOrderId = paypalContainer.getAttribute('data-local-order-id');
                    
                    if (!localOrderId) {
                        throw new Error('Local order ID not found');
                    }
                    
                    const fd = new FormData();
                    fd.append('paypal_order_id', data.orderID);
                    fd.append('order_id', localOrderId);
                    
                    return fetch('paypal-capture-order.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.error) throw new Error(resp.error);
                        if (resp.redirect) {
                            window.location.href = resp.redirect;
                        }
                    })
                    .catch(err => {
                        console.error('Payment capture failed:', err);
                        alert('Payment capture failed: ' + err.message);
                    });
                },
                onError: function(err) {
                    console.error('PayPal error:', err);
                    alert('PayPal error: ' + (err?.message || 'Unexpected error occurred'));
                },
                onCancel: function(data) {
                    console.log('PayPal payment cancelled:', data);
                }
            }).render('#paypal-button-container').then(function() {
                console.log('PayPal buttons rendered successfully');
                console.log('PayPal container content:', paypalContainer.innerHTML.length > 0 ? 'Has content' : 'Empty');
            }).catch(function(err) {
                console.error('Failed to render PayPal buttons:', err);
                showPayPalError('Failed to initialize PayPal buttons. Please try again.');
            });
        } catch (err) {
            console.error('PayPal initialization error:', err);
            showPayPalError('PayPal initialization failed. Please refresh the page and try again.');
        }
    }
    
    function showPayPalError(message) {
        paypalContainer.innerHTML = `
            <div style="text-align: center; padding: 20px; border: 1px solid #ff6b6b; border-radius: 5px; background-color: #ffe0e0; color: #d63031;">
                <i class="fas fa-exclamation-triangle"></i>
                <p style="margin: 10px 0;">${message}</p>
                <button onclick="location.reload()" style="padding: 8px 16px; background: #ff6b6b; color: white; border: none; border-radius: 3px; cursor: pointer;">
                    Refresh Page
                </button>
            </div>
        `;
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>

