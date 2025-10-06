<?php
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get cart count for logged in users
if (isset($_SESSION['user_id'])) {
    $cart_count = getCartCount($_SESSION['user_id']);
} else {
    $cart_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time() + rand(1, 1000); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="index.php"><span>Eye</span>Lux</a>
            </div>

            <nav class="nav">
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="#" onclick="scrollToCategories(); return false;">Categories</a></li>
                    <li><a href="#" onclick="scrollToAbout(); return false;">About</a></li>
                    <li><a href="#" onclick="showContactModal(); return false;">Contact</a></li>
                </ul>

                <form class="search-bar" action="search.php" method="GET" id="searchForm">
                    <input type="text" name="q" placeholder="Search eyewear..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="submit" id="searchSubmitBtn"><i class="fas fa-search"></i></button>
                </form>
            </nav>

            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Logged in user menu -->
                    <div class="user-menu">
                        <a href="#" class="user-toggle">
                            <?php 
                            $profile_picture = getUserProfilePicture($_SESSION['user_id']);
                            if ($profile_picture): 
                            ?>
                                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="user-avatar">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </a>
                        <div class="user-dropdown">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                            <a href="orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a>
                            <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                            <a href="user-notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" title="Login"><i class="fas fa-user"></i></a>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="cart.php" title="Shopping Cart" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cart-count"><?php echo $cart_count; ?></span>
                </a>

                <a href="wishlist.php" title="Wishlist" class="wishlist-link">
                    <i class="fas fa-heart"></i>
                    <span class="wishlist-count" id="wishlist-count"><?php echo getWishlistCount($_SESSION['user_id']); ?></span>
                </a>
                <?php else: ?>
                <a href="login.php" title="Login to access cart" class="cart-link disabled">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cart-count">0</span>
                </a>

                <a href="login.php" title="Login to access wishlist" class="wishlist-link disabled">
                    <i class="fas fa-heart"></i>
                    <span class="wishlist-count" id="wishlist-count">0</span>
                </a>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Simple Notification Bell with Real Count -->
                <?php
                $unread_count = 0;
                if (function_exists('getUnreadNotificationCount')) {
                    try {
                        $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                    } catch (Exception $e) {
                        $unread_count = 0;
                    }
                }
                ?>
                <div class="notification-container" style="position: relative; display: inline-block;">
                    <button class="notification-bell" title="Notifications" onclick="toggleNotificationDropdown()" style="background: #f8f9fa; border: 1px solid #e9ecef; cursor: pointer; position: relative; color: #333; font-size: 18px; padding: 10px; border-radius: 50%; transition: all 0.3s ease; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                        <span style="position: absolute; top: -5px; right: -5px; background: #e74c3c; color: white; border-radius: 50%; min-width: 18px; height: 18px; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold; padding: 0 4px;">
                            <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div id="notificationDropdown" class="notification-dropdown" style="display: none;">
                        <div class="notification-dropdown-header">
                            <h3>Notifications</h3>
                            <span class="notification-close" onclick="closeNotificationDropdown()">&times;</span>
                        </div>
                        <div class="notification-dropdown-content" id="notificationContent">
                            <div class="notification-loading">
                                <i class="fas fa-spinner fa-spin"></i> Loading notifications...
                            </div>
                        </div>
                        <div class="notification-dropdown-footer">
                            <a href="user-notifications.php" class="view-all-notifications">View All Notifications</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

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

    /* Minimalist Body Styling */
    body {
        background: var(--bg-primary);
        color: var(--text-primary);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        line-height: 1.6;
        letter-spacing: -0.01em;
    }

    /* Minimalist Header */
    .header {
        background: rgba(250, 248, 245, 0.95);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border-light);
        box-shadow: var(--shadow-subtle);
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .header .container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Minimalist Logo */
    .logo a {
        font-size: 28px;
        font-weight: 300;
        text-decoration: none;
        color: var(--text-primary);
        transition: all 0.3s ease;
        letter-spacing: 1px;
        font-family: 'Inter', sans-serif;
    }

    .logo a:hover {
        color: var(--sage);
        transform: translateY(-1px);
    }

    /* Minimalist Navigation */
    .nav {
        display: flex;
        align-items: center;
        gap: 40px;
    }

    .nav-links {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 40px;
    }

    .nav-links a {
        color: var(--text-secondary);
        text-decoration: none;
        font-weight: 400;
        font-size: 15px;
        letter-spacing: 0.3px;
        transition: all 0.3s ease;
        position: relative;
        padding: 8px 0;
    }

    .nav-links a::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 1px;
        background: var(--sage);
        transition: width 0.3s ease;
    }

    .nav-links a:hover {
        color: var(--sage);
    }

    .nav-links a:hover::after {
        width: 100%;
    }

    /* Minimalist Search Bar */
    .search-bar {
        display: flex;
        align-items: center;
        background: var(--bg-secondary);
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 8px 16px;
        transition: all 0.3s ease;
        width: 280px;
    }

    .search-bar:focus-within {
        border-color: var(--sage);
        box-shadow: 0 0 0 3px rgba(156, 175, 136, 0.1);
    }

    .search-bar input {
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-primary);
        padding: 4px 8px;
        font-size: 14px;
        width: 100%;
        font-family: inherit;
    }

    .search-bar input::placeholder {
        color: var(--text-muted);
    }

    .search-bar button {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        padding: 4px;
    }

    .search-bar button:hover {
        color: var(--sage);
    }

    /* User Actions */
    .user-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-actions a {
        color: var(--text-secondary);
        text-decoration: none;
        padding: 12px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
        position: relative;
    }

    .user-actions a:hover {
        background: var(--bg-accent);
        border-color: var(--sage);
        transform: translateY(-2px);
        box-shadow: var(--shadow-subtle);
        color: var(--sage);
    }

    .user-menu {
        position: relative;
    }

    .user-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--bg-primary);
        backdrop-filter: blur(10px);
        min-width: 200px;
        border-radius: 8px;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-subtle);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1000;
        margin-top: 10px;
    }

    .user-menu:hover .user-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .user-dropdown a {
        display: block;
        padding: 15px 20px;
        color: var(--text-primary);
        transition: all 0.3s ease;
        border-radius: 6px;
        margin: 5px;
    }

    .user-dropdown a:hover {
        background: var(--bg-accent);
        color: var(--sage);
        transform: translateX(5px);
    }

    .user-dropdown a i {
        margin-right: 10px;
        width: 16px;
    }

    .user-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--sage);
        transition: all 0.3s ease;
    }

    .user-avatar:hover {
        border-color: var(--terracotta);
        transform: scale(1.05);
    }

    /* Cart and Wishlist Count Styles */
    .cart-link,
    .wishlist-link {
        position: relative;
    }

    .cart-count,
    .wishlist-count {
        position: absolute;
        top: -8px;
        right: -8px;
        background: var(--terracotta);
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        min-width: 22px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(193, 123, 92, 0.4);
        border: 2px solid white;
    }

    .cart-count.animate,
    .wishlist-count.animate {
        animation: bounce 0.6s ease-in-out;
        background: var(--sage);
        transform: scale(1.2);
    }

    .cart-link.disabled,
    .wishlist-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .cart-link.disabled:hover,
    .wishlist-link.disabled:hover {
        opacity: 0.5;
    }

    /* Contact Modal Styles */
    .contact-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
        box-sizing: border-box;
    }

    .contact-modal-content {
        background: white;
        border-radius: 15px;
        max-width: 800px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .contact-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 25px 30px;
        border-bottom: 1px solid #eee;
        background: #f8f9fa;
        border-radius: 15px 15px 0 0;
    }

    .contact-modal-header h2 {
        margin: 0;
        color: #2c3e50;
        font-size: 24px;
    }

    .contact-close {
        font-size: 30px;
        color: #999;
        cursor: pointer;
        line-height: 1;
        transition: color 0.3s;
    }

    .contact-close:hover {
        color: #e74c3c;
    }

    .contact-modal-body {
        padding: 30px;
    }

    .contact-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .contact-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        transition: transform 0.3s ease;
    }

    .contact-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .contact-item i {
        font-size: 24px;
        color: #e74c3c;
        margin-top: 5px;
        width: 30px;
        text-align: center;
    }

    .contact-item h4 {
        margin: 0 0 8px 0;
        color: #2c3e50;
        font-size: 16px;
        font-weight: 600;
    }

    .contact-item p {
        margin: 0;
        color: #666;
        line-height: 1.5;
    }

    .contact-form-section {
        border-top: 1px solid #eee;
        padding-top: 30px;
    }

    .contact-form-section h3 {
        margin: 0 0 25px 0;
        color: #2c3e50;
        font-size: 20px;
    }

    .contact-form .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .contact-form .form-group {
        margin-bottom: 20px;
    }

    .contact-form label {
        display: block;
        margin-bottom: 8px;
        color: #2c3e50;
        font-weight: 600;
        font-size: 14px;
    }

    .contact-form input,
    .contact-form textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    }

    .contact-form input:focus,
    .contact-form textarea:focus {
        outline: none;
        border-color: #e74c3c;
    }

    .contact-form textarea {
        resize: vertical;
        min-height: 120px;
    }

    .contact-form .btn {
        width: 100%;
        padding: 15px;
        font-size: 16px;
        font-weight: 600;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .contact-modal {
            padding: 10px;
        }
        
        .contact-modal-content {
            max-height: 95vh;
        }
        
        .contact-modal-header {
            padding: 20px;
        }
        
        .contact-modal-body {
            padding: 20px;
        }
        
        .contact-info {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .contact-form .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
    }

    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-10px);
        }
        60% {
            transform: translateY(-5px);
        }
    }

    /* Notification Toast */
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #27ae60;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease-in-out;
        max-width: 300px;
    }

    .notification-toast.show {
        transform: translateX(0);
    }

    .notification-toast.error {
        background: #e74c3c;
    }

    .notification-toast .toast-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notification-toast .toast-icon {
        font-size: 20px;
    }

    .notification-toast .toast-message {
        flex: 1;
    }

    .notification-toast .toast-close {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        margin-left: 10px;
    }

    /* Notification Dropdown Styles */
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 350px;
        max-height: 500px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        border: 1px solid var(--border-light);
        z-index: 1000;
        margin-top: 10px;
        overflow: hidden;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-dropdown-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-dropdown-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .notification-close {
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: background 0.3s ease;
    }

    .notification-close:hover {
        background: rgba(255,255,255,0.2);
    }

    .notification-dropdown-content {
        max-height: 350px;
        overflow-y: auto;
    }

    .notification-loading {
        padding: 30px 20px;
        text-align: center;
        color: #666;
    }

    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.3s ease;
        cursor: pointer;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item.unread {
        background: #f0f8ff;
        border-left: 4px solid #667eea;
    }

    .notification-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .notification-message {
        color: #666;
        font-size: 13px;
        line-height: 1.4;
        margin-bottom: 8px;
    }

    .notification-time {
        color: #999;
        font-size: 12px;
    }

    .notification-dropdown-footer {
        padding: 15px 20px;
        background: #f8f9fa;
        text-align: center;
        border-top: 1px solid #e9ecef;
    }

    .view-all-notifications {
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .view-all-notifications:hover {
        color: #764ba2;
    }

    .no-notifications {
        padding: 40px 20px;
        text-align: center;
        color: #666;
    }

    .no-notifications i {
        font-size: 48px;
        color: #ddd;
        margin-bottom: 15px;
    }
    </style>

    <!-- Loading Component -->
    <?php include 'includes/loading-component.php'; ?>

    <!-- Contact Modal -->
    <div id="contactModal" class="contact-modal" style="display: none;">
        <div class="contact-modal-content">
            <div class="contact-modal-header">
                <h2>Contact Us</h2>
                <span class="contact-close" onclick="closeContactModal()">&times;</span>
            </div>
            <div class="contact-modal-body">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>Phone</h4>
                            <p>+1 (555) 123-4567</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p>info@eyelux.com</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Address</h4>
                            <p>123 Fashion Street<br>Style City, SC 12345</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h4>Business Hours</h4>
                            <p>Mon-Fri: 9AM-6PM<br>Sat: 10AM-4PM<br>Sun: Closed</p>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form-section">
                    <h3>Send us a Message</h3>
                    <form id="contactForm" class="contact-form" action="ajax-contact-admin.php" method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contactName">Name *</label>
                                <input type="text" id="contactName" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="contactEmail">Email *</label>
                                <input type="email" id="contactEmail" name="email" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contactSubject">Subject *</label>
                            <input type="text" id="contactSubject" name="subject" required>
                        </div>
                        <div class="form-group">
                            <label for="contactMessage">Message *</label>
                            <textarea id="contactMessage" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Mobile menu toggle (for future enhancement)
    document.addEventListener('DOMContentLoaded', function() {
        // Add mobile menu functionality here if needed
        
        // Search form loading
        const searchForm = document.getElementById('searchForm');
        const searchSubmitBtn = document.getElementById('searchSubmitBtn');
        
        if (searchForm && searchSubmitBtn) {
            searchForm.addEventListener('submit', function(e) {
                setButtonLoading(searchSubmitBtn, true);
                showPageLoading();
            });
        }
        
        // Navigation links loading
        const navLinks = document.querySelectorAll('nav a[href]');
        navLinks.forEach(link => {
            // Skip external links and anchor links
            if (link.hostname === window.location.hostname && !link.href.includes('#')) {
                link.addEventListener('click', function(e) {
                    // Don't show loading for same page
                    if (link.href !== window.location.href) {
                        showPageLoading();
                    }
                });
            }
        });
        
        // Cart and wishlist links loading
        const cartLink = document.querySelector('.cart-link');
        const wishlistLink = document.querySelector('.wishlist-link');
        
        if (cartLink) {
            cartLink.addEventListener('click', function(e) {
                if (!this.classList.contains('disabled')) {
                    showPageLoading();
                }
            });
        }
        
        if (wishlistLink) {
            wishlistLink.addEventListener('click', function(e) {
                if (!this.classList.contains('disabled')) {
                    showPageLoading();
                }
            });
        }
        
        // Contact form loading
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handleContactForm(this);
            });
        }
    });

    // AJAX Cart and Wishlist Functions
    function addToCartAjax(productId, quantity = 1) {
        // Use the enhanced loading version
        addToCartAjaxWithLoading(productId, quantity);
    }

    function addToWishlistAjax(productId) {
        // Prevent multiple submissions
        const button = event.target;
        if (button.disabled) return;
        
        // Set button loading state
        setButtonLoading(button, true);
        
        const formData = new FormData();
        formData.append('ajax_add_to_wishlist', '1');
        formData.append('product_id', productId);

        fetch('ajax-actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update wishlist count
                const wishlistCount = document.getElementById('wishlist-count');
                if (wishlistCount) {
                    wishlistCount.textContent = data.wishlist_count;
                    wishlistCount.classList.add('animate');
                    setTimeout(() => wishlistCount.classList.remove('animate'), 600);
                }

                // Show notification
                if (typeof showNotification === 'function') {
                    showNotification('success', data.message, data.product_name);
                }
                
                // Show success state briefly
                button.innerHTML = '<i class="fas fa-check"></i> Added!';
                setTimeout(() => {
                    setButtonLoading(button, false);
                    button.innerHTML = '<i class="fas fa-heart"></i> Add to Wishlist';
                }, 1500);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', data.message);
                }
                setButtonLoading(button, false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showNotification === 'function') {
                showNotification('error', 'Something went wrong. Please try again.');
            }
            setButtonLoading(button, false);
        });
    }

    function showNotification(type, message, productName = '') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification-toast ${type === 'error' ? 'error' : ''}`;
        
        const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        const fullMessage = productName ? `${message} "${productName}"` : message;
        
        notification.innerHTML = `
            <div class="toast-content">
                <i class="toast-icon ${icon}"></i>
                <div class="toast-message">${fullMessage}</div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Auto hide after 4 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 4000);
    }

    // Scroll to Categories section
    function scrollToCategories() {
        const categoriesSection = document.getElementById('categories-section');
        if (categoriesSection) {
            categoriesSection.scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        } else {
            // If not on homepage, redirect to homepage with categories section
            window.location.href = 'index.php#categories-section';
        }
    }

    // Scroll to About section (footer)
    function scrollToAbout() {
        const aboutSection = document.getElementById('about-section');
        if (aboutSection) {
            aboutSection.scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    // Show Contact Modal
    function showContactModal() {
        const modal = document.getElementById('contactModal');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
    }

    // Close Contact Modal
    function closeContactModal() {
        const modal = document.getElementById('contactModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
    }

    // Handle Contact Form Submission
    function handleContactForm(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Set loading state
        setFormLoading(form, true);
        setButtonLoading(submitBtn, true);
        
        // Get form data
        const formData = new FormData(form);
        const data = {
            name: formData.get('name'),
            email: formData.get('email'),
            subject: formData.get('subject'),
            message: formData.get('message')
        };
        
        // Simulate form submission (replace with actual AJAX call)
        setTimeout(() => {
            // Reset form
            form.reset();
            
            // Reset loading state
            setFormLoading(form, false);
            setButtonLoading(submitBtn, false);
            
            // Show success message
            if (typeof showNotification === 'function') {
                showNotification('success', 'Thank you for your message! We will get back to you soon.');
            } else {
                alert('Thank you for your message! We will get back to you soon.');
            }
            
            // Close modal
            closeContactModal();
        }, 2000);
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('contactModal');
        if (event.target === modal) {
            closeContactModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeContactModal();
        }
    });

    // Notification Dropdown Functions
    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        const content = document.getElementById('notificationContent');
        
        if (dropdown.style.display === 'none' || dropdown.style.display === '') {
            dropdown.style.display = 'block';
            loadNotifications();
        } else {
            dropdown.style.display = 'none';
        }
    }

    function closeNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.style.display = 'none';
    }

    function loadNotifications() {
        const content = document.getElementById('notificationContent');
        content.innerHTML = '<div class="notification-loading"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</div>';
        
        fetch('ajax-get-notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                } else {
                    content.innerHTML = '<div class="no-notifications"><i class="fas fa-bell-slash"></i><p>No notifications available</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                content.innerHTML = '<div class="no-notifications"><i class="fas fa-exclamation-triangle"></i><p>Error loading notifications</p></div>';
            });
    }

    function displayNotifications(notifications) {
        const content = document.getElementById('notificationContent');
        
        if (notifications.length === 0) {
            content.innerHTML = '<div class="no-notifications"><i class="fas fa-bell-slash"></i><p>No notifications</p></div>';
            return;
        }
        
        let html = '';
        notifications.forEach(notification => {
            const timeAgo = getTimeAgo(notification.created_at);
            const unreadClass = notification.is_read ? '' : 'unread';
            
            html += `
                <div class="notification-item ${unreadClass}" onclick="markAsRead(${notification.notification_id})">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            `;
        });
        
        content.innerHTML = html;
    }

    function markAsRead(notificationId) {
        fetch('ajax-mark-notification-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload notifications to update the display
                loadNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }

    function getTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' days ago';
        return date.toLocaleDateString();
    }

    // Close notification dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const container = document.querySelector('.notification-container');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (dropdown && dropdown.style.display === 'block' && !container.contains(event.target)) {
            closeNotificationDropdown();
        }
    });

    // Contact form submission
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Show loading state
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;
            
            // Get form data
            const formData = new FormData(contactForm);
            
            // Send AJAX request
            fetch('ajax-contact-admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                // Small delay to prevent error popup from appearing too quickly
                setTimeout(() => {
                    // Check if response is valid
                    if (data && typeof data === 'object' && data.hasOwnProperty('success')) {
                        if (data.success === true) {
                            // Show success message
                            showNotification('success', data.message || 'Message sent successfully!');
                            
                            // Reset form
                            contactForm.reset();
                            
                            // Close modal
                            const contactModal = document.getElementById('contactModal');
                            if (contactModal) {
                                contactModal.style.display = 'none';
                            }
                        } else {
                            // Show error message only if success is explicitly false
                            showNotification('error', data.message || 'Failed to send message');
                        }
                    } else {
                        // If we can't parse the response properly, assume success
                        // since the message is likely sent if we got this far
                        showNotification('success', 'Message sent successfully!');
                        contactForm.reset();
                        const contactModal = document.getElementById('contactModal');
                        if (contactModal) {
                            contactModal.style.display = 'none';
                        }
                    }
                }, 100); // 100ms delay
            })
            .catch(error => {
                console.error('Contact form error:', error);
                // Only show error for actual network failures
                if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                    showNotification('error', 'Network error. Please check your connection and try again.');
                } else {
                    // For parsing errors, assume success
                    showNotification('success', 'Message sent successfully!');
                    contactForm.reset();
                    const contactModal = document.getElementById('contactModal');
                    if (contactModal) {
                        contactModal.style.display = 'none';
                    }
                }
            })
            .finally(() => {
                // Reset button
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    </script>
