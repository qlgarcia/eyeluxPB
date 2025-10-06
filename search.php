<?php
require_once 'includes/header.php';

$page_title = 'Search Results';

$search_query = sanitizeInput($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

if (empty($search_query)) {
    redirect('products.php');
}

// Search products
$products = searchProducts($search_query, null, null, null, 'name', $per_page, $offset);

// Get total count for pagination
$db = Database::getInstance();
$total_result = $db->fetchOne(
    "SELECT COUNT(*) as total FROM products p 
     WHERE p.is_active = 1 AND (p.product_name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)",
    ["%$search_query%", "%$search_query%", "%$search_query%"]
);
$total_products = $total_result['total'];
$total_pages = ceil($total_products / $per_page);
?>

<main>
    <div class="container">
        <div class="search-results-page">
            <div class="search-header">
                <h1>Search Results</h1>
                <p>Search results for: "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                <p><?php echo $total_products; ?> product<?php echo $total_products !== 1 ? 's' : ''; ?> found</p>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                    <h2>No products found</h2>
                    <p>We couldn't find any products matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                    <div class="search-suggestions">
                        <h3>Try these suggestions:</h3>
                        <ul>
                            <li>Check your spelling</li>
                            <li>Try different keywords</li>
                            <li>Use more general terms</li>
                            <li>Browse our <a href="categories.php">categories</a></li>
                        </ul>
                    </div>
                    <a href="products.php" class="btn btn-primary">Browse All Products</a>
                </div>
            <?php else: ?>
                <div class="search-results">
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                                    <?php endif; ?>
                                </a>
                                
                                <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                    <div class="sale-badge">
                                        <?php echo calculateDiscountPercentage($product['price'], $product['sale_price']); ?>% OFF
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['is_new_arrival']): ?>
                                    <div class="new-badge">NEW</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-name">
                                    <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </a>
                                </h3>
                                
                                <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                
                                <div class="product-price">
                                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                        <span class="sale-price"><?php echo formatPrice($product['sale_price']); ?></span>
                                        <span class="original-price"><?php echo formatPrice($product['price']); ?></span>
                                    <?php else: ?>
                                        <?php echo formatPrice($product['price']); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-rating">
                                    <div class="stars">
                                        <?php
                                        $rating = $product['rating'] ?? 0;
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
                                    <span class="rating-count">(<?php echo $product['review_count']; ?>)</span>
                                </div>
                                
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>" 
                               class="pagination-btn">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>" 
                               class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?q=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>" 
                               class="pagination-btn">Next</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.search-results-page {
    padding: 20px 0;
}

.search-header {
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.search-header h1 {
    font-size: 28px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.search-header p {
    color: #666;
    margin-bottom: 5px;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.no-results h2 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.no-results p {
    color: #666;
    margin-bottom: 30px;
}

.search-suggestions {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    text-align: left;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.search-suggestions h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 16px;
}

.search-suggestions ul {
    list-style: none;
    padding: 0;
}

.search-suggestions li {
    padding: 5px 0;
    color: #666;
    position: relative;
    padding-left: 20px;
}

.search-suggestions li::before {
    content: '•';
    color: #e74c3c;
    font-weight: bold;
    position: absolute;
    left: 0;
}

.search-suggestions a {
    color: #e74c3c;
    text-decoration: none;
}

.search-suggestions a:hover {
    text-decoration: underline;
}

.search-results {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

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
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.product-image {
    position: relative;
    flex: 0 0 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
}

/* Product Card Link */
.product-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    width: 100%;
    height: 100%;
}

/* Product Info */
.product-info {
    flex: 1;
    padding: 12px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 0;
}

/* Product Actions */
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
}

.sale-badge,
.new-badge {
    position: absolute;
    top: 10px;
    right: 10px;
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

.product-info {
    padding: 20px;
}

.product-name {
    margin-bottom: 10px;
}

.product-name a {
    color: #2c3e50;
    text-decoration: none;
    font-size: 16px;
    font-weight: 600;
}

.product-name a:hover {
    color: #e74c3c;
}

.product-category {
    color: #666;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.product-price {
    margin-bottom: 15px;
}

.sale-price {
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

.product-rating {
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

.add-to-cart-btn {
    width: 100%;
    padding: 12px;
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    transition: background 0.3s;
}

.add-to-cart-btn:hover {
    background: #c0392b;
    color: white;
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

@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .search-header {
        padding: 15px;
    }
    
    .search-header h1 {
        font-size: 24px;
    }
    
    .search-results {
        padding: 20px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
