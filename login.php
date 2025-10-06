<?php
// Include necessary files first
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/google_oauth_service.php';

$page_title = 'Login';

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
$success_message = getFlashMessage('success');
$login_successful = false;

// Login processing

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!validateEmail($email)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Use direct database query instead of getUserByEmail to avoid filtering issues
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            
            // Check if email is verified
            if (!$user['email_verified'] || $user['email_verified'] == '0' || $user['email_verified'] === 0) {
                // Store user info for verification
                $_SESSION['pending_verification_user'] = [
                    'user_id' => $user['user_id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ];
                
                // Redirect to verification page
                header("Location: verify-email.php?email=" . urlencode($email));
                exit();
            }
            
            // Check if user is active, if not, activate them
            if (!$user['is_active']) {
                $db->execute("UPDATE users SET is_active = 1 WHERE user_id = ?", [$user['user_id']]);
                $user['is_active'] = 1; // Update the user array
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Session is set - redirect immediately
            $redirect = $_GET['redirect'] ?? 'index.php';
            
            // Set flag to prevent HTML rendering
            $login_successful = true;
            
            // Simple redirect
            header("Location: $redirect");
            exit();
            
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}

// Include header after all redirects are handled
if (!$login_successful) {
    require_once 'includes/header.php';
}
?>

<?php if (!$login_successful): ?>
<main>
    <div class="container" style="max-width: 400px; margin: 50px auto; padding: 0 20px;">
        <div class="login-form">
            <h2 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">Login to Your Account</h2>
            
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
            
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group" style="text-align: left;">
                    <label style="display: inline-flex; align-items: center; font-weight: normal; white-space: nowrap; justify-content: flex-start;">
                        <input type="checkbox" name="remember" style="margin-right: 5px; flex-shrink: 0;">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginSubmitBtn" style="width: 100%; margin-bottom: 20px;">
                    Login
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
                <p style="margin-bottom: 15px;">
                    <a href="forgot-password.php" style="color: #e74c3c;">Forgot your password?</a>
                </p>
                <p>
                    Don't have an account? 
                    <a href="register.php" style="color: #e74c3c; font-weight: 600;">Sign up here</a>
                </p>
            </div>
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

.login-form {
    background: var(--bg-primary);
    padding: 40px;
    border-radius: 20px;
    box-shadow: var(--shadow-subtle);
    border: 1px solid var(--border-light);
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
    border-radius: 8px;
    font-weight: 500;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.btn-primary {
    background: var(--sage);
    color: white;
}

.btn-primary:hover {
    background: var(--terracotta);
    transform: translateY(-2px);
    box-shadow: var(--shadow-subtle);
}

.google-login-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-light);
    padding: 12px 24px;
    border-radius: 8px;
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

/* Modal animations */
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Modal close button hover effect */
#closeBanModal:hover {
    background: rgba(255,255,255,0.2) !important;
    border-radius: 50%;
}

/* Pulse animation for ban icon */
@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}
</style>

