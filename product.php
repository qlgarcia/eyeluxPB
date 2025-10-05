<?php
require_once 'includes/header.php';

$page_title = 'Product Details';

// Get product ID
$product_id = (int)($_GET['id'] ?? 0);

if (!$product_id) {
    redirect('products.php');
}

// Get product details
$product = getProductById($product_id);

if (!$product) {
    redirect('products.php');
}

$page_title = $product['product_name'] . ' - ' . SITE_NAME;

// Message variable for any notifications (now handled by AJAX)
$message = '';

// Get related products (same category)
$db = Database::getInstance();
$related_products = $db->fetchAll(
    "SELECT * FROM products WHERE category_id = ? AND product_id != ? AND is_active = 1 ORDER BY RAND() LIMIT 4",
    [$product['category_id'], $product_id]
);

// Get product reviews
$reviews = $db->fetchAll(
    "SELECT r.*, u.first_name, u.last_name, u.profile_picture FROM reviews r 
     JOIN users u ON r.user_id = u.user_id 
     WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 10",
    [$product_id]
);

// Calculate average rating
$avg_rating = $db->fetchOne(
    "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ?",
    [$product_id]
);
?>

<main>
    <div class="container">
        <div class="product-detail-page">
            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="index.php">Home</a> > 
                <a href="products.php">Products</a> > 
                <a href="products.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> > 
                <span><?php echo htmlspecialchars($product['product_name']); ?></span>
            </nav>

            <div class="product-detail-layout">
                <!-- Product Images -->
                <div class="product-images">
                    <div class="images-layout">
                        <?php 
                        $thumbs = [];
                        if (!empty($product['additional_images'])) {
                            $decoded = json_decode($product['additional_images'], true);
                            if (is_array($decoded)) { 
                                $thumbs = $decoded; 
                            }
                        }
                        ?>
                        <?php
                        // Helper to infer view label from image URL
                        if (!function_exists('inferViewLabel')) {
                            function inferViewLabel($url) {
                                $path = parse_url($url, PHP_URL_PATH);
                                $name = strtolower(basename($path ?: ''));
                                if (preg_match('/front|main|primary|_1\b|01\b/', $name)) return 'Front';
                                if (preg_match('/side|left|right|temple|profile|lateral/', $name)) return 'Side';
                                if (preg_match('/back|rear|inside|inner/', $name)) return 'Back';
                                if (preg_match('/top|above|overhead/', $name)) return 'Top';
                                return '';
                            }
                        }

                        // Build full list with labels: use additional images if available, otherwise use main image
                        $allThumbs = [];
                        
                        if (!empty($thumbs)) {
                            // Use additional images with proper labels from admin
                            $viewLabels = ['Front', 'Side', 'Back'];
                            $labelIndex = 0;
                            foreach ($thumbs as $img) {
                                if ($img && $labelIndex < count($viewLabels)) {
                                    $allThumbs[] = ['url' => $img, 'label' => $viewLabels[$labelIndex++]]; 
                                }
                            }
                        } else {
                            // Fallback to main image if no additional images
                            if (!empty($product['image_url'])) {
                                $allThumbs[] = ['url' => $product['image_url'], 'label' => 'Front'];
                            }
                        }
                        // Only pad if we have fewer than 3 images AND no additional images were provided
                        $desiredThumbs = 3;
                        if (!empty($allThumbs) && count($allThumbs) < $desiredThumbs && empty($thumbs)) {
                            $baseUrl = $allThumbs[0]['url'];
                            $labelsPad = ['Front','Side','Back'];
                            while (count($allThumbs) < $desiredThumbs) {
                                $label = $labelsPad[count($allThumbs)] ?? '';
                                $allThumbs[] = ['url' => $baseUrl, 'label' => $label];
                            }
                        }
                        ?>
                        <div class="thumbnail-rail" id="thumbnail-rail">
                            <?php 
                            // Limit to at most 3 thumbs (main + up to 2 others) since we removed Top view
                            $limited = array_slice($allThumbs, 0, 3);
                            foreach ($limited as $idx => $image): ?>
                            <div class="thumbnail <?php echo $idx === 0 ? 'active' : ''; ?>" data-image="<?php echo htmlspecialchars($image['url']); ?>" data-label="<?php echo htmlspecialchars($image['label']); ?>" title="<?php echo htmlspecialchars($image['label'] ?: 'View'); ?>">
                                <img src="<?php echo htmlspecialchars($image['url']); ?>" alt="<?php echo htmlspecialchars($product['product_name'] . ' ' . ($image['label'] ?: 'View')); ?>">
                                <?php if (!empty($image['label'])): ?><span class="thumb-badge"><?php echo htmlspecialchars($image['label']); ?></span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="main-image zoomable" id="main-image-container">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     id="main-product-image">
                                <div class="main-view-badge" id="main-view-badge"><?php echo htmlspecialchars($allThumbs[0]['label'] ?? ''); ?></div>
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-image"></i>
                                    <p>No image available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    
                    <div class="product-rating">
                        <div class="stars">
                            <?php
                            $rating = $avg_rating['avg_rating'] ?? 0;
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
                        <span class="rating-text">
                            <?php echo number_format($rating, 1); ?> 
                            (<?php echo $avg_rating['review_count'] ?? 0; ?> reviews)
                        </span>
                    </div>

                    <div class="product-price">
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                            <span class="current-price"><?php echo formatPrice($product['sale_price']); ?></span>
                            <span class="original-price"><?php echo formatPrice($product['price']); ?></span>
                            <span class="discount">Save <?php echo calculateDiscountPercentage($product['price'], $product['sale_price']); ?>%</span>
                        <?php else: ?>
                            <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-description">
                        <h3>Description</h3>
                        <div class="frames-only-notice">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>IMPORTANT:</strong> This product includes FRAMES ONLY. No prescription lenses are included.
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <!-- About this item -->
                    <?php 
                        $rawDesc = trim((string)($product['description'] ?? ''));
                        $points = [];
                        if ($rawDesc !== '') {
                            // Split description into bullet-ish sentences with a safe pattern
                            $parts = preg_split("/(\r?\n|\.\s+|•|\-|\x{2022})/u", $rawDesc);
                            if (is_array($parts)) foreach ($parts as $part) {
                                $part = trim($part);
                                if (strlen($part) >= 8) { $points[] = $part; }
                                if (count($points) >= 7) break;
                            }
                        }
                        // Sensible defaults if description has no bulletable sentences
                        if (empty($points)) {
                            $points = [
                                'Lightweight, comfortable frame designed for all‑day wear',
                                'Durable build with everyday scratch resistance',
                                'Ready for prescription lenses (frames only, lenses not included)',
                                'Universal fit with balanced nose bridge and temple arms',
                                'Modern silhouette that pairs well with casual or formal looks'
                            ];
                        }
                    ?>
                    <section class="about-item">
                        <h3><i class="fas fa-list-ul"></i> About this item</h3>
                        <ul class="about-list">
                            <?php foreach ($points as $pt): ?>
                                <li><?php echo htmlspecialchars($pt); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>

                    <!-- Product Specifications -->
                    <?php if ($product['specifications']): ?>
                        <?php $specs = json_decode($product['specifications'], true); ?>
                        <?php if (is_array($specs) && !empty($specs)): ?>
                        <div class="product-specifications">
                            <h3>Specifications</h3>
                            <ul>
                                <?php foreach ($specs as $key => $value): ?>
                                <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Product Details -->
                    <div class="product-details">
                        <h3>Product Details</h3>
                        <ul>
                            <?php if ($product['brand']): ?>
                                <li><strong>Brand:</strong> <?php echo htmlspecialchars($product['brand']); ?></li>
                            <?php endif; ?>
                            <?php if ($product['model']): ?>
                                <li><strong>Model:</strong> <?php echo htmlspecialchars($product['model']); ?></li>
                            <?php endif; ?>
                            <?php if ($product['color']): ?>
                                <li><strong>Color:</strong> <?php echo htmlspecialchars($product['color']); ?></li>
                            <?php endif; ?>
                            <?php if ($product['size']): ?>
                                <li><strong>Size:</strong> <?php echo htmlspecialchars($product['size']); ?></li>
                            <?php endif; ?>
                            <?php if ($product['material']): ?>
                                <li><strong>Material:</strong> <?php echo htmlspecialchars($product['material']); ?></li>
                            <?php endif; ?>
                            <?php if ($product['gender']): ?>
                                <li><strong>Gender:</strong> <?php echo ucfirst($product['gender']); ?></li>
                            <?php endif; ?>
                            <li><strong>SKU:</strong> <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></li>
                            <li><strong>Stock:</strong> <?php echo $product['stock_quantity']; ?> available</li>
                        </ul>
                    </div>

                    <!-- Add to Cart Form -->
                    <?php if ($product['stock_quantity'] > 0): ?>
                        <?php if (isLoggedIn()): ?>
                        <div class="add-to-cart-form">
                            <div class="quantity-selector">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-controls">
                                    <button type="button" onclick="decreaseQuantity()">-</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                    <button type="button" onclick="increaseQuantity()">+</button>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary add-to-cart-btn" onclick="addToCartAjax(<?php echo $product_id; ?>, document.getElementById('quantity').value)">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            
                            <button type="button" class="btn btn-outline wishlist-btn" onclick="addToWishlistAjax(<?php echo $product_id; ?>)">
                                <i class="fas fa-heart"></i> Add to Wishlist
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="login-required">
                            <div class="login-prompt">
                                <i class="fas fa-lock"></i>
                                <h3>Login Required</h3>
                                <p>Please login to add items to your cart and access all features.</p>
                                <div class="login-actions">
                                    <a href="login.php?redirect=product.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Login
                                    </a>
                                    <a href="register.php" class="btn btn-outline">
                                        <i class="fas fa-user-plus"></i> Create Account
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                    <div class="out-of-stock">
                        <p>This product is currently out of stock.</p>
                        <button class="btn btn-secondary" disabled>Out of Stock</button>
                    </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                        <?php echo $message; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Reviews -->
            <?php if (!empty($reviews)): ?>
            <section class="product-reviews">
                <h2>Customer Reviews</h2>
                
                <div class="reviews-summary">
                    <div class="average-rating">
                        <span class="rating-number"><?php echo number_format($rating, 1); ?></span>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i - 0.5 <= $rating): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <span class="review-count"><?php echo $avg_rating['review_count']; ?> reviews</span>
                    </div>
                </div>

                <div class="reviews-list">
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">
                                    <?php if (!empty($review['profile_picture']) && file_exists($review['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($review['profile_picture']); ?>" alt="<?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>" class="review-avatar">
                                    <?php else: ?>
                                        <div class="review-avatar-default">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="reviewer-details">
                                    <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $review['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <span class="review-date"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                        </div>
                        
                        <?php if ($review['title']): ?>
                            <h4 class="review-title"><?php echo htmlspecialchars($review['title']); ?></h4>
                        <?php endif; ?>
                        
                        <?php if ($review['comment']): ?>
                            <p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
            <section class="related-products">
                <h2>Related Products</h2>
                <div class="product-grid">
                    <?php foreach ($related_products as $related): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="product.php?id=<?php echo $related['product_id']; ?>">
                                <?php if ($related['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($related['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($related['product_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                                <?php endif; ?>
                            </a>
                        </div>
                        
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="product.php?id=<?php echo $related['product_id']; ?>">
                                    <?php echo htmlspecialchars($related['product_name']); ?>
                                </a>
                            </h3>
                            
                            <div class="product-price">
                                <?php if ($related['sale_price'] && $related['sale_price'] < $related['price']): ?>
                                    <span class="sale-price"><?php echo formatPrice($related['sale_price']); ?></span>
                                    <span class="original-price"><?php echo formatPrice($related['price']); ?></span>
                                <?php else: ?>
                                    <?php echo formatPrice($related['price']); ?>
                                <?php endif; ?>
                            </div>
                            
                            <a href="product.php?id=<?php echo $related['product_id']; ?>" 
                               class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
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

.product-detail-page {
    padding: 20px 0;
    background: var(--bg-primary);
    min-height: 100vh;
}

.breadcrumb {
    margin-bottom: 20px;
    font-size: 14px;
    color: var(--text-secondary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.breadcrumb a {
    color: var(--sage);
    text-decoration: none;
    transition: all 0.3s ease;
}

.breadcrumb a:hover {
    color: var(--terracotta);
    text-decoration: underline;
}

.product-detail-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 60px;
}

.product-images {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 25px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

/* Amazon-like vertical thumbnail rail */
.images-layout { display: grid; grid-template-columns: 95px 1fr; gap: 16px; align-items: start; }
.thumbnail-rail { display: flex; flex-direction: column; gap: 10px; max-height: none; overflow: visible; padding-right: 0; }
/* Ensure no scrollbar appears in any browser */
.thumbnail-rail::-webkit-scrollbar { display: none; }
.thumbnail-rail { -ms-overflow-style: none; scrollbar-width: none; }
.thumbnail-rail .thumbnail { position: relative; width: 90px; height: 70px; border:2px solid var(--border-light); border-radius:12px; overflow:hidden; cursor:pointer; transition: all .3s ease; background:var(--bg-secondary); display:flex; align-items:center; justify-content:center; }
.thumbnail-rail .thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.thumbnail-rail .thumbnail:hover { border-color: var(--sage); transform: translateY(-2px); box-shadow: var(--shadow-subtle); }
.thumbnail-rail .thumbnail.active { border-color: var(--sage); box-shadow: 0 0 0 3px rgba(156,175,136,0.15); }
.thumb-badge { position:absolute; bottom:4px; left:4px; background:rgba(0,0,0,0.55); color:#fff; font-size:10px; padding:2px 6px; border-radius:10px; backdrop-filter: blur(2px); }

.main-image { position: relative; width: 100%; min-height: 150px; display:flex; align-items:center; justify-content:center; background:var(--bg-secondary); border:1px solid var(--border-light); border-radius:15px; overflow:hidden; }
.main-image img { max-width: 100%; max-height: 300px; object-fit: contain; transition: transform .3s ease; }
.zoomable:hover img { transform: scale(1.04); }
.main-view-badge { position:absolute; top:10px; left:10px; background:var(--sage); color:#fff; font-size:12px; padding:6px 12px; border-radius:15px; letter-spacing:.2px; font-weight:500; }
.thumb-badge { pointer-events: none; }

.main-image .no-image {
    height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #ccc;
}

.main-image .no-image i {
    font-size: 48px;
    margin-bottom: 10px;
}

.product-info {
    background: var(--bg-primary);
    border-radius: 20px;
    padding: 35px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.product-title {
    font-size: 28px;
    color: var(--text-primary);
    margin-bottom: 15px;
    font-weight: 400;
    letter-spacing: -0.02em;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.product-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.rating-text {
    color: var(--text-secondary);
    font-size: 14px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.product-price {
    margin-bottom: 30px;
}

.current-price {
    font-size: 32px;
    font-weight: 400;
    color: var(--sage);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.original-price {
    font-size: 20px;
    color: var(--text-muted);
    text-decoration: line-through;
    margin-left: 15px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.discount {
    background: var(--terracotta);
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-left: 15px;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.product-description,
.product-specifications,
.product-details {
    margin-bottom: 25px;
}

.frames-only-notice {
    background: var(--bg-accent);
    color: var(--text-primary);
    padding: 18px;
    border-radius: 12px;
    border: 1px solid var(--border-light);
    margin-bottom: 20px;
    font-size: 14px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.frames-only-notice i {
    color: var(--sage);
    margin-right: 8px;
}

.product-description h3,
.product-specifications h3,
.product-details h3 {
    font-size: 18px;
    color: var(--text-primary);
    margin-bottom: 15px;
    font-weight: 400;
    letter-spacing: 0.3px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.product-specifications ul,
.product-details ul {
    list-style: none;
    padding: 0;
}

.product-specifications li,
.product-details li {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.add-to-cart-form {
    margin-top: 30px;
}

.quantity-selector {
    margin-bottom: 20px;
}

.quantity-selector label {
    display: block;
    margin-bottom: 10px;
    font-weight: 500;
    color: var(--text-secondary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.quantity-controls button {
    width: 40px;
    height: 40px;
    border: 1px solid var(--border-light);
    background: var(--bg-secondary);
    border-radius: 8px;
    cursor: pointer;
    font-size: 18px;
    color: var(--sage);
    transition: all 0.3s ease;
}

.quantity-controls button:hover {
    background: var(--sage);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.quantity-controls input {
    width: 80px;
    height: 40px;
    text-align: center;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    transition: all 0.3s ease;
}

.quantity-controls input:focus {
    outline: none;
    border-color: var(--sage);
    box-shadow: 0 0 0 3px rgba(156, 175, 136, 0.1);
}

.add-to-cart-btn {
    width: 100%;
    margin-bottom: 15px;
    padding: 15px;
    font-size: 16px;
}

.wishlist-btn {
    width: 100%;
    padding: 15px;
    font-size: 16px;
}

.out-of-stock {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 5px;
}

.login-required {
    margin-top: 30px;
}

.login-prompt {
    text-align: center;
    padding: 40px 20px;
    background: var(--bg-secondary);
    border-radius: 20px;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-subtle);
}

.login-prompt i {
    font-size: 48px;
    color: var(--sage);
    margin-bottom: 20px;
}

.login-prompt h3 {
    color: var(--text-primary);
    margin-bottom: 15px;
    font-size: 24px;
    font-weight: 400;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.login-prompt p {
    color: var(--text-secondary);
    margin-bottom: 30px;
    font-size: 16px;
}

.login-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.login-actions .btn {
    min-width: 150px;
    padding: 12px 20px;
    font-size: 16px;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    border-radius: 8px;
    transition: all 0.3s ease;
}

/* Button Styles for Product Detail Page */
.btn-primary {
    background: var(--sage);
    color: white;
    border: 1px solid var(--sage);
    transition: all 0.3s ease;
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
}

.btn-outline:hover {
    background: var(--sage);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.message {
    padding: 15px;
    border-radius: 12px;
    margin-top: 20px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.message.success {
    background: var(--bg-accent);
    color: var(--text-primary);
    border: 1px solid var(--sage);
}

.message.error {
    background: rgba(193, 123, 92, 0.1);
    color: var(--text-primary);
    border: 1px solid var(--terracotta);
}

.product-reviews,
.related-products {
    margin-top: 60px;
}

.product-reviews h2,
.related-products h2 {
    font-size: 24px;
    color: var(--text-primary);
    margin-bottom: 30px;
    font-weight: 400;
    letter-spacing: -0.02em;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.reviews-summary {
    background: var(--bg-primary);
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
    margin-bottom: 30px;
}

.average-rating {
    display: flex;
    align-items: center;
    gap: 15px;
}

.rating-number {
    font-size: 48px;
    font-weight: 400;
    color: var(--sage);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.review-count {
    color: var(--text-secondary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.review-item {
    background: var(--bg-primary);
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.reviewer-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.reviewer-avatar {
    flex-shrink: 0;
}

.review-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--sage);
}

.review-avatar-default {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--sage), var(--terracotta));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    border: 2px solid var(--sage);
}

.review-avatar-default i {
    font-size: 18px;
}

.reviewer-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.review-rating {
    color: #f39c12;
}

.review-date {
    color: #666;
    font-size: 14px;
}

.review-title {
    font-size: 16px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.review-comment {
    color: #666;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .product-detail-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .product-info {
        padding: 20px;
    }
    
    .product-title {
        font-size: 24px;
    }
    
    .current-price {
        font-size: 28px;
    }
    
    .thumbnail-images {
        flex-wrap: wrap;
    }
}
</style>

<script>
function changeMainImage(imageSrc) {
    document.getElementById('main-product-image').src = imageSrc;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.classList.remove('active');
    });
    if (event && event.target) {
        const el = event.target.closest('.thumbnail');
        if (el) el.classList.add('active');
    } else {
        // fallback: set active by data-image
        const match = document.querySelector(`.thumbnail[data-image="${imageSrc}"]`);
        if (match) match.classList.add('active');
    }
}

// Hover-to-switch behavior like Amazon thumb rail
document.addEventListener('DOMContentLoaded', function() {
    const rail = document.getElementById('thumbnail-rail');
    const mainImg = document.getElementById('main-product-image');
    const viewBadge = document.getElementById('main-view-badge');
    if (!rail || !mainImg) return;

    rail.querySelectorAll('.thumbnail').forEach(thumb => {
        const imgUrl = thumb.getAttribute('data-image');
        const label = thumb.getAttribute('data-label') || '';
        function activate() {
            if (!imgUrl) return;
            mainImg.src = imgUrl;
            rail.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
            if (viewBadge) { viewBadge.textContent = label; viewBadge.style.display = label ? 'block' : 'none'; }
        }
        thumb.addEventListener('mouseenter', activate);
        thumb.addEventListener('click', activate);
    });
});

function increaseQuantity() {
    const input = document.getElementById('quantity');
    const max = parseInt(input.getAttribute('max'));
    const current = parseInt(input.value);
    if (current < max) {
        input.value = current + 1;
        // Add visual feedback
        input.style.transform = 'scale(1.05)';
        setTimeout(() => input.style.transform = 'scale(1)', 200);
    }
}

function decreaseQuantity() {
    const input = document.getElementById('quantity');
    const current = parseInt(input.value);
    if (current > 1) {
        input.value = current - 1;
        // Add visual feedback
        input.style.transform = 'scale(1.05)';
        setTimeout(() => input.style.transform = 'scale(1)', 200);
    }
}

// Wishlist functionality is now handled by addToWishlistAjax() in header.php
</script>

<?php require_once 'includes/footer.php'; ?>
