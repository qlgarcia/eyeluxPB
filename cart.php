<?php
// Handle AJAX requests first, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Include only necessary files for AJAX
    require_once 'includes/config.php';
    require_once 'includes/database.php';
    require_once 'includes/functions.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    header('Content-Type: application/json');
    
    try {
        // Check if user is logged in
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Please login to access cart', 'login_required' => true]);
            exit;
        }
        
        $action = $_POST['ajax_action'] ?? '';
        $product_id = (int)($_POST['product_id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        $db = Database::getInstance();
        
        switch ($action) {
            case 'update_quantity':
                $quantity = max(0, (int)($_POST['quantity'] ?? 0));
                if ($quantity === 0) {
                    // Remove item
                    $db->execute("DELETE FROM cart WHERE user_id = ? AND product_id = ?", 
                                [$user_id, $product_id]);
                } else {
                    // Update quantity
                    $db->execute("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?", 
                                [$quantity, $user_id, $product_id]);
                }
                break;
                
            case 'remove_item':
                $db->execute("DELETE FROM cart WHERE user_id = ? AND product_id = ?", 
                            [$user_id, $product_id]);
                break;
                
            case 'clear_cart':
                clearCart($user_id, null);
                break;
        }
        
        // Get updated cart info
        $cart_items = getCartItems($user_id, null);
        $cart_total = calculateCartTotal($cart_items);
        $cart_count = count($cart_items);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cart_count' => $cart_count,
            'cart_total' => $cart_total,
            'cart_items' => $cart_items
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Regular page request - include full header
require_once 'includes/header.php';

$page_title = 'Shopping Cart';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=cart.php');
}

// Get user info
$user_id = $_SESSION['user_id'];

// Handle cart actions (legacy - for non-AJAX requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    switch ($action) {
        case 'update_quantity':
            $quantity = max(0, (int)($_POST['quantity'] ?? 0));
            if ($quantity === 0) {
                // Remove item
                $db = Database::getInstance();
                $db->execute("DELETE FROM cart WHERE user_id = ? AND product_id = ?", 
                            [$user_id, $product_id]);
            } else {
                // Update quantity
                $db = Database::getInstance();
                $db->execute("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?", 
                            [$quantity, $user_id, $product_id]);
            }
            break;
            
        case 'remove_item':
            $db = Database::getInstance();
            $db->execute("DELETE FROM cart WHERE user_id = ? AND product_id = ?", 
                        [$user_id, $product_id]);
            break;
            
        case 'clear_cart':
            $db = Database::getInstance();
            $db->execute("DELETE FROM cart WHERE user_id = ?", 
                        [$user_id]);
            break;
    }
    
    // Use JavaScript redirect to avoid header issues
    echo "<script>window.location.href = 'cart.php';</script>";
    exit;
}

// Get cart items
$cart_items = getCartItems($user_id, null);
$cart_total = calculateCartTotal($cart_items);
$cart_count = count($cart_items);
?>