<!-- Ban Modal -->
<div id="banModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
    <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 0; border: none; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: modalSlideIn 0.3s ease-out;">
        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 25px; border-radius: 15px 15px 0 0; text-align: center; position: relative;">
            <h2 style="margin: 0; font-size: 28px; font-weight: bold;">🚫 Account Banned</h2>
            <button id="closeBanModal" style="position: absolute; top: 15px; right: 20px; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 30px; text-align: center;">
            <div style="font-size: 64px; margin-bottom: 20px; animation: pulse 2s infinite;">🚫</div>
            <h3 style="color: #dc3545; margin-bottom: 20px; font-size: 24px; font-weight: bold;">Your Account Has Been Banned</h3>
            <p style="color: #666; margin-bottom: 25px; line-height: 1.6; font-size: 16px;">
                You can no longer access this account due to violations of our terms of service.
            </p>
            <p style="color: #dc3545; margin-bottom: 25px; font-weight: bold; font-size: 18px;">
                If you wish to appeal this decision, please contact the admin.
            </p>
            
            <div id="banDetails" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px; text-align: left;">
                <h4 style="margin-top: 0; color: #333;">Ban Details:</h4>
                <p><strong>Reason:</strong> <span id="banReason">-</span></p>
                <p><strong>Date:</strong> <span id="banDate">-</span></p>
                <p><strong>Warnings:</strong> <span id="warningCount">-</span></p>
            </div>
            
            <p style="color: #666; margin-bottom: 25px;">
                If you believe this is an error or wish to appeal this decision, please contact our admin team.
            </p>
            
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button id="closeBanModal" class="btn btn-secondary" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    Close
                </button>
                <button id="contactAdminBtn" class="btn btn-primary" style="background: #007bff; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    Contact Admin
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Contact Admin Modal -->
<div id="contactAdminModal" class="modal" style="display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 0; border: none; border-radius: 10px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;">
            <h2 style="margin: 0; font-size: 24px;">📧 Contact Admin</h2>
        </div>
        <div class="modal-body" style="padding: 30px;">
            <form id="contactAdminForm">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="contactEmail" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Your Email</label>
                    <input type="email" id="contactEmail" name="email" required 
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;"
                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="contactSubject" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Subject</label>
                    <input type="text" id="contactSubject" name="subject" required 
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;"
                           value="Appeal for Account Unban">
                </div>
                
                <div class="form-group" style="margin-bottom: 25px;">
                    <label for="contactMessage" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Message</label>
                    <textarea id="contactMessage" name="message" required rows="6" 
                              style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; resize: vertical;"
                              placeholder="Please explain why you believe your account should be unbanned..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="button" id="closeContactModal" class="btn btn-secondary" style="background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;">
                        Cancel
                    </button>
                    <button type="submit" id="submitContact" class="btn btn-primary" style="background: #007bff; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px;">
                        Send Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if user was banned
    <?php if ($error_message === 'banned' && isset($ban_details)): ?>
        showBanModal();
    <?php endif; ?>
    
    // Ban modal functions
    function showBanModal() {
        document.getElementById('banModal').style.display = 'block';
        
        // Populate ban details
        document.getElementById('banReason').textContent = '<?php echo htmlspecialchars($ban_details['reason'] ?? 'No reason provided'); ?>';
        document.getElementById('banDate').textContent = '<?php echo date('M j, Y g:i A', strtotime($ban_details['date'] ?? 'now')); ?>';
        document.getElementById('warningCount').textContent = '<?php echo $ban_details['warnings'] ?? 0; ?>';
    }
    
    // Close ban modal
    document.getElementById('closeBanModal').addEventListener('click', function() {
        document.getElementById('banModal').style.display = 'none';
    });
    
    // Contact admin button
    document.getElementById('contactAdminBtn').addEventListener('click', function() {
        document.getElementById('banModal').style.display = 'none';
        document.getElementById('contactAdminModal').style.display = 'block';
    });
    
    // Close contact modal
    document.getElementById('closeContactModal').addEventListener('click', function() {
        document.getElementById('contactAdminModal').style.display = 'none';
    });
    
    // Contact form submission
    document.getElementById('contactAdminForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitContact');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Sending...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        formData.append('action', 'contact_admin');
        
        fetch('ajax-contact-admin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Your request has been sent to the admin team. You will be contacted soon.');
                document.getElementById('contactAdminModal').style.display = 'none';
                document.getElementById('contactAdminForm').reset();
            } else {
                alert('Error sending request: ' + (data.message || 'Please try again.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending request. Please try again.');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Login form - no JavaScript interference
    console.log('Login page loaded');
    
    // Close modals when clicking outside (except ban modal - it should not be dismissible)
    window.addEventListener('click', function(event) {
        const contactModal = document.getElementById('contactAdminModal');
        
        if (event.target === contactModal) {
            contactModal.style.display = 'none';
        }
    });
});
</script>

<?php 
require_once 'includes/footer.php'; 
endif; // Close the !$login_successful condition
?>


