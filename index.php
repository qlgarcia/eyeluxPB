<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Debug logging for index.php access
error_log("Index.php accessed - Session user_id: " . ($_SESSION['user_id'] ?? 'not set') . ", isLoggedIn: " . (isLoggedIn() ? 'true' : 'false'));

require_once 'includes/header.php';

$page_title = 'Home';

// Check for verification success message and complete login if needed
$verification_success = '';
if (isset($_SESSION['verification_success']) && $_SESSION['verification_success']) {
    $verification_success = 'Email verified successfully! You are now logged in.';
    unset($_SESSION['verification_success']);
}

// Complete login if there's a pending login user ID
if (isset($_SESSION['pending_login_user_id']) && !isset($_SESSION['user_id'])) {
    error_log("Pending login detected on index.php - User ID: " . $_SESSION['pending_login_user_id']);
    
    $pending_user_id = $_SESSION['pending_login_user_id'];
    unset($_SESSION['pending_login_user_id']);
    
    try {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT user_id, email, first_name, last_name FROM users WHERE user_id = ? AND email_verified = 1",
            [$pending_user_id]
        );
        
        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Migrate guest cart to user cart
            migrateGuestCartToUser($user['user_id'], session_id());
            
            error_log("Login completed on index.php - User ID: {$user['user_id']}, Email: {$user['email']}");
        } else {
            error_log("Failed to find verified user for pending login - User ID: {$pending_user_id}");
        }
    } catch (Exception $e) {
        error_log("Error completing login on index.php: " . $e->getMessage());
    }
}

// Get featured products
$featured_products = getFeaturedProducts(8);
$new_arrivals = getNewArrivalProducts(6);
$best_sellers = getBestSellingProducts(6);
$categories = getAllCategories();

// Get hero content and carousel
$hero_content = getHeroContent();
$carousel_items = getHeroCarousel();

// Fallback content if no hero content exists
if (!$hero_content) {
    $hero_content = [
        'title' => 'Discover Your Perfect Eyewear',
        'subtitle' => 'From classic aviators to modern frames, find the perfect pair that matches your style and personality.',
        'button_text' => 'Shop Now',
        'button_link' => 'products.php'
    ];
}
?>

