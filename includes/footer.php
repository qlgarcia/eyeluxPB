<?php
// Footer component
?>
    <footer class="footer" id="about-section">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>EyeLux</h3>
                    <p>Your premier destination for stylish eyewear. We offer a wide selection of sunglasses, eyeglasses, and accessories to enhance your vision and style.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Products</a></li>
                        <li><a href="categories.php">Categories</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Customer Service</h3>
                    <ul>
                        <li><a href="shipping.php">Shipping Info</a></li>
                        <li><a href="returns.php">Returns & Exchanges</a></li>
                        <li><a href="size-guide.php">Size Guide</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="support.php">Support</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>My Account</h3>
                    <ul>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="profile.php">My Profile</a></li>
                            <li><a href="orders.php">Order History</a></li>
                            <li><a href="wishlist.php">Wishlist</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-envelope"></i> info@eyelux.com</li>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Fashion Street, Style City, SC 12345</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> EyeLux. All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <style>
    .social-links {
        margin-top: 15px;
    }

    .social-links a {
        display: inline-block;
        margin-right: 15px;
        font-size: 20px;
        color: #bdc3c7;
        transition: color 0.3s;
    }

    .social-links a:hover {
        color: #e74c3c;
    }

    .footer-section ul li i {
        margin-right: 8px;
        width: 16px;
    }
    </style>
</body>
</html>



