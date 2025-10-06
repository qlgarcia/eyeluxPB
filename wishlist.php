<?php
require_once 'includes/header.php';

$page_title = 'Wishlist';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php?redirect=wishlist.php');
}

$user_id = $_SESSION['user_id'];

// Handle wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    $db = Database::getInstance();
    
    switch ($action) {
        case 'add':
            // Check if already in wishlist
            $existing = $db->fetchOne("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?", 
                                     [$user_id, $product_id]);
            if (!$existing) {
                $db->insert("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)", 
                           [$user_id, $product_id]);
                setFlashMessage('success', 'Product added to wishlist!');
            } else {
                setFlashMessage('info', 'Product is already in your wishlist.');
            }
            break;
            
        case 'remove':
            $db->execute("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?", 
                        [$user_id, $product_id]);
            setFlashMessage('success', 'Product removed from wishlist.');
            break;
            
        case 'clear':
            $db->execute("DELETE FROM wishlist WHERE user_id = ?", [$user_id]);
            setFlashMessage('success', 'Wishlist cleared.');
            break;
    }
    
    // Redirect to prevent resubmission
    redirect('wishlist.php');
}

// Get wishlist items
$db = Database::getInstance();
$wishlist_items = $db->fetchAll(
    "SELECT w.*, p.*, c.category_name FROM wishlist w
     JOIN products p ON w.product_id = p.product_id
     LEFT JOIN categories c ON p.category_id = c.category_id
     WHERE w.user_id = ? AND p.is_active = 1
     ORDER BY w.added_at DESC",
    [$user_id]
);

$success_message = getFlashMessage('success');
$error_message = getFlashMessage('error');
$info_message = getFlashMessage('info');
?>