<main>
    <?php if ($verification_success): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px; border: 1px solid #c3e6cb; text-align: center;">
            <strong>✅ <?php echo htmlspecialchars($verification_success); ?></strong>
        </div>
    <?php endif; ?>
    
    <!-- Hero Section -->
    <section class="hero">
        <!-- Carousel Background -->
        <?php if (!empty($carousel_items)): ?>
        <div class="hero-carousel">
            <?php foreach ($carousel_items as $index => $item): ?>
            <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo htmlspecialchars($item['image_url']); ?>');">
                <div class="carousel-overlay"></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Carousel Controls -->
        <div class="carousel-controls">
            <button class="carousel-prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
            <button class="carousel-next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
        
        <!-- Carousel Indicators -->
        <div class="carousel-indicators">
            <?php foreach ($carousel_items as $index => $item): ?>
            <button class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $index + 1; ?>)"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="container">
            <div class="hero-content">
                <h1><?php echo htmlspecialchars($hero_content['title']); ?></h1>
                <p><?php echo htmlspecialchars($hero_content['subtitle']); ?></p>
                <a href="<?php echo htmlspecialchars($hero_content['button_link']); ?>" class="hero-btn"><?php echo htmlspecialchars($hero_content['button_text']); ?></a>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="section" id="categories-section">
        <div class="container">
            <h2 class="section-title">Shop by Category</h2>
            <div class="categories">
                <?php foreach ($categories as $category): ?>
                <div class="category-card">
                    <div class="category-icon">
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
                    <h3 class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></h3>
                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                    <a href="products.php?category=<?php echo $category['category_id']; ?>" class="btn btn-outline">View Products</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="section" style="background: var(--bg-primary); backdrop-filter: blur(10px); border-radius: 20px; margin: 20px; padding: 40px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-light);">
        <div class="container">
            <h2 class="section-title">Top Rated Products</h2>
            <?php if (!empty($featured_products)): ?>
            <div class="product-grid">
                <?php foreach ($featured_products as $product): ?>
                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="product-card-link">
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                        <?php endif; ?>
                        
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
                        <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
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
                                $rating = $product['avg_rating'] ?? 0;
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
                        
                        <?php 
                        $sales_count = getProductSalesCount($product['product_id']);
                        if ($sales_count > 0): 
                        ?>
                        <div class="product-sales">
                            <i class="fas fa-chart-line"></i>
                            <span><?php echo $sales_count; ?> sold</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-actions">
                            <?php if (isLoggedIn()): ?>
                                <button type="button" class="btn btn-primary add-to-cart-btn" onclick="event.stopPropagation(); addToCartAjax(<?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-shopping-cart"></i> Quick Add
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-warning" onclick="event.stopPropagation(); showLoginPrompt()">
                                    <i class="fas fa-lock"></i> Login to Add
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline" onclick="event.stopPropagation(); window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="products.php" class="btn btn-outline">View All Products</a>
            </div>
            <?php else: ?>
            <div class="no-top-rated">
                <div style="text-align: center; padding: 60px 20px; color: #666;">
                    <i class="fas fa-star" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 15px; color: #999;">No Top Rated Products Yet</h3>
                    <p style="margin-bottom: 30px; font-size: 16px;">Be the first to review our amazing eyewear collection!</p>
                    <a href="products.php" class="btn btn-primary">Shop All Products</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Best Sellers Section -->
    <?php if (!empty($best_sellers)): ?>
    <section class="section" style="background: rgba(248, 249, 250, 0.95); backdrop-filter: blur(15px); border-radius: 20px; margin: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="container">
            <h2 class="section-title">Our Best Sellers</h2>
            <div class="product-grid">
                <?php foreach ($best_sellers as $product): ?>
                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="product-card-link">
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                        <?php endif; ?>
                        
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                            <div class="sale-badge">
                                <?php echo calculateDiscountPercentage($product['price'], $product['sale_price']); ?>% OFF
                            </div>
                        <?php endif; ?>
                        
                        <div class="best-seller-badge">BEST SELLER</div>
                    </div>
                    
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                        
                        <div class="product-rating">
                            <?php
                            $rating = $product['avg_rating'] ?? 0;
                            $review_count = $product['review_count'] ?? 0;
                            for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                            <?php endfor; ?>
                            <span class="rating-text">(<?php echo $review_count; ?>)</span>
                        </div>
                        
                        <div class="sales-info">
                            <span class="sales-count">
                                <i class="fas fa-users"></i>
                                <?php 
                                $sales_count = $product['sales_count'] ?? 0;
                                if ($sales_count > 0) {
                                    echo $sales_count . ' ' . ($sales_count == 1 ? 'person' : 'people') . ' bought this';
                                } else {
                                    echo 'Be the first to buy!';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <div class="product-price">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                <span class="current-price">₱<?php echo number_format($product['sale_price'], 2); ?></span>
                                <span class="original-price">₱<?php echo number_format($product['price'], 2); ?></span>
                            <?php else: ?>
                                <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="products.php?sort=sales" class="btn btn-outline">View All Best Sellers</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- New Arrivals Section -->
    <?php if (!empty($new_arrivals)): ?>
    <section class="section" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); border-radius: 20px; margin: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="container">
            <h2 class="section-title">New Arrivals</h2>
            <div class="product-grid">
                <?php foreach ($new_arrivals as $product): ?>
                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="product-card-link">
                <div class="product-card">
                    <div class="product-image">
                        <?php if ($product['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                        <?php endif; ?>
                        
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
                        <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
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
                        
                        <div class="product-actions">
                            <?php if (isLoggedIn()): ?>
                                <button type="button" class="btn btn-primary add-to-cart-btn" onclick="event.stopPropagation(); addToCartAjax(<?php echo $product['product_id']; ?>)">
                                    <i class="fas fa-shopping-cart"></i> Quick Add
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-warning" onclick="event.stopPropagation(); showLoginPrompt()">
                                    <i class="fas fa-lock"></i> Login to Add
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline" onclick="event.stopPropagation(); window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <section class="section" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); border-radius: 20px; margin: 20px; padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 0 0 20px 20px;">
        <div class="container">
            <h2 class="section-title">Why Choose EyeLux?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h3>Free Shipping</h3>
                    <p>Free shipping on orders over $50. Fast and reliable delivery to your doorstep.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3>Easy Returns</h3>
                    <p>30-day return policy. Not satisfied? Return your purchase hassle-free.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Quality Guarantee</h3>
                    <p>Authentic eyewear with UV protection and quality materials guaranteed.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Round-the-clock customer support to help you with any questions.</p>
                </div>
            </div>
        </div>
    </section>
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
    margin: 0;
    padding: 0;
}