<main>
    <div class="container">
        <div class="cart-page">
            <h1>Shopping Cart</h1>
            
            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                    <h2>Your cart is empty</h2>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="cart-header">
                            <div class="header-left">
                                <h2>Cart Items (<?php echo $cart_count; ?>)</h2>
                                <label class="select-all-container">
                                    <input type="checkbox" id="select-all" checked onchange="toggleAllItems()">
                                    <span class="checkmark"></span>
                                    Select All
                                </label>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="clearCart()">Clear Cart</button>
                        </div>
                        
                        <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                            <div class="item-checkbox">
                                <input type="checkbox" 
                                       id="item_<?php echo $item['product_id']; ?>" 
                                       class="item-select-checkbox" 
                                       value="<?php echo $item['product_id']; ?>"
                                       checked
                                       onchange="updateSelectedItems()">
                                <label for="item_<?php echo $item['product_id']; ?>"></label>
                            </div>
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
                                <h3 class="item-name">
                                    <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </a>
                                </h3>
                                
                                <div class="item-price">
                                    <?php 
                                    $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
                                    ?>
                                    <span class="unit-price"><?php echo formatPrice($price); ?></span>
                                    <span class="total-price"><?php echo formatPrice($price * $item['quantity']); ?></span>
                                </div>
                                
                                <div class="item-stock">
                                    <?php if ($item['stock_quantity'] > 0): ?>
                                        <span class="in-stock">In Stock (<?php echo $item['stock_quantity']; ?> available)</span>
                                    <?php else: ?>
                                        <span class="out-of-stock">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="item-quantity">
                                <div class="quantity-controls">
                                    <button type="button" onclick="decreaseQuantity(<?php echo $item['product_id']; ?>)">-</button>
                                    <input type="number" id="qty_<?php echo $item['product_id']; ?>" 
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="0" max="<?php echo $item['stock_quantity']; ?>"
                                           onchange="updateQuantity(<?php echo $item['product_id']; ?>)">
                                    <button type="button" onclick="increaseQuantity(<?php echo $item['product_id']; ?>)">+</button>
                                </div>
                            </div>
                            
                            <div class="item-actions">
                                <button type="button" class="btn btn-outline remove-btn" onclick="removeItem(<?php echo $item['product_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <div class="summary-card">
                            <h3>Order Summary</h3>
                            
                            <div class="summary-line">
                                <span>Subtotal (<span id="selected-count"><?php echo $cart_count; ?></span> items)</span>
                                <span id="selected-subtotal"><?php echo formatPrice($cart_total); ?></span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Shipping</span>
                                <span class="free-shipping" id="shipping-text">Free</span>
                            </div>
                            
                            <div class="summary-line">
                                <span>Tax</span>
                                <span id="selected-tax"><?php echo formatPrice($cart_total * 0.08); ?></span>
                            </div>
                            
                            <div class="summary-line total">
                                <span>Total</span>
                                <span id="selected-total"><?php echo formatPrice($cart_total * 1.08); ?></span>
                            </div>
                            
                            <div class="checkout-actions">
                                <?php if (isLoggedIn()): ?>
                                    <button type="button" class="btn btn-primary checkout-btn" onclick="proceedToCheckout()">
                                        Proceed to Checkout
                                    </button>
                                <?php else: ?>
                                    <a href="login.php?redirect=checkout.php" class="btn btn-primary checkout-btn">
                                        Login to Checkout
                                    </a>
                                <?php endif; ?>
                                
                                <a href="products.php" class="btn btn-outline continue-shopping-btn">
                                    Continue Shopping
                                </a>
                            </div>
                            
                            <div class="security-info">
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure checkout with SSL encryption</span>
                            </div>
                        </div>
                        
                        <!-- Promo Code -->
                        <div class="promo-code">
                            <h4>Have a promo code?</h4>
                            <form class="promo-form">
                                <input type="text" placeholder="Enter promo code" name="promo_code">
                                <button type="submit" class="btn btn-outline">Apply</button>
                            </form>
                        </div>
                        
                        <!-- Shipping Info -->
                        <div class="shipping-info">
                            <h4>Shipping Information</h4>
                            <ul>
                                <li><i class="fas fa-truck"></i> Free shipping on orders over $50</li>
                                <li><i class="fas fa-clock"></i> 2-3 business days delivery</li>
                                <li><i class="fas fa-undo"></i> 30-day return policy</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

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

.cart-page {
    padding: 20px 0;
    background: var(--bg-primary);
    min-height: 100vh;
}

