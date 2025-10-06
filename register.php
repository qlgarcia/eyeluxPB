<?php
require_once 'includes/header.php';
require_once 'includes/email_service.php';
require_once 'includes/google_oauth_service.php';

$page_title = 'Register';

// Helper function to get Google auth URL
function getGoogleAuthUrl() {
    $googleOAuth = new GoogleOAuthService();
    return $googleOAuth->getAuthUrl();
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $existing_user = getUserByEmail($email);
        if ($existing_user) {
            $error_message = 'An account with this email already exists.';
        } else {
            // Create new user with email verification code
            $db = Database::getInstance();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $verification_expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            try {
                $user_id = $db->insert(
                    "INSERT INTO users (first_name, last_name, email, password, phone, verification_code, verification_code_expires) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$first_name, $last_name, $email, $hashed_password, $phone, $verification_code, $verification_expires]
                );
                
                if ($user_id) {
                    // Send verification email with code
                    $emailService = new EmailService();
                    $emailSent = $emailService->sendVerificationEmail($email, $first_name, $verification_code);
                    
                    if ($emailSent) {
                        $success_message = 'Account created successfully! Please check your email for the 6-digit verification code.';
                    } else {
                        $success_message = 'Account created successfully! Email sending failed, but you can still verify with the code: <strong>' . $verification_code . '</strong>';
                    }
                    
                    // Store user ID in session for verification
                    $_SESSION['pending_verification_user_id'] = $user_id;
                    // Set flag to show verification modal
                    $show_verification_modal = true;
                } else {
                    $error_message = 'Failed to create account. Please try again.';
                }
            } catch (Exception $e) {
                $error_message = 'Error: ' . $e->getMessage();
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
}
?>

<main>
    <div class="container" style="max-width: 500px; margin: 50px auto; padding: 0 20px;">
        <div class="register-form">
            <h2 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">Create Your Account</h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <small style="color: #666; font-size: 12px;">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary" id="registerSubmitBtn" style="width: 100%; margin-bottom: 20px;">
                    Create Account
                </button>
            </form>
            
            <div style="text-align: center; margin: 20px 0;">
                <div style="display: flex; align-items: center; margin: 20px 0;">
                    <div style="flex: 1; height: 1px; background: #ddd;"></div>
                    <span style="padding: 0 15px; color: #666;">OR</span>
                    <div style="flex: 1; height: 1px; background: #ddd;"></div>
                </div>
                
                <a href="<?php echo getGoogleAuthUrl(); ?>" class="google-login-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" style="margin-right: 8px;">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </a>
            </div>
            
            <div style="text-align: center;">
                <p>
                    Already have an account? 
                    <a href="login.php" style="color: #e74c3c; font-weight: 600;">Login here</a>
                </p>
            </div>
        </div>
    </div>
</main>

<!-- Email Verification Modal -->
<div id="verificationModal" class="modal" style="display: none;">
    <div class="modal-content" style="background: var(--bg-primary); border: 1px solid var(--border-light); border-radius: 20px; box-shadow: var(--shadow-subtle);">
        <div class="modal-header" style="background: var(--gradient-accent); color: white; border-radius: 20px 20px 0 0;">
            <h2 style="color: white;">Verify Your Email</h2>
            <span class="close" onclick="closeVerificationModal()" style="color: white;">&times;</span>
        </div>
        <div class="modal-body" style="background: var(--bg-primary);">
            <div class="verification-info">
                <p>We've sent a 6-digit verification code to your email address. Please enter it below to complete your registration.</p>
            </div>
            
            <form id="verificationForm">
                <div class="form-group">
                    <label for="verification_code">Verification Code</label>
                    <input type="text" id="verification_code" name="verification_code" 
                           class="verification-input" maxlength="6" pattern="[0-9]{6}" 
                           placeholder="000000" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Verify Email</button>
                    <button type="button" class="btn btn-secondary" onclick="resendVerificationCode()">Resend Code</button>
                </div>
            </form>
            
            <div id="verificationMessage" class="verification-message" style="display: none;"></div>
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

.register-form {
    background: var(--bg-primary);
    padding: 40px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.form-group input:focus {
    outline: none;
    border-color: var(--sage);
    box-shadow: 0 0 0 3px rgba(156, 175, 136, 0.1);
    background: var(--bg-primary);
}

.btn {
    padding: 12px 24px;
    border-radius: 5px;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #e74c3c;
    color: white;
}

.btn-primary:hover {
    background: #c0392b;
}

.google-login-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    color: #333;
    border: 1px solid #dadce0;
    padding: 12px 24px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.google-login-btn:hover {
    background: #f8f9fa;
    border-color: #c1c7cd;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    text-decoration: none;
    color: #333;
}

@media (max-width: 600px) {
    .register-form > form > div:first-child {
        grid-template-columns: 1fr !important;
    }
}

/* Verification Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
    color: #2c3e50;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    line-height: 1;
}

.close:hover {
    color: #000;
}

.modal-body {
    padding: 30px;
}

.verification-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.verification-input {
    width: 100%;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 10px;
    font-size: 24px;
    text-align: center;
    letter-spacing: 5px;
    font-weight: bold;
    box-sizing: border-box;
    margin-bottom: 20px;
}

.verification-input:focus {
    outline: none;
    border-color: #e74c3c;
    box-shadow: 0 0 10px rgba(231,76,60,0.3);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.form-actions .btn {
    flex: 1;
    max-width: 150px;
}

.verification-message {
    margin-top: 20px;
    padding: 15px;
    border-radius: 5px;
    text-align: center;
}

.verification-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.verification-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Show verification modal if needed
<?php if (isset($show_verification_modal) && $show_verification_modal): ?>
document.addEventListener('DOMContentLoaded', function() {
    showVerificationModal();
});
<?php endif; ?>

// Verification modal functions
function showVerificationModal() {
    document.getElementById('verificationModal').style.display = 'flex';
    document.getElementById('verification_code').focus();
}

function closeVerificationModal() {
    document.getElementById('verificationModal').style.display = 'none';
}

// Verification form submission
document.getElementById('verificationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const verificationCode = document.getElementById('verification_code').value;
    const messageDiv = document.getElementById('verificationMessage');
    
    if (verificationCode.length !== 6) {
        showMessage('Please enter a 6-digit verification code.', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Verifying...';
    submitBtn.disabled = true;
    
    // Send verification request
    fetch('ajax-verify-email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'verification_code=' + encodeURIComponent(verificationCode)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 2000);
            }
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
});

// Resend verification code
function resendVerificationCode() {
    const messageDiv = document.getElementById('verificationMessage');
    
    fetch('ajax-verify-email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'resend_code=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred. Please try again.', 'error');
    });
}

// Show message in modal
function showMessage(message, type) {
    const messageDiv = document.getElementById('verificationMessage');
    messageDiv.textContent = message;
    messageDiv.className = 'verification-message ' + type;
    messageDiv.style.display = 'block';
}

// Format verification code input
document.getElementById('verification_code').addEventListener('input', function(e) {
    // Only allow numbers
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // Auto-submit when 6 digits are entered
    if (this.value.length === 6) {
        document.getElementById('verificationForm').dispatchEvent(new Event('submit'));
    }
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('verificationModal');
    if (event.target === modal) {
        closeVerificationModal();
    }
});

// Register form loading
const registerForm = document.getElementById('registerForm');
const registerSubmitBtn = document.getElementById('registerSubmitBtn');

if (registerForm && registerSubmitBtn) {
    registerForm.addEventListener('submit', function(e) {
        setFormLoading(registerForm, true);
        setButtonLoading(registerSubmitBtn, true);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>