/* Container for content sections */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Full Screen Hero Section */
.hero {
    position: relative;
    overflow: hidden;
    height: 85vh;
    background: var(--gradient-warm);
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-carousel {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.carousel-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: 0;
    transition: opacity 1s ease-in-out;
}

.carousel-slide.active {
    opacity: 1;
}

.carousel-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(250, 248, 245, 0.7);
}

.hero-content {
    position: relative;
    z-index: 3;
    text-align: center;
    color: var(--text-primary);
    padding: 40px 30px;
    max-width: 800px;
}

.hero-content h1 {
    font-size: 3.2rem;
    font-weight: 300;
    margin-bottom: 25px;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1.1;
}

.hero-content p {
    font-size: 1.1rem;
    margin-bottom: 35px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    color: var(--text-secondary);
    font-weight: 400;
    line-height: 1.7;
    letter-spacing: 0.2px;
}

.hero-btn {
    display: inline-block;
    background: var(--sage);
    color: white;
    padding: 18px 36px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
    border: 1px solid var(--sage);
}

.hero-btn:hover {
    background: var(--terracotta);
    border-color: var(--terracotta);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
    text-decoration: none;
}

.carousel-controls {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 100%;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    padding: 0 20px;
}

.carousel-prev, .carousel-next {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.carousel-prev:hover, .carousel-next:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(0,0,0,0.3);
}

.carousel-indicators {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 2;
}

.indicator {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.8);
    background: transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.indicator.active {
    background: white;
    transform: scale(1.2);
    box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
}

.indicator:hover {
    background: rgba(255, 255, 255, 0.5);
    transform: scale(1.1);
}

/* Responsive Carousel */
@media (max-width: 768px) {
    .carousel-controls {
        padding: 0 10px;
    }
    
    .carousel-prev, .carousel-next {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
    
    .carousel-indicators {
        bottom: 15px;
    }
    
    .indicator {
        width: 10px;
        height: 10px;
    }
}

.sale-badge, .new-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--terracotta);
    color: white;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.new-badge {
    background: var(--sage);
}

.original-price {
    text-decoration: line-through;
    color: var(--text-muted);
    font-size: 16px;
    margin-left: 10px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.sale-price {
    color: var(--sage);
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.feature-item {
    text-align: center;
    padding: 30px 20px;
}

.feature-icon {
    font-size: 48px;
    color: #e74c3c;
    margin-bottom: 20px;
}

.feature-item h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #2c3e50;
}

.feature-item p {
    color: #666;
    line-height: 1.6;
}

/* Categories Section */
#categories-section {
    background: var(--bg-primary);
    padding: 60px 0;
    margin: 40px 20px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

#categories-section .section-title {
    color: var(--text-primary);
    font-size: 2.5rem;
    font-weight: 300;
    margin-bottom: 50px;
    text-align: center;
    letter-spacing: -0.02em;
}