<main>
    <div class="container">
        <div class="wishlist-page">
            <div class="page-header">
                <h1>My Wishlist</h1>
                <p>Save your favorite products for later</p>
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
            
            <?php if ($info_message): ?>
                <div class="alert alert-info">
                    <?php echo $info_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($wishlist_items)): ?>
                <div class="empty-wishlist">
                    <i class="fas fa-heart" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                    <h2>Your wishlist is empty</h2>
                    <p>Start adding products you love to your wishlist.</p>
                    <a href="products.php" class="btn btn-primary">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="wishlist-header">
                    <div class="wishlist-info">
                        <h2><?php echo count($wishlist_items); ?> item<?php echo count($wishlist_items) !== 1 ? 's' : ''; ?> in your wishlist</h2>
                    </div>
                    
                    <div class="wishlist-actions">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear your wishlist?')">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-trash"></i> Clear Wishlist
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="wishlist-grid">
                    <?php foreach ($wishlist_items as $item): ?>
                    <div class="wishlist-item">
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
                            
                            <div class="item-badges">
                                <?php if ($item['sale_price'] && $item['sale_price'] < $item['price']): ?>
                                    <span class="sale-badge">
                                        <?php echo calculateDiscountPercentage($item['price'], $item['sale_price']); ?>% OFF
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($item['is_new_arrival']): ?>
                                    <span class="new-badge">NEW</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-actions-overlay">
                                <button type="button" class="btn btn-primary quick-add-btn" onclick="addToCartAjax(<?php echo $item['product_id']; ?>)">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                        
                        <div class="item-info">
                            <div class="item-category">
                                <?php echo htmlspecialchars($item['category_name']); ?>
                            </div>
                            
                            <h3 class="item-name">
                                <a href="product.php?id=<?php echo $item['product_id']; ?>">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </a>
                            </h3>
                            
                            <div class="item-price">
                                <?php if ($item['sale_price'] && $item['sale_price'] < $item['price']): ?>
                                    <span class="current-price"><?php echo formatPrice($item['sale_price']); ?></span>
                                    <span class="original-price"><?php echo formatPrice($item['price']); ?></span>
                                <?php else: ?>
                                    <span class="current-price"><?php echo formatPrice($item['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-rating">
                                <div class="stars">
                                    <?php
                                    $rating = $item['rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } elseif ($i - 0.5 <= $rating) {
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="rating-count">(<?php echo $item['review_count']; ?>)</span>
                            </div>
                            
                            <div class="item-stock">
                                <?php if ($item['stock_quantity'] > 0): ?>
                                    <span class="in-stock">
                                        <i class="fas fa-check-circle"></i> In Stock
                                    </span>
                                <?php else: ?>
                                    <span class="out-of-stock">
                                        <i class="fas fa-times-circle"></i> Out of Stock
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-added">
                                Added <?php echo date('M j, Y', strtotime($item['added_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="item-actions">
                            <a href="product.php?id=<?php echo $item['product_id']; ?>" class="btn btn-outline">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this item from wishlist?')">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <button type="submit" class="btn btn-secondary remove-btn">
                                    <i class="fas fa-heart-broken"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Wishlist Summary -->
                <div class="wishlist-summary">
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count($wishlist_items); ?></span>
                            <span class="stat-label">Items</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-number">
                                <?php 
                                $total_value = 0;
                                foreach ($wishlist_items as $item) {
                                    $price = $item['sale_price'] ? $item['sale_price'] : $item['price'];
                                    $total_value += $price;
                                }
                                echo formatPrice($total_value);
                                ?>
                            </span>
                            <span class="stat-label">Total Value</span>
                        </div>
                        
                        <div class="stat-item">
                            <span class="stat-number">
                                <?php 
                                $in_stock_count = 0;
                                foreach ($wishlist_items as $item) {
                                    if ($item['stock_quantity'] > 0) $in_stock_count++;
                                }
                                echo $in_stock_count;
                                ?>
                            </span>
                            <span class="stat-label">In Stock</span>
                        </div>
                    </div>
                    
                    <div class="summary-actions">
                        <a href="products.php" class="btn btn-outline">
                            <i class="fas fa-plus"></i> Add More Items
                        </a>
                        
                        <?php if ($in_stock_count > 0): ?>
                        <button class="btn btn-primary" onclick="addAllToCart()">
                            <i class="fas fa-shopping-cart"></i> Add All to Cart
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.wishlist-page {
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

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.empty-wishlist {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.empty-wishlist h2 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.empty-wishlist p {
    color: #666;
    margin-bottom: 30px;
}

.wishlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.wishlist-info h2 {
    color: #2c3e50;
    margin: 0;
}

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.wishlist-item {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.wishlist-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.item-image {
    position: relative;
    height: 250px;
    overflow: hidden;
    background: #f8f9fa;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.wishlist-item:hover .item-image img {
    transform: scale(1.05);
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 48px;
}

.item-badges {
    position: absolute;
    top: 10px;
    left: 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.sale-badge,
.new-badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.sale-badge {
    background: #e74c3c;
}

.new-badge {
    background: #27ae60;
}

.item-actions-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.wishlist-item:hover .item-actions-overlay {
    opacity: 1;
}

.quick-add-btn {
    padding: 12px 20px;
    font-size: 14px;
}

.item-info {
    padding: 20px;
}

.item-category {
    color: #666;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.item-name {
    margin-bottom: 15px;
}

.item-name a {
    color: #2c3e50;
    text-decoration: none;
    font-size: 16px;
    font-weight: 600;
}

.item-name a:hover {
    color: #e74c3c;
}

.item-price {
    margin-bottom: 15px;
}

.current-price {
    font-size: 18px;
    font-weight: bold;
    color: #e74c3c;
}

.original-price {
    font-size: 14px;
    color: #999;
    text-decoration: line-through;
    margin-left: 10px;
}

.item-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.stars {
    color: #f39c12;
}

.rating-count {
    color: #666;
    font-size: 12px;
}

.item-stock {
    margin-bottom: 15px;
}

.in-stock {
    color: #27ae60;
    font-size: 14px;
}

.out-of-stock {
    color: #e74c3c;
    font-size: 14px;
}

.item-added {
    color: #666;
    font-size: 12px;
    margin-bottom: 20px;
}

.item-actions {
    display: flex;
    gap: 10px;
}

.item-actions .btn {
    flex: 1;
    padding: 10px;
    font-size: 14px;
}

.remove-btn {
    color: #e74c3c;
    border-color: #e74c3c;
}

.remove-btn:hover {
    background: #e74c3c;
    color: white;
}

.wishlist-summary {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #e74c3c;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
}

.summary-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

@media (max-width: 768px) {
    .wishlist-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .wishlist-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-actions {
        flex-direction: column;
    }
    
    .item-actions {
        flex-direction: column;
    }
}
</style>

<script>
function addAllToCart() {
    if (confirm('Add all in-stock items to your cart?')) {
        // This would typically make AJAX requests to add all items
        alert('Add all to cart functionality would be implemented with AJAX');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
