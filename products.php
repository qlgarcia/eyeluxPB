<?php
require_once 'includes/header.php';

$page_title = 'Products';

// Get filter parameters
$search_query = sanitizeInput($_GET['q'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$min_price = !empty($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$sort = sanitizeInput($_GET['sort'] ?? 'name');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get all categories for filter
$categories = getAllCategories();

// Get products based on filters
$products = searchProducts($search_query, $category_id, $min_price, $max_price, $sort, $per_page, $offset);

// Get total count for pagination
$db = Database::getInstance();
$count_sql = "SELECT COUNT(*) as total FROM products p WHERE p.is_active = 1";
$count_params = [];

if (!empty($search_query)) {
    $count_sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $search_term = "%$search_query%";
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

if ($category_id) {
    $count_sql .= " AND p.category_id = ?";
    $count_params[] = $category_id;
}

if ($min_price !== null) {
    $count_sql .= " AND p.price >= ?";
    $count_params[] = $min_price;
}

if ($max_price !== null) {
    $count_sql .= " AND p.price <= ?";
    $count_params[] = $max_price;
}

$total_result = $db->fetchOne($count_sql, $count_params);
$total_products = $total_result['total'];
$total_pages = ceil($total_products / $per_page);
?>

<main>
    <div class="container">
        <div class="products-page">
            <div class="products-layout">
                <!-- Sidebar Filters -->
                <aside class="filters-sidebar">
                    <div class="filters-section">
                        <h3>Filters</h3>
                        
                        <!-- Search -->
                        <div class="filter-group">
                            <h4>Search</h4>
                            <form method="GET" action="products.php" class="search-form">
                                <input type="text" name="q" placeholder="Search products..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <?php if ($category_id): ?>
                                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                <?php endif; ?>
                                <button type="submit"><i class="fas fa-search"></i></button>
                            </form>
                        </div>

                        <!-- Categories -->
                        <div class="filter-group">
                            <h4>Categories</h4>
                            <ul class="filter-list">
                                <li>
                                    <a href="products.php<?php echo $search_query ? '?q=' . urlencode($search_query) : ''; ?>" 
                                       class="<?php echo !$category_id ? 'active' : ''; ?>">
                                        All Categories
                                    </a>
                                </li>
                                <?php foreach ($categories as $category): ?>
                                <li>
                                    <a href="products.php?category=<?php echo $category['category_id']; ?><?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" 
                                       class="<?php echo $category_id == $category['category_id'] ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Price Range -->
                        <div class="filter-group">
                            <h4>Price Range</h4>
                            <form method="GET" action="products.php" class="price-form" id="price-form">
                                <div class="price-inputs">
                                    <input type="number" name="min_price" placeholder="Min (₱)" 
                                           value="<?php echo $min_price; ?>" min="0" step="0.01" 
                                           id="min-price" onchange="validatePriceRange()">
                                    <span>to</span>
                                    <input type="number" name="max_price" placeholder="Max (₱)" 
                                           value="<?php echo $max_price; ?>" min="0" step="0.01" 
                                           id="max-price" onchange="validatePriceRange()">
                                </div>
                                <div class="price-error" id="price-error" style="display: none; color: #e74c3c; font-size: 12px; margin-bottom: 10px;"></div>
                                <?php if ($search_query): ?>
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                                <?php endif; ?>
                                <?php if ($category_id): ?>
                                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-outline" id="price-submit">Apply</button>
                                <?php if ($min_price !== null || $max_price !== null): ?>
                                    <button type="button" class="btn btn-secondary" onclick="clearPriceRange()" style="margin-top: 8px; width: 100%;">Clear Price</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Clear Filters -->
                        <div class="filter-group">
                            <a href="products.php" class="btn btn-secondary">Clear All Filters</a>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="products-main">
                    <!-- Sort and View Options -->
                    <div class="products-toolbar">
                        <div class="sort-options">
                            <label for="sort">Sort by:</label>
                            <form method="GET" action="products.php" id="sort-form">
                                <select name="sort" id="sort" onchange="this.form.submit()">
                                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                </select>
                                
                                <!-- Preserve other filters -->
                                <?php if ($search_query): ?>
                                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                                <?php endif; ?>
                                <?php if ($category_id): ?>
                                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                <?php endif; ?>
                                <?php if ($min_price !== null): ?>
                                    <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                                <?php endif; ?>
                                <?php if ($max_price !== null): ?>
                                    <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <?php if (empty($products)): ?>
                        <div class="no-products">
                            <div class="no-products-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3>No products found</h3>
                            <p>We couldn't find any products matching your criteria.</p>
                            
                            <?php if ($search_query || $category_id || $min_price !== null || $max_price !== null): ?>
                                <div class="no-products-suggestions">
                                    <p><strong>Try these suggestions:</strong></p>
                                    <ul>
                                        <?php if ($search_query): ?>
                                            <li>Check your spelling</li>
                                            <li>Try different keywords</li>
                                        <?php endif; ?>
                                        <?php if ($min_price !== null || $max_price !== null): ?>
                                            <li>Adjust your price range</li>
                                        <?php endif; ?>
                                        <li>Browse all categories</li>
                                        <li>Remove some filters</li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="no-products-actions">
                                <a href="products.php" class="btn btn-primary">View All Products</a>
                                <a href="categories.php" class="btn btn-outline">Browse Categories</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="product-grid">
                            <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="product-image">
                                    <?php if ($product['image_url'] && !empty($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="no-image" style="display: none;">
                                            <i class="fas fa-image"></i>
                                            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                        <div class="sale-badge">
                                            <?php 
                                            $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                                            echo $discount;
                                            ?>% OFF
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($product['is_new_arrival'])): ?>
                                        <div class="new-badge">NEW</div>
                                    <?php endif; ?>

                                    <?php if ((int)$product['stock_quantity'] <= 0): ?>
                                        <div class="out-of-stock-badge">OUT OF STOCK</div>
                                    <?php endif; ?>
                                </a>

                                <div class="product-info">
                                    <h3 class="product-name">
                                        <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </a>
                                    </h3>

                                    <div class="product-price">
                                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                            <span class="sale-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                            <span class="original-price"><?php echo formatPrice($product['price']); ?></span>
                                        <?php else: ?>
                                            <?php echo formatPrice($product['price']); ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="product-actions">
                                        <?php if ((int)$product['stock_quantity'] > 0): ?>
                                            <?php if (isLoggedIn()): ?>
                                                <button type="button" class="btn btn-primary add-to-cart-btn" onclick="addToCartAjax(<?php echo $product['product_id']; ?>)">
                                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-warning" onclick="showLoginPrompt()">
                                                    <i class="fas fa-lock"></i> Login to Add
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary" disabled>
                                                <i class="fas fa-times"></i> Out of Stock
                                            </button>
                                        <?php endif; ?>

                                        <a class="btn btn-outline" href="product.php?id=<?php echo $product['product_id']; ?>">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Review Notifications -->
                        <?php if (isLoggedIn()): ?>
                            <?php 
                            $user_id = $_SESSION['user_id'];
                            try {
                                $review_notifications = getReviewNotifications($user_id, true);
                            } catch (Exception $e) {
                                $review_notifications = [];
                            }
                            ?>
                            <?php if (!empty($review_notifications)): ?>
                            <div class="review-notifications-section">
                                <h3><i class="fas fa-star"></i> Review Your Recent Purchases</h3>
                                <div class="review-notifications">
                                    <?php foreach ($review_notifications as $notification): ?>
                                    <div class="review-notification-card" data-notification-id="<?php echo $notification['notification_id']; ?>" 
                                         data-product-id="<?php echo $notification['product_id']; ?>" 
                                         data-order-id="<?php echo $notification['order_id']; ?>">
                                        <div class="notification-content">
                                            <div class="product-info">
                                                <?php if ($notification['image_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($notification['image_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($notification['product_name']); ?>" 
                                                         class="product-thumb">
                                                <?php else: ?>
                                                    <div class="product-thumb-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="product-details">
                                                    <h4><?php echo htmlspecialchars($notification['product_name']); ?></h4>
                                                    <p>Order #<?php echo htmlspecialchars($notification['order_number']); ?></p>
                                                    <p>Delivered on <?php echo date('M j, Y', strtotime($notification['order_date'])); ?></p>
                                                </div>
                                            </div>
                                            <button class="btn btn-primary review-btn" onclick="openReviewModal(<?php echo $notification['notification_id']; ?>, <?php echo $notification['product_id']; ?>, <?php echo $notification['order_id']; ?>, '<?php echo htmlspecialchars($notification['product_name']); ?>')" type="button">
                                                <i class="fas fa-star"></i> Write Review
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>


                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="pagination-btn">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="pagination-btn">Next</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Review Modal -->
<div id="reviewModal" class="review-modal">
    <div class="review-modal-content">
        <div class="review-modal-header">
            <h3><i class="fas fa-star"></i> Write a Review</h3>
            <span class="review-close" onclick="closeReviewModal()">&times;</span>
        </div>
        <div class="review-modal-body">
            <div class="review-product-name">
                <i class="fas fa-glasses"></i>
                <span id="review-product-name">Product Name</span>
            </div>
            
            <div class="rating-section">
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
            
            <div class="review-form-group">
                <label for="review-title">
                    <i class="fas fa-heading"></i> Review Title
                </label>
                <input type="text" id="review-title" placeholder="Give your review a catchy title..." required>
            </div>
            
            <div class="review-form-group">
                <label for="review-comment">
                    <i class="fas fa-comment"></i> Your Review
                </label>
                <textarea id="review-comment" placeholder="Share your experience with this product. What did you like? Any suggestions for improvement?" required></textarea>
                <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">Help other customers by sharing your honest experience</small>
            </div>
            
            <button class="review-submit-btn" id="review-submit-btn" onclick="submitReview()">
                <i class="fas fa-paper-plane"></i> Submit Review
            </button>
        </div>
    </div>
</div>

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

* {
    box-sizing: border-box !important;
}

/* Minimalist Clean Background */
body {
    background: var(--bg-primary);
    min-height: 100vh;
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    line-height: 1.6;
    letter-spacing: -0.01em;
}

/* Clean Main Container */
main {
    background: transparent;
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
}

.product-grid {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 20px !important;
    width: 100% !important;
    margin: 20px 0 !important;
    padding: 0 !important;
    background: transparent !important;
}

.product-card-link {
    text-decoration: none !important;
    color: inherit !important;
    display: block !important;
    width: 100% !important;
    height: auto !important;
}

.product-card {
    width: 100% !important;
    height: 380px !important;
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(15px) !important;
    border-radius: 20px !important;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    transition: transform 0.3s ease, box-shadow 0.3s ease !important;
}

.product-card:hover {
    transform: translateY(-8px) scale(1.02) !important;
    box-shadow: 0 15px 40px rgba(0,0,0,0.2) !important;
}

.product-image {
    height: 200px !important;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    overflow: hidden !important;
    border-radius: 20px 20px 0 0 !important;
}

.product-image img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

.product-info {
    flex: 1 !important;
    padding: 15px !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
}

.product-actions {
    display: flex !important;
    gap: 10px !important;
    margin-top: auto !important;
}

.product-actions .btn {
    flex: 1 !important;
    padding: 10px 15px !important;
    font-size: 14px !important;
    text-align: center !important;
    border-radius: 5px !important;
    text-decoration: none !important;
    cursor: pointer !important;
    border: none !important;
}

.btn-primary {
    background: var(--sage) !important;
    color: white !important;
    border: 1px solid var(--sage) !important;
    transition: all 0.3s ease !important;
}

.btn-primary:hover {
    background: var(--terracotta) !important;
    border-color: var(--terracotta) !important;
    transform: translateY(-2px) !important;
    box-shadow: var(--shadow-subtle) !important;
}

.btn-secondary {
    background: var(--khaki-dark) !important;
    color: white !important;
    border: 1px solid var(--khaki-dark) !important;
    transition: all 0.3s ease !important;
}

.btn-secondary:hover {
    background: var(--khaki-deep) !important;
    border-color: var(--khaki-deep) !important;
    transform: translateY(-2px) !important;
    box-shadow: var(--shadow-subtle) !important;
}


/* RESPONSIVE BREAKPOINTS */
@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 480px) {
    .product-grid {
        grid-template-columns: 1fr !important;
    }
}

.products-page {
    padding: 0;
}


.products-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 20px;
    align-items: start;
    max-width: 100%;
    overflow-x: hidden;
}

.filters-sidebar {
    background: var(--bg-primary);
    backdrop-filter: blur(10px);
    padding: 30px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
    height: fit-content;
    position: sticky;
    top: 20px;
    width: 100%;
    max-width: 300px;
    color: var(--text-primary);
    margin-right: 20px;
}

.filters-section h3 {
    margin-bottom: 25px;
    color: var(--text-primary);
    font-size: 20px;
    font-weight: 400;
    border-bottom: 2px solid var(--sage);
    padding-bottom: 12px;
    letter-spacing: 0.5px;
}

.filters-section h3::after {
    content: '';
    position: absolute;
    bottom: -3px;
    left: 0;
    width: 0;
    height: 3px;
    background: var(--gradient-accent);
    transition: width 0.5s ease;
}

.filters-section:hover h3::after {
    width: 100%;
}

.filter-group {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-light);
}

.filter-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.filter-group h4 {
    margin-bottom: 15px;
    color: var(--text-secondary);
    font-size: 14px;
    text-transform: uppercase;
    font-weight: 500;
    letter-spacing: 0.5px;
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.search-form input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.search-form input:focus {
    outline: none;
    border-color: var(--sage);
    box-shadow: 0 0 0 3px rgba(156, 175, 136, 0.1);
    background: var(--bg-primary);
}

.search-form button {
    background: var(--sage);
    color: white;
    border: none;
    padding: 12px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.search-form button:hover {
    background: var(--terracotta);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.filter-list {
    list-style: none;
    padding: 0;
}

.filter-list li {
    margin-bottom: 8px;
}

.filter-list a {
    color: var(--text-secondary);
    text-decoration: none;
    padding: 10px 16px;
    border-radius: 8px;
    display: block;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 400;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.filter-list a:hover {
    color: var(--sage);
    background: var(--bg-accent);
    transform: translateX(4px);
}

.filter-list a.active {
    color: var(--sage);
    background: var(--bg-accent);
    font-weight: 500;
    border-left: 3px solid var(--sage);
}

.price-inputs {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
}

.price-inputs input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.price-inputs input:focus {
    outline: none;
    border-color: #e74c3c;
}

.price-inputs span {
    color: #666;
    font-weight: 500;
    text-align: center;
    font-size: 12px;
}

.products-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px 25px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.sort-options {
    display: flex;
    align-items: center;
    gap: 15px;
}

.sort-options label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.sort-options select {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    transition: border-color 0.3s ease;
}

.sort-options select:focus {
    outline: none;
    border-color: #e74c3c;
}

.no-products {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-width: 600px;
    margin: 0 auto;
}

.no-products-icon {
    margin-bottom: 25px;
}

.no-products-icon i {
    font-size: 64px;
    color: #e0e0e0;
}

.no-products h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 24px;
    font-weight: 600;
}

.no-products p {
    color: #666;
    margin-bottom: 25px;
    font-size: 16px;
    line-height: 1.5;
}

.no-products-suggestions {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    text-align: left;
    display: inline-block;
}

.no-products-suggestions p {
    margin-bottom: 15px;
    color: #2c3e50;
    font-weight: 600;
}

.no-products-suggestions ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.no-products-suggestions li {
    padding: 5px 0;
    color: #666;
    position: relative;
    padding-left: 20px;
}

.no-products-suggestions li:before {
    content: "•";
    color: #e74c3c;
    font-weight: bold;
    position: absolute;
    left: 0;
}

.no-products-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.no-products-actions .btn {
    min-width: 150px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}

.pagination-btn {
    padding: 10px 15px;
    background: white;
    color: #333;
    text-decoration: none;
    border-radius: 5px;
    border: 1px solid #ddd;
    transition: all 0.3s;
}

.pagination-btn:hover,
.pagination-btn.active {
    background: #e74c3c;
    color: white;
    border-color: #e74c3c;
}

/* Product Grid */
.product-grid {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 20px !important;
    margin-bottom: 40px !important;
    width: 100% !important;
    max-width: 100% !important;
    align-items: start !important;
    justify-items: stretch !important;
    padding: 0 !important;
    clear: both !important;
}

.product-card {
    height: 380px !important;
    background: var(--bg-primary) !important;
    border-radius: 15px !important;
    overflow: hidden !important;
    box-shadow: var(--shadow-subtle) !important;
    border: 1px solid var(--border-light) !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(139, 115, 85, 0.12);
    border-color: var(--sage);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

/* FINAL OVERRIDE - make grid items fill their cells */
.product-grid { justify-items: stretch !important; align-items: stretch !important; }
.product-card-link {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    display: flex !important;
    height: 100% !important;
    flex-direction: column !important;
    background: var(--bg-primary) !important;
    border-radius: 15px !important;
    overflow: hidden !important;
    border: 1px solid var(--border-light) !important;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
    text-decoration: none !important;
}
.product-card-link:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important; }
.product-card { width: 100% !important; max-width: 100% !important; height: 100% !important; }

.product-card-link {
    text-decoration: none !important;
    color: inherit !important;
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

/* Button Styles */
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

.btn-warning {
    background: var(--khaki-dark);
    color: white;
    border: 1px solid var(--khaki-dark);
    transition: all 0.3s ease;
}

.btn-warning:hover {
    background: var(--khaki-deep);
    border-color: var(--khaki-deep);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}
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

.btn-secondary {
    background: var(--khaki-dark);
    color: white;
    border: 1px solid var(--khaki-dark);
    transition: all 0.3s ease;
    cursor: not-allowed;
}

/* Product Name */
.product-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Product Category */
.product-category {
    font-size: 11px;
    color: #666;
    margin-bottom: 4px;
    text-transform: uppercase;
    font-weight: 500;
}

/* Product Price */
.product-price {
    font-size: 16px;
    font-weight: bold;
    color: #e74c3c;
    margin-bottom: 4px;
}

/* Frames Only Badge */
.frames-only-badge {
    font-size: 10px;
    background: #fff3cd;
    color: #856404;
    padding: 2px 6px;
    border-radius: 3px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Product Rating */
.product-rating {
    font-size: 11px;
    color: #666;
    margin-bottom: 4px;
}

/* Product Sales */
.product-sales {
    font-size: 10px;
    color: #666;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.no-image {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #ccc;
    text-align: center;
    padding: 20px;
}

.no-image i {
    font-size: 48px;
    margin-bottom: 10px;
}

.no-image span {
    font-size: 14px;
    font-weight: 500;
    color: #666;
}

/* Badges */
.sale-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #e74c3c;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    z-index: 2;
}

.new-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #27ae60;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    z-index: 2;
}

.out-of-stock-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #dc3545;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    z-index: 2;
}

/* Product Info */
.product-info {
    padding: 20px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.product-name {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
    line-height: 1.3;
}

.product-name a {
    color: inherit;
    text-decoration: none;
}

.product-name a:hover {
    color: #e74c3c;
}

.product-category {
    color: #666;
    font-size: 14px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.frames-only-badge {
    background: #fff3cd;
    color: #856404;
    font-size: 11px;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 4px;
    margin-bottom: 12px;
    border: 1px solid #ffeaa7;
    text-align: center;
}

.frames-only-badge i {
    margin-right: 4px;
}

/* Product Price */
.product-price {
    margin-bottom: 12px;
    font-size: 18px;
    font-weight: bold;
}

.sale-price {
    color: #e74c3c;
    margin-right: 8px;
}

.original-price {
    color: #999;
    text-decoration: line-through;
    font-size: 14px;
    font-weight: normal;
}

/* Product Rating */
.product-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
}

.stars {
    color: #ffc107;
}

.stars i {
    font-size: 14px;
}

.rating-count {
    color: #666;
    font-size: 12px;
}

.product-sales {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 15px;
    color: #28a745;
    font-size: 12px;
    font-weight: 500;
}

.product-sales i {
    font-size: 14px;
}

/* Product Actions */
.product-actions {
    margin-top: auto;
    display: flex;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.product-actions .btn {
    flex: 1;
    padding: 10px 12px;
    font-size: 14px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.product-actions .btn i {
    margin-right: 5px;
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

.btn-secondary:disabled {
    background: var(--khaki-dark);
    color: white;
    border-color: var(--khaki-dark);
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .products-layout {
        grid-template-columns: 250px 1fr;
        gap: 15px;
    }
    
    .product-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .products-layout {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .filters-sidebar {
        order: 2;
        position: static;
        max-width: none;
        width: 100%;
        padding: 15px;
    }
    
    .products-main {
        order: 1;
        width: 100%;
        max-width: 100%;
    }

    .products-toolbar {
        flex-direction: column;
        gap: 15px;
        padding: 15px;
    }

    .product-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 15px !important;
        justify-items: stretch !important;
        align-items: start !important;
    }
    
    .product-card {
        max-width: 100%;
        aspect-ratio: 1;
    }
    
    .product-image {
        flex: 0 0 50%;
    }
    
    .product-actions {
        flex-direction: column;
    }
    
    .product-actions .btn {
        flex: none;
    }
}

@media (max-width: 480px) {
    .product-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .product-card {
        width: 100%;
        max-width: 100%;
        aspect-ratio: 1;
        margin: 0 auto;
    }
    
    .product-image {
        flex: 0 0 50%;
    }
    
    .filters-sidebar {
        padding: 10px;
    }
    
    .container {
        padding: 10px;
    }
}

/* Ensure products display in proper grid on larger screens */
@media (min-width: 769px) {
    .product-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 20px !important;
        justify-items: stretch !important;
        align-items: start !important;
    }
    
    .product-card {
        width: 100% !important;
        max-width: 100% !important;
        height: 380px !important;
    }
    
    .products-main {
        width: 100% !important;
        max-width: 100% !important;
    }
}

/* For very large screens, maintain 3 products per row */
@media (min-width: 1400px) {
    .product-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 25px !important;
        justify-items: stretch !important;
        align-items: start !important;
    }
}

/* Review Notifications Styles */
.review-notifications-section {
    background: rgba(255, 243, 205, 0.95);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 234, 167, 0.8);
    border-radius: 20px;
    padding: 20px;
    margin: 30px 0;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}

.review-notifications-section h3 {
    color: #856404;
    margin-bottom: 20px;
    font-size: 18px;
}

.review-notifications {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.review-notification-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(222, 226, 230, 0.8);
    border-radius: 15px;
    padding: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

.review-notification-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    transform: translateY(-4px) scale(1.01);
}

.notification-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.product-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
}

.product-thumb-placeholder {
    width: 60px;
    height: 60px;
    background: #f8f9fa;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-size: 20px;
}

.product-details h4 {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-size: 16px;
}

.product-details p {
    margin: 0;
    color: #6c757d;
    font-size: 14px;
}

.review-btn {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.3s ease;
    white-space: nowrap;
}

.review-btn:hover {
    background: #c0392b;
}

/* Review Modal Styles */
.review-modal {
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

.review-modal-content {
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    margin: 3% auto;
    padding: 0;
    border-radius: 20px;
    width: 90%;
    max-width: 550px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideInUp 0.4s ease;
    box-shadow: 
        0 25px 80px rgba(0,0,0,0.3),
        0 0 0 1px rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
}

.review-modal-header {
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

.review-modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
    pointer-events: none;
}

.review-modal-header h3 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

.review-close {
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

.review-close:hover {
    background: rgba(255,255,255,0.2);
    transform: scale(1.1);
}

.review-modal-body {
    padding: 35px;
    background: white;
}

.review-product-name {
    text-align: center;
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 25px;
    padding: 15px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    border: 1px solid #dee2e6;
}

.star-rating {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin: 30px 0;
    padding: 20px;
    background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
    border-radius: 15px;
    border: 2px solid #ffeaa7;
}

.star {
    font-size: 40px;
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

.review-form-group {
    margin-bottom: 25px;
}

.review-form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 15px;
}

.review-form-group label i {
    margin-right: 8px;
    color: #667eea;
}

.review-product-name i {
    margin-right: 10px;
    color: #667eea;
    font-size: 1.2em;
}

.review-submit-btn i {
    margin-right: 8px;
}

.review-form-group input,
.review-form-group textarea {
    width: 100%;
    padding: 15px 18px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: #f8f9fa;
    font-family: inherit;
}

.review-form-group input:focus,
.review-form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    transform: translateY(-2px);
}

.review-form-group textarea {
    height: 120px;
    resize: vertical;
    line-height: 1.5;
}

.review-submit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 18px 35px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.3);
    position: relative;
    overflow: hidden;
}

.review-submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.review-submit-btn:hover::before {
    left: 100%;
}

.review-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.4);
}

.review-submit-btn:active {
    transform: translateY(0);
}

.review-submit-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
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

/* Responsive Review Notifications */
@media (max-width: 768px) {
    .notification-content {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .product-info {
        justify-content: center;
        text-align: center;
    }
    
    .review-btn {
        width: 100%;
    }
    
    .review-modal-content {
        margin: 10% auto;
        width: 95%;
    }
    
    .star-rating {
        gap: 5px;
    }
    
    .star {
        font-size: 25px;
    }
}

/* Force Override - Critical CSS */
.product-grid,
/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: start;
    justify-items: stretch;
}

.product-card {
    height: 380px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    width: 100%;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: start;
    justify-items: center;
}

.product-card {
    height: 300px;
    aspect-ratio: 1;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

/* Visual Enhancements - final overrides */
:root { --accent: #e74c3c; --accent-2: #ff7b5f; --text-dark: #2c3e50; --muted: #9aa3aa; }

.products-page .product-card {
    background: linear-gradient(180deg, #ffffff 0%, #fcfcfc 100%) !important;
    border: 1px solid #f1f1f1 !important;
    border-radius: 14px !important;
    box-shadow: 0 10px 22px rgba(0,0,0,0.08) !important;
    transition: transform 0.25s ease, box-shadow 0.25s ease !important;
}

.products-page .product-card:hover {
    transform: translateY(-6px) scale(1.005) !important;
    box-shadow: 0 16px 34px rgba(0,0,0,0.15) !important;
}

.products-page .product-image {
    position: relative !important;
    aspect-ratio: 4 / 3 !important;
    background: #f7f8fb !important;
    border-bottom: 1px solid #f1f1f1 !important;
}

.products-page .product-image img { transition: transform 0.35s ease !important; }
.products-page .product-card:hover .product-image img { transform: scale(1.06) !important; }

.products-page .product-info { gap: 10px !important; padding: 16px !important; }

.products-page .product-name { 
    font-size: 16px !important; 
    font-weight: 700 !important; 
    color: var(--text-dark) !important; 
    margin: 0 !important; 
    line-height: 1.25 !important; 
    display: -webkit-box !important; 
    -webkit-line-clamp: 2 !important; 
    -webkit-box-orient: vertical !important; 
    overflow: hidden !important; 
}

.products-page .product-price { 
    font-size: 18px !important; 
    font-weight: 800 !important; 
    color: var(--accent) !important; 
}
.products-page .sale-price { color: var(--accent) !important; margin-right: 8px !important; }
.products-page .original-price { color: var(--muted) !important; font-size: 13px !important; text-decoration: line-through !important; }

.products-page .btn-primary { 
    background: var(--sage) !important; 
    color: #fff !important; 
    border: 1px solid var(--sage) !important; 
    transition: all 0.3s ease !important;
}
.products-page .btn-primary:hover {
    background: var(--terracotta) !important;
    border-color: var(--terracotta) !important;
    transform: translateY(-2px) !important;
    box-shadow: var(--shadow-subtle) !important;
}
.products-page .btn-warning { 
    background: var(--khaki-dark) !important; 
    color: #fff !important; 
    border: 1px solid var(--khaki-dark) !important; 
    transition: all 0.3s ease !important;
}
.products-page .btn-warning:hover {
    background: var(--khaki-deep) !important;
    border-color: var(--khaki-deep) !important;
    transform: translateY(-2px) !important;
    box-shadow: var(--shadow-subtle) !important;
}
.products-page .btn-outline { 
    background: transparent !important; 
    color: var(--sage) !important; 
    border: 2px solid var(--sage) !important; 
    transition: all 0.3s ease !important;
}
.products-page .btn-outline:hover { 
    background: var(--sage) !important; 
    color: #fff !important; 
    transform: translateY(-2px) !important;
    box-shadow: var(--shadow-subtle) !important;
}

.products-page .sale-badge { background: var(--terracotta) !important; }
.products-page .new-badge { background: var(--sage) !important; }

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: start;
    justify-items: center;
}

.product-card {
    height: 300px;
    aspect-ratio: 1;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: start;
    justify-items: center;
}

.product-card {
    height: 300px;
    aspect-ratio: 1;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: start;
    justify-items: center;
}

.product-card {
    height: 300px;
    aspect-ratio: 1;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
    align-items: start;
    justify-items: center;
}

.product-card {
    height: 300px;
    aspect-ratio: 1;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-image {
    flex: 0 0 50%;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-info {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

.product-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
    flex-wrap: wrap;
    flex-shrink: 0;
}

.product-actions .btn {
    flex: 1;
    min-width: 0;
    font-size: 11px;
    padding: 6px 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}
</style>

<script>
// SIMPLE GRID ENFORCEMENT
document.addEventListener('DOMContentLoaded', function() {
    const grids = document.querySelectorAll('.product-grid');
    grids.forEach(function(grid) {
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(3, minmax(0, 1fr))';
        grid.style.gap = '20px';
        grid.style.alignItems = 'stretch';
        grid.style.justifyItems = 'stretch';
    });
});

// Price range validation
function validatePriceRange() {
    const minPrice = document.getElementById('min-price');
    const maxPrice = document.getElementById('max-price');
    const errorDiv = document.getElementById('price-error');
    const submitBtn = document.getElementById('price-submit');
    
    const min = parseFloat(minPrice.value) || 0;
    const max = parseFloat(maxPrice.value) || 0;
    
    // Clear previous error
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    
    // Validate price range
    if (min < 0 || max < 0) {
        showPriceError('Price cannot be negative');
        return false;
    }
    
    if (min > 0 && max > 0 && min > max) {
        showPriceError('Minimum price cannot be greater than maximum price');
        return false;
    }
    
    if (min > 560000 || max > 560000) {
        showPriceError('Price cannot exceed ₱560,000');
        return false;
    }
    
    return true;
}

function showPriceError(message) {
    const errorDiv = document.getElementById('price-error');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

function clearPriceRange() {
    document.getElementById('min-price').value = '';
    document.getElementById('max-price').value = '';
    document.getElementById('price-error').style.display = 'none';
    
    // Submit form to clear price filter
    const form = document.getElementById('price-form');
    const minInput = form.querySelector('input[name="min_price"]');
    const maxInput = form.querySelector('input[name="max_price"]');
    
    minInput.removeAttribute('name');
    maxInput.removeAttribute('name');
    
    form.submit();
}

// Auto-submit price form when both fields are filled
document.addEventListener('DOMContentLoaded', function() {
    const minPrice = document.getElementById('min-price');
    const maxPrice = document.getElementById('max-price');
    
    if (minPrice && maxPrice) {
        minPrice.addEventListener('blur', function() {
            if (this.value && maxPrice.value) {
                setTimeout(() => {
                    if (validatePriceRange()) {
                        document.getElementById('price-form').submit();
                    }
                }, 1000);
            }
        });
        
        maxPrice.addEventListener('blur', function() {
            if (this.value && minPrice.value) {
                setTimeout(() => {
                    if (validatePriceRange()) {
                        document.getElementById('price-form').submit();
                    }
                }, 1000);
            }
        });
    }
});

// Review Modal Functions
let currentRating = 0;
let currentNotificationId = 0;
let currentProductId = 0;
let currentOrderId = 0;

function openReviewModal(notificationId, productId, orderId, productName) {
    console.log('Opening review modal for:', productName);
    
    currentNotificationId = notificationId;
    currentProductId = productId;
    currentOrderId = orderId;
    currentRating = 0;
    
    // Update modal content
    const productNameElement = document.getElementById('review-product-name');
    if (productNameElement) {
        productNameElement.textContent = productName;
    }
    
    const titleElement = document.getElementById('review-title');
    if (titleElement) {
        titleElement.value = '';
    }
    
    const commentElement = document.getElementById('review-comment');
    if (commentElement) {
        commentElement.value = '';
    }
    
    const ratingTextElement = document.getElementById('rating-text');
    if (ratingTextElement) {
        ratingTextElement.textContent = '';
    }
    
    // Reset stars
    const stars = document.querySelectorAll('.star');
    stars.forEach(star => star.classList.remove('active'));
    
    // Show modal
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.style.display = 'block';
        console.log('Modal should be visible now');
    } else {
        console.error('Review modal element not found!');
    }
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
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
    
    // Update rating text
    const ratingTexts = {
        1: 'Poor - Not satisfied',
        2: 'Fair - Could be better',
        3: 'Good - Met expectations',
        4: 'Very Good - Exceeded expectations',
        5: 'Excellent - Outstanding!'
    };
    
    ratingText.textContent = ratingTexts[rating] || '';
}

function submitReview() {
    if (currentRating === 0) {
        alert('Please select a rating');
        return;
    }
    
    const title = document.getElementById('review-title').value.trim();
    const comment = document.getElementById('review-comment').value.trim();
    
    if (!title) {
        alert('Please enter a review title');
        return;
    }
    
    if (!comment) {
        alert('Please enter a review comment');
        return;
    }
    
    // Disable submit button
    const submitBtn = document.getElementById('review-submit-btn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    // Submit review via AJAX
    fetch('ajax-submit-review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            notification_id: currentNotificationId,
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
            alert('Thank you for your review!');
            closeReviewModal();
            // Remove the notification card
            const notificationCard = document.querySelector(`[data-notification-id="${currentNotificationId}"]`);
            if (notificationCard) {
                notificationCard.remove();
            }
            // Refresh page to update ratings
            location.reload();
        } else {
            alert('Error submitting review: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting review. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Review';
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reviewModal');
    if (event.target === modal) {
        closeReviewModal();
    }
}

// Debug: Check if functions are loaded
console.log('Review modal functions loaded:', {
    openReviewModal: typeof openReviewModal,
    closeReviewModal: typeof closeReviewModal,
    setRating: typeof setRating,
    submitReview: typeof submitReview
});

// Debug: Check if modal element exists
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reviewModal');
    console.log('Review modal element found:', !!modal);
    if (modal) {
        console.log('Modal initial display:', modal.style.display);
    }
});

// Login prompt function
function showLoginPrompt() {
    if (typeof showNotification === 'function') {
        showNotification('warning', 'Please login to add items to your cart');
    } else {
        alert('Please login to add items to your cart');
    }
    
    // Redirect to login page after a short delay
    setTimeout(() => {
        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
    }, 2000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