.categories {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Enhanced Category Cards */
.category-card {
    background: var(--bg-primary);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 30px 25px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
    color: var(--text-primary);
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(139, 115, 85, 0.12);
    border-color: var(--sage);
}

.category-icon {
    width: 80px;
    height: 80px;
    background: var(--gradient-accent);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 28px;
    box-shadow: var(--shadow-subtle);
    transition: all 0.3s ease;
}

.category-card:hover .category-icon {
    transform: scale(1.05);
    box-shadow: 0 5px 20px rgba(156, 175, 136, 0.3);
}

.category-name {
    font-size: 20px;
    font-weight: 400;
    color: var(--text-primary);
    margin-bottom: 12px;
    letter-spacing: 0.3px;
}

.category-card p {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.btn-outline {
    display: inline-block;
    padding: 12px 24px;
    background: transparent;
    color: var(--sage);
    border: 2px solid var(--sage);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-outline:hover {
    background: var(--sage);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

/* Button Styles for Home Page */
.btn-primary {
    background: var(--sage);
    color: white;
    border: 1px solid var(--sage);
    transition: all 0.3s ease;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    background: var(--terracotta);
    border-color: var(--terracotta);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
    color: white;
}

.btn-warning {
    background: var(--khaki-dark);
    color: white;
    border: 1px solid var(--khaki-dark);
    transition: all 0.3s ease;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
}

.btn-warning:hover {
    background: var(--khaki-deep);
    border-color: var(--khaki-deep);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
    color: white;
}

/* Enhanced Product Cards */
.product-card {
    background: var(--bg-primary);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
    transition: all 0.3s ease;
    height: 100%;
    min-height: 550px;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(139, 115, 85, 0.12);
    border-color: var(--sage);
}

.product-image {
    background: var(--bg-secondary);
    border-radius: 20px 20px 0 0;
    overflow: hidden;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-image img {
    transition: transform 0.3s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-info {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 350px;
}

/* Enhanced Best Seller Badge */
.best-seller-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
}

.product-sales {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 8px;
    font-size: 14px;
    color: #27ae60;
    font-weight: 600;
}

.product-sales i {
    font-size: 12px;
}

.sales-info {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--sage);
    font-size: 12px;
    font-weight: 600;
    margin-top: 8px;
}

.sales-count {
    color: var(--sage);
    font-size: 12px;
    font-weight: 600;
}

.product-rating {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 8px 0;
}

.product-rating .fas.fa-star {
    color: #ffc107;
    font-size: 12px;
}

.product-rating .fas.fa-star:not(.active) {
    color: #ddd;
}

.rating-text {
    color: var(--text-secondary);
    font-size: 12px;
    margin-left: 3px;
}
</style>

<script>
// Carousel functionality
let currentSlideIndex = 0;
const slides = document.querySelectorAll('.carousel-slide');
const indicators = document.querySelectorAll('.indicator');
const totalSlides = slides.length;

// Handle URL hash for direct navigation to sections
window.addEventListener('load', function() {
    if (window.location.hash === '#categories-section') {
        setTimeout(() => {
            const categoriesSection = document.getElementById('categories-section');
            if (categoriesSection) {
                categoriesSection.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }, 100);
    }
});

function showSlide(index) {
    // Remove active class from all slides and indicators
    slides.forEach(slide => slide.classList.remove('active'));
    indicators.forEach(indicator => indicator.classList.remove('active'));
    
    // Add active class to current slide and indicator
    if (slides[index]) {
        slides[index].classList.add('active');
    }
    if (indicators[index]) {
        indicators[index].classList.add('active');
    }
}

function changeSlide(direction) {
    currentSlideIndex += direction;
    
    if (currentSlideIndex >= totalSlides) {
        currentSlideIndex = 0;
    } else if (currentSlideIndex < 0) {
        currentSlideIndex = totalSlides - 1;
    }
    
    showSlide(currentSlideIndex);
}

function currentSlide(index) {
    currentSlideIndex = index - 1;
    showSlide(currentSlideIndex);
}

// Auto-advance carousel every 5 seconds
if (totalSlides > 1) {
    setInterval(() => {
        changeSlide(1);
    }, 5000);
}

// Touch/swipe support for mobile
let startX = 0;
let endX = 0;

document.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX;
});

document.addEventListener('touchend', (e) => {
    endX = e.changedTouches[0].clientX;
    handleSwipe();
});

function handleSwipe() {
    const threshold = 50;
    const diff = startX - endX;
    
    if (Math.abs(diff) > threshold) {
        if (diff > 0) {
            // Swipe left - next slide
            changeSlide(1);
        } else {
            // Swipe right - previous slide
            changeSlide(-1);
        }
    }
}

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
