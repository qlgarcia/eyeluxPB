<?php
require_once 'includes/header.php';

$page_title = 'Contact Us';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $inquiry_type = sanitizeInput($_POST['inquiry_type'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Submit concern using the new function
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $result = submitUserConcern($name, $email, $subject, $message, $user_id);
        
        if ($result['success']) {
            $success_message = $result['message'];
            
            // Log the contact form submission (optional)
            if (isLoggedIn()) {
                logActivity($_SESSION['user_id'], 'contact_form_submitted', "Subject: $subject");
            }
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<main>
    <div class="container">
        <div class="contact-page">
            <div class="page-header">
                <h1>Contact Us</h1>
                <p>We're here to help! Get in touch with our customer support team.</p>
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
            
            <div class="contact-layout">
                <!-- Contact Form -->
                <div class="contact-form-section">
                    <div class="contact-form-card">
                        <h2>Send us a Message</h2>
                        
                        <form method="POST" class="contact-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Full Name *</label>
                                    <input type="text" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="inquiry_type">Inquiry Type</label>
                                <select id="inquiry_type" name="inquiry_type">
                                    <option value="">Select an option</option>
                                    <option value="general" <?php echo ($inquiry_type ?? '') === 'general' ? 'selected' : ''; ?>>General Question</option>
                                    <option value="order" <?php echo ($inquiry_type ?? '') === 'order' ? 'selected' : ''; ?>>Order Support</option>
                                    <option value="product" <?php echo ($inquiry_type ?? '') === 'product' ? 'selected' : ''; ?>>Product Question</option>
                                    <option value="shipping" <?php echo ($inquiry_type ?? '') === 'shipping' ? 'selected' : ''; ?>>Shipping & Delivery</option>
                                    <option value="returns" <?php echo ($inquiry_type ?? '') === 'returns' ? 'selected' : ''; ?>>Returns & Exchanges</option>
                                    <option value="technical" <?php echo ($inquiry_type ?? '') === 'technical' ? 'selected' : ''; ?>>Technical Support</option>
                                    <option value="complaint" <?php echo ($inquiry_type ?? '') === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                                    <option value="feedback" <?php echo ($inquiry_type ?? '') === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <input type="text" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Message *</label>
                                <textarea id="message" name="message" rows="6" 
                                          placeholder="Please provide as much detail as possible..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="contact-info-section">
                    <div class="contact-info-card">
                        <h2>Get in Touch</h2>
                        
                        <div class="contact-methods">
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="method-content">
                                    <h3>Phone</h3>
                                    <p>+1 (555) 123-4567</p>
                                    <small>Mon-Fri: 9AM-6PM EST</small>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="method-content">
                                    <h3>Email</h3>
                                    <p>support@eyelux.com</p>
                                    <small>We respond within 24 hours</small>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="method-content">
                                    <h3>Address</h3>
                                    <p>123 Fashion Street<br>Style City, SC 12345</p>
                                    <small>Visit our showroom</small>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="method-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="method-content">
                                    <h3>Business Hours</h3>
                                    <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 4:00 PM</p>
                                    <small>Sunday: Closed</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="social-links">
                            <h3>Follow Us</h3>
                            <div class="social-icons">
                                <a href="#" class="social-link facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="social-link twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="social-link instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="social-link youtube">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ Section -->
                    <div class="faq-card">
                        <h2>Frequently Asked Questions</h2>
                        
                        <div class="faq-list">
                            <div class="faq-item">
                                <h4>How long does shipping take?</h4>
                                <p>Standard shipping takes 3-5 business days. Express shipping takes 1-2 business days.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4>What is your return policy?</h4>
                                <p>We offer a 30-day return policy for all items in original condition.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4>Do you offer international shipping?</h4>
                                <p>Yes, we ship to most countries worldwide. Shipping costs vary by location.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4>How do I track my order?</h4>
                                <p>You'll receive a tracking number via email once your order ships.</p>
                            </div>
                            
                            <div class="faq-item">
                                <h4>Can I change my order after placing it?</h4>
                                <p>You can modify or cancel your order within 1 hour of placing it.</p>
                            </div>
                        </div>
                        
                        <a href="faq.php" class="btn btn-outline">View All FAQs</a>
                    </div>
                </div>
            </div>
            
            <!-- Live Chat Section -->
            <div class="live-chat-section">
                <div class="live-chat-card">
                    <div class="chat-content">
                        <div class="chat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="chat-info">
                            <h3>Need Immediate Help?</h3>
                            <p>Chat with our support team in real-time</p>
                        </div>
                    </div>
                    <button class="btn btn-primary chat-btn">
                        <i class="fas fa-comment-dots"></i> Start Live Chat
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.contact-page {
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

.contact-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.contact-form-section,
.contact-info-section {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.contact-form-card,
.contact-info-card,
.faq-card {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.contact-form-card h2,
.contact-info-card h2,
.faq-card h2 {
    color: #2c3e50;
    margin-bottom: 25px;
    font-size: 24px;
}

.contact-form {
    max-width: 100%;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    font-family: inherit;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #e74c3c;
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.contact-methods {
    display: flex;
    flex-direction: column;
    gap: 25px;
    margin-bottom: 30px;
}

.contact-method {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.method-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e74c3c;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.method-content h3 {
    color: #2c3e50;
    margin-bottom: 5px;
    font-size: 16px;
}

.method-content p {
    color: #666;
    margin-bottom: 5px;
    line-height: 1.4;
}

.method-content small {
    color: #999;
    font-size: 12px;
}

.social-links h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 18px;
}

.social-icons {
    display: flex;
    gap: 15px;
}

.social-link {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: transform 0.3s;
}

.social-link:hover {
    transform: translateY(-2px);
}

.social-link.facebook { background: #3b5998; }
.social-link.twitter { background: #1da1f2; }
.social-link.instagram { background: #e4405f; }
.social-link.youtube { background: #ff0000; }

.faq-list {
    margin-bottom: 25px;
}

.faq-item {
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.faq-item:last-child {
    border-bottom: none;
}

.faq-item h4 {
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 16px;
}

.faq-item p {
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.live-chat-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    padding: 30px;
    color: white;
}

.live-chat-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-content {
    display: flex;
    align-items: center;
    gap: 20px;
}

.chat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.chat-info h3 {
    margin-bottom: 5px;
    font-size: 20px;
}

.chat-info p {
    opacity: 0.9;
    font-size: 14px;
}

.chat-btn {
    background: white;
    color: #667eea;
    border: none;
    padding: 15px 25px;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s;
}

.chat-btn:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
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

@media (max-width: 768px) {
    .contact-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .live-chat-card {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .contact-method {
        flex-direction: column;
        text-align: center;
    }
    
    .method-icon {
        align-self: center;
    }
    
    .social-icons {
        justify-content: center;
    }
}
</style>

<script>
// Live chat functionality (placeholder)
document.querySelector('.chat-btn').addEventListener('click', function() {
    alert('Live chat functionality would be implemented with a third-party service like Intercom or Zendesk');
});

// Form validation
document.getElementById('contact-form').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const subject = document.getElementById('subject').value.trim();
    const message = document.getElementById('message').value.trim();
    
    if (!name || !email || !subject || !message) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (!validateEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address.');
        return false;
    }
});

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}
</script>

<?php require_once 'includes/footer.php'; ?>








