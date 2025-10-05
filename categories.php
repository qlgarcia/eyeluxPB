<?php
require_once 'includes/header.php';

$page_title = 'Categories';

// Get all categories
$categories = getAllCategories();

// Get category with product count
$db = Database::getInstance();
$categories_with_counts = $db->fetchAll(
    "SELECT c.*, COUNT(p.product_id) as product_count 
     FROM categories c 
     LEFT JOIN products p ON c.category_id = p.category_id AND p.is_active = 1
     WHERE c.is_active = 1 
     GROUP BY c.category_id 
     ORDER BY c.category_name"
);
?>

<main>
    <div class="container">
        <div class="categories-page">
            <div class="page-header">
                <h1>Product Categories</h1>
                <p>Browse our eyewear collection by category</p>
            </div>
            
            <div class="categories-grid">
                <?php foreach ($categories_with_counts as $category): ?>
                <div class="category-card">
                    <div class="category-image">
                        <?php if ($category['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($category['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($category['category_name']); ?>">
                        <?php else: ?>
                            <div class="category-placeholder">
                                <?php
                                $icons = [
                                    'Sunglasses' => 'fas fa-sun',
                                    'Eyeglasses' => 'fas fa-eye',
                                    'Sports Glasses' => 'fas fa-running',
                                    'Kids Eyewear' => 'fas fa-child'
                                ];
                                $icon = $icons[$category['category_name']] ?? 'fas fa-glasses';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="category-overlay">
                            <a href="products.php?category=<?php echo $category['category_id']; ?>" 
                               class="btn btn-primary category-btn">
                                View Products
                            </a>
                        </div>
                    </div>
                    
                    <div class="category-info">
                        <h3 class="category-name">
                            <a href="products.php?category=<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </a>
                        </h3>
                        
                        <p class="category-description">
                            <?php echo htmlspecialchars($category['description']); ?>
                        </p>
                        
                        <div class="category-stats">
                            <span class="product-count">
                                <?php echo $category['product_count']; ?> product<?php echo $category['product_count'] !== 1 ? 's' : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Featured Categories -->
            <div class="featured-categories">
                <h2>Featured Categories</h2>
                <div class="featured-grid">
                    <?php 
                    $featured_categories = array_slice($categories_with_counts, 0, 3);
                    foreach ($featured_categories as $category): 
                    ?>
                    <div class="featured-card">
                        <div class="featured-image">
                            <?php if ($category['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($category['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($category['category_name']); ?>">
                            <?php else: ?>
                                <div class="featured-placeholder">
                                    <?php
                                    $icons = [
                                        'Sunglasses' => 'fas fa-sun',
                                        'Eyeglasses' => 'fas fa-eye',
                                        'Sports Glasses' => 'fas fa-running',
                                        'Kids Eyewear' => 'fas fa-child'
                                    ];
                                    $icon = $icons[$category['category_name']] ?? 'fas fa-glasses';
                                    ?>
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="featured-content">
                            <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                            <p><?php echo htmlspecialchars(substr($category['description'], 0, 100)); ?>...</p>
                            <a href="products.php?category=<?php echo $category['category_id']; ?>" 
                               class="btn btn-outline">Explore Now</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Category Benefits -->
            <div class="category-benefits">
                <h2>Why Choose EyeLux?</h2>
                <div class="benefits-grid">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Quality Guarantee</h3>
                        <p>All our eyewear comes with UV protection and quality materials guaranteed.</p>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <h3>Free Shipping</h3>
                        <p>Free shipping on orders over $50. Fast and reliable delivery to your doorstep.</p>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-undo"></i>
                        </div>
                        <h3>Easy Returns</h3>
                        <p>30-day return policy. Not satisfied? Return your purchase hassle-free.</p>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h3>Expert Support</h3>
                        <p>Our eyewear experts are here to help you find the perfect pair.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.categories-page {
    padding: 20px 0;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
}

.page-header h1 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 15px;
}

.page-header p {
    color: #666;
    font-size: 16px;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.category-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.category-image {
    position: relative;
    height: 200px;
    overflow: hidden;
    background: #f8f9fa;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.category-card:hover .category-image img {
    transform: scale(1.05);
}

.category-placeholder,
.featured-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-size: 48px;
}

.category-overlay {
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

.category-card:hover .category-overlay {
    opacity: 1;
}

.category-btn {
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
}

.category-info {
    padding: 25px;
}

.category-name {
    margin-bottom: 15px;
}

.category-name a {
    color: #2c3e50;
    text-decoration: none;
    font-size: 20px;
    font-weight: 600;
}

.category-name a:hover {
    color: #e74c3c;
}

.category-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.category-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-count {
    color: #e74c3c;
    font-weight: 600;
    font-size: 14px;
}

.featured-categories {
    background: white;
    border-radius: 10px;
    padding: 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 60px;
}

.featured-categories h2 {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 40px;
    font-size: 28px;
}

.featured-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.featured-card {
    text-align: center;
    padding: 30px 20px;
    border-radius: 10px;
    background: #f8f9fa;
    transition: transform 0.3s;
}

.featured-card:hover {
    transform: translateY(-5px);
}

.featured-image {
    width: 120px;
    height: 120px;
    margin: 0 auto 20px;
    border-radius: 50%;
    overflow: hidden;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}

.featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.featured-content h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 18px;
}

.featured-content p {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.5;
}

.category-benefits {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    padding: 50px;
    color: white;
    text-align: center;
}

.category-benefits h2 {
    margin-bottom: 40px;
    font-size: 28px;
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.benefit-item {
    padding: 20px;
}

.benefit-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 20px;
}

.benefit-item h3 {
    margin-bottom: 15px;
    font-size: 20px;
}

.benefit-item p {
    opacity: 0.9;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .featured-grid {
        grid-template-columns: 1fr;
    }
    
    .benefits-grid {
        grid-template-columns: 1fr;
    }
    
    .category-benefits {
        padding: 30px 20px;
    }
    
    .featured-categories {
        padding: 30px 20px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>