.cart-page h1 {
    font-size: 32px;
    color: var(--text-primary);
    margin-bottom: 30px;
    font-weight: 300;
    letter-spacing: -0.02em;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.empty-cart {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-primary);
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.empty-cart h2 {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-weight: 400;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.empty-cart p {
    color: var(--text-secondary);
    margin-bottom: 30px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.cart-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.cart-items {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 25px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-light);
}

.cart-header h2 {
    color: var(--text-primary);
    margin: 0;
    font-weight: 400;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr auto auto;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border-light);
    align-items: center;
}

.cart-item:last-child {
    border-bottom: none;
}

.item-image {
    width: 100px;
    height: 100px;
    border-radius: 12px;
    overflow: hidden;
    background: var(--bg-secondary);
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

.item-details {
    min-width: 0;
}

.item-name {
    margin-bottom: 10px;
}

.item-name a {
    color: var(--text-primary);
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    transition: all 0.3s ease;
}

.item-name a:hover {
    color: var(--sage);
}

.item-price {
    margin-bottom: 5px;
}

.unit-price {
    color: var(--text-secondary);
    font-size: 14px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.total-price {
    font-weight: 500;
    color: var(--sage);
    font-size: 16px;
    margin-left: 10px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.item-stock {
    font-size: 12px;
}

.in-stock {
    color: var(--sage);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.out-of-stock {
    color: var(--terracotta);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 5px;
}

.quantity-controls button {
    width: 30px;
    height: 30px;
    border: 1px solid var(--border-light);
    background: var(--bg-secondary);
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    color: var(--sage);
    transition: all 0.3s ease;
}

.quantity-controls button:hover {
    background: var(--sage);
    color: white;
    transform: translateY(-1px);
}

.quantity-controls input {
    width: 60px;
    height: 30px;
    text-align: center;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.remove-btn {
    padding: 8px 12px;
    color: var(--terracotta);
    border: 1px solid var(--terracotta);
    background: transparent;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.remove-btn:hover {
    background: var(--terracotta);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.cart-summary {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.summary-card {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 25px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.summary-card h3 {
    color: var(--text-primary);
    margin-bottom: 20px;
    font-size: 20px;
    font-weight: 400;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.summary-line.total {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    border-bottom: 2px solid #e74c3c;
    margin-bottom: 20px;
}

.free-shipping {
    color: #27ae60;
    font-weight: 600;
}

.checkout-actions {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.checkout-btn,
.continue-shopping-btn {
    width: 100%;
    padding: 15px;
    font-size: 16px;
    font-weight: 600;
}

.security-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #666;
    font-size: 14px;
}

.security-info i {
    color: #27ae60;
}

.promo-code,
.shipping-info {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.promo-code h4,
.shipping-info h4 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.promo-form {
    display: flex;
    gap: 10px;
}

.promo-form input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.shipping-info ul {
    list-style: none;
    padding: 0;
}

.shipping-info li {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    color: #666;
}

.shipping-info i {
    color: #e74c3c;
    width: 16px;
}

@media (max-width: 768px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr;
        gap: 15px;
    }
    
    .item-quantity,
    .item-actions {
        grid-column: 1 / -1;
        margin-top: 10px;
    }
    
    .item-quantity {
        justify-self: start;
    }
    
    .item-actions {
        justify-self: end;
    }
    
    .item-image {
        width: 80px;
        height: 80px;
    }
}

/* Button Styles */
.btn-primary {
    background: var(--sage);
    color: white;
    border: 1px solid var(--sage);
    transition: all 0.3s ease;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.btn-primary:hover {
    background: var(--terracotta);
    border-color: var(--terracotta);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.btn-secondary {
    background: var(--khaki-dark);
    color: white;
    border: 1px solid var(--khaki-dark);
    transition: all 0.3s ease;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.btn-secondary:hover {
    background: var(--khaki-deep);
    border-color: var(--khaki-deep);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.btn-outline {
    background: transparent;
    color: var(--sage);
    border: 2px solid var(--sage);
    transition: all 0.3s ease;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.btn-outline:hover {
    background: var(--sage);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

/* Checkbox Styles */
.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.select-all-container {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-secondary);
}

.select-all-container input[type="checkbox"] {
    display: none;
}

.checkmark {
    width: 18px;
    height: 18px;
    border: 2px solid var(--khaki-dark);
    border-radius: 3px;
    position: relative;
    transition: all 0.2s ease;
}

.select-all-container input[type="checkbox"]:checked + .checkmark {
    background: var(--sage);
    border-color: var(--sage);
}

.select-all-container input[type="checkbox"]:checked + .checkmark::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.select-all-container input[type="checkbox"]:indeterminate + .checkmark {
    background: var(--sage);
    border-color: var(--sage);
}

.select-all-container input[type="checkbox"]:indeterminate + .checkmark::after {
    content: '−';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 14px;
    font-weight: bold;
}

.cart-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-subtle);
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.item-checkbox {
    display: flex;
    align-items: center;
}

.item-checkbox input[type="checkbox"] {
    display: none;
}

.item-checkbox label {
    width: 20px;
    height: 20px;
    border: 2px solid var(--khaki-dark);
    border-radius: 4px;
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
}

.item-checkbox input[type="checkbox"]:checked + label {
    background: var(--sage);
    border-color: var(--sage);
}

.item-checkbox input[type="checkbox"]:checked + label::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.item-checkbox:hover label {
    border-color: var(--sage);
    transform: scale(1.05);
}

.cart-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-medium);
}
</style>

<script>
function increaseQuantity(productId) {
    const input = document.getElementById('qty_' + productId);
    const max = parseInt(input.getAttribute('max'));
    const current = parseInt(input.value);
    if (current < max) {
        input.value = current + 1;
        updateQuantity(productId);
    }
}

function decreaseQuantity(productId) {
    const input = document.getElementById('qty_' + productId);
    const current = parseInt(input.value);
    if (current > 0) {
        input.value = current - 1;
        updateQuantity(productId);
    }
}

function updateQuantity(productId) {
    const quantity = document.getElementById('qty_' + productId).value;
    const input = document.getElementById('qty_' + productId);
    
    // Show loading state on input
    input.style.opacity = '0.6';
    input.disabled = true;
    
    const formData = new FormData();
    formData.append('ajax_action', 'update_quantity');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = data.cart_count;
                cartCount.classList.add('animate');
                setTimeout(() => cartCount.classList.remove('animate'), 600);
            }
            
            // Update cart summary
            updateCartSummary(data);
            
            // If quantity is 0, remove the item from display
            if (quantity == 0) {
                const itemElement = document.querySelector(`[data-product-id="${productId}"]`);
                if (itemElement) {
                    itemElement.style.transition = 'opacity 0.3s ease';
                    itemElement.style.opacity = '0';
                    setTimeout(() => itemElement.remove(), 300);
                }
            }
        } else {
            if (typeof showNotification === 'function') {
                showNotification('error', data.message);
            } else {
                alert('Error: ' + data.message);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('error', 'Something went wrong. Please try again.');
        } else {
            alert('Something went wrong. Please try again.');
        }
    })
    .finally(() => {
        // Reset input state
        input.style.opacity = '1';
        input.disabled = false;
    });
}

function removeItem(productId) {
    if (!confirm('Remove this item from cart?')) return;
    
    const itemElement = document.querySelector(`[data-product-id="${productId}"]`);
    if (itemElement) {
        // Show loading state
        itemElement.style.opacity = '0.6';
        itemElement.style.pointerEvents = 'none';
    }
    
    const formData = new FormData();
    formData.append('ajax_action', 'remove_item');
    formData.append('product_id', productId);

    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = data.cart_count;
                cartCount.classList.add('animate');
                setTimeout(() => cartCount.classList.remove('animate'), 600);
            }
            
            // Update cart summary
            updateCartSummary(data);
            
            // Remove item from display with animation
            if (itemElement) {
                itemElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                itemElement.style.opacity = '0';
                itemElement.style.transform = 'translateX(-100%)';
                setTimeout(() => itemElement.remove(), 300);
            }
        } else {
            if (typeof showNotification === 'function') {
                showNotification('error', data.message);
            } else {
                alert('Error: ' + data.message);
            }
            // Reset item state on error
            if (itemElement) {
                itemElement.style.opacity = '1';
                itemElement.style.pointerEvents = 'auto';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('error', 'Something went wrong. Please try again.');
        } else {
            alert('Something went wrong. Please try again.');
        }
        // Reset item state on error
        if (itemElement) {
            itemElement.style.opacity = '1';
            itemElement.style.pointerEvents = 'auto';
        }
    });
}

function clearCart() {
    if (!confirm('Are you sure you want to clear your cart?')) return;
    
    // Show loading overlay
    if (typeof showLoading === 'function') {
        showLoading('Clearing cart...');
    }
    
    const formData = new FormData();
    formData.append('ajax_action', 'clear_cart');

    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = 0;
                cartCount.classList.add('animate');
                setTimeout(() => cartCount.classList.remove('animate'), 600);
            }
            
            // Hide loading and reload page
            if (typeof hideLoading === 'function') {
                hideLoading();
            }
            setTimeout(() => location.reload(), 500);
        } else {
            if (typeof hideLoading === 'function') {
                hideLoading();
            }
            if (typeof showNotification === 'function') {
                showNotification('error', data.message);
            } else {
                alert('Error: ' + data.message);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof hideLoading === 'function') {
            hideLoading();
        }
        if (typeof showNotification === 'function') {
            showNotification('error', 'Something went wrong. Please try again.');
        } else {
            alert('Something went wrong. Please try again.');
        }
    });
}

function updateCartSummary(data) {
    // Update cart count in header
    document.querySelector('.cart-header h2').textContent = `Cart Items (${data.cart_count})`;
    
    // Update summary totals
    document.querySelector('.summary-line span:last-child').textContent = formatPrice(data.cart_total);
    document.querySelector('.summary-line.total span:last-child').textContent = formatPrice(data.cart_total * 1.08);
}

function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2);
}

// Auto-update quantity changes after a delay
let quantityTimeout;
document.addEventListener('input', function(e) {
    if (e.target.id && e.target.id.startsWith('qty_')) {
        clearTimeout(quantityTimeout);
        quantityTimeout = setTimeout(() => {
            const productId = e.target.id.replace('qty_', '');
            updateQuantity(productId);
        }, 1000);
    }
});

// Checkbox functionality for selective checkout
function toggleAllItems() {
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('.item-select-checkbox');
    
    if (selectAllCheckbox) {
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        
        updateSelectedItems();
    }
}

function updateSelectedItems() {
    const itemCheckboxes = document.querySelectorAll('.item-select-checkbox');
    const selectedCount = document.querySelectorAll('.item-select-checkbox:checked').length;
    const selectAllCheckbox = document.getElementById('select-all');
    
    // Check if select all checkbox exists before trying to update it
    if (selectAllCheckbox) {
        // Update select all checkbox state
        if (selectedCount === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (selectedCount === itemCheckboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
            selectAllCheckbox.checked = false;
        }
    }
    
    // Calculate totals for selected items only
    let subtotal = 0;
    let selectedItems = 0;
    
    itemCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            const productId = checkbox.value;
            const quantityInput = document.getElementById('qty_' + productId);
            const quantity = parseInt(quantityInput.value) || 0;
            
            // Get price from the item details
            const itemElement = checkbox.closest('.cart-item');
            const priceElement = itemElement.querySelector('.unit-price');
            if (priceElement) {
                const priceText = priceElement.textContent.replace(/[₱,]/g, '');
                const price = parseFloat(priceText);
                subtotal += price * quantity;
                selectedItems += quantity;
            }
        }
    });
    
    // Update summary display
    document.getElementById('selected-count').textContent = selectedItems;
    document.getElementById('selected-subtotal').textContent = formatPrice(subtotal);
    
    const tax = subtotal * 0.08;
    document.getElementById('selected-tax').textContent = formatPrice(tax);
    
    const shipping = subtotal >= 50 ? 0 : 9.99;
    const shippingElement = document.getElementById('shipping-text');
    if (shipping === 0) {
        shippingElement.textContent = 'Free';
        shippingElement.className = 'free-shipping';
    } else {
        shippingElement.textContent = formatPrice(shipping);
        shippingElement.className = '';
    }
    
    const total = subtotal + tax + shipping;
    document.getElementById('selected-total').textContent = formatPrice(total);
}

function proceedToCheckout() {
    const selectedCheckboxes = document.querySelectorAll('.item-select-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select at least one item to proceed to checkout.');
        return;
    }
    
    // Collect selected items data
    const selectedItems = [];
    selectedCheckboxes.forEach(checkbox => {
        const productId = checkbox.value;
        const quantityInput = document.getElementById('qty_' + productId);
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (quantity > 0) {
            selectedItems.push({
                product_id: productId,
                quantity: quantity
            });
        }
    });
    
    if (selectedItems.length === 0) {
        alert('Please select at least one item with quantity greater than 0.');
        return;
    }
    
    // Debug logging
    console.log('Selected items to send:', selectedItems);
    console.log('JSON string:', JSON.stringify(selectedItems));
    
    // Create a form to submit selected items
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'checkout.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'selected_items';
    input.value = JSON.stringify(selectedItems);
    
    form.appendChild(input);
    document.body.appendChild(form);
    
    // Debug: Show what we're about to submit
    console.log('Form data:', input.value);
    
    form.submit();
}

function formatPrice(amount) {
    return '₱' + parseFloat(amount).toFixed(2);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedItems();
});
</script>

<?php require_once 'includes/footer.php'; ?>
