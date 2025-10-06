<?php
// Email verification page with code input
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';

$message = '';
$messageType = '';
$showForm = true;


// Handle form submission
if ($_POST && isset($_POST['verification_code'])) {
    $verification_code = trim($_POST['verification_code']);
    $user_id = $_SESSION['pending_verification_user_id'] ?? $_SESSION['unverified_user_id'] ?? null;
    $is_login_verification = isset($_SESSION['unverified_user_id']);
    
    // Debug logging
    error_log("Verification attempt - user_id: " . ($user_id ?? 'null') . ", is_login_verification: " . ($is_login_verification ? 'true' : 'false'));
    error_log("Session unverified_user_id: " . ($_SESSION['unverified_user_id'] ?? 'not set'));
    error_log("Session pending_verification_user_id: " . ($_SESSION['pending_verification_user_id'] ?? 'not set'));
    
    
    if (empty($verification_code)) {
        $message = "Please enter the verification code.";
        $messageType = 'error';
    } else {
        // If no user_id in session, try to find user by verification code
        if (!$user_id) {
            try {
                $db = Database::getInstance();
                $user = $db->fetchOne(
                    "SELECT user_id, first_name, email, email_verified, verification_code_expires FROM users WHERE verification_code = ?",
                    [$verification_code]
                );
                
                if ($user) {
                    $user_id = $user['user_id'];
                    // Set session data for this user
                    $_SESSION['pending_verification_user_id'] = $user_id;
                }
            } catch (Exception $e) {
                error_log("Error finding user by verification code: " . $e->getMessage());
            }
        }
        
        if (!$user_id) {
            $message = "No pending verification found. Please register again or try logging in.";
            $messageType = 'error';
            $showForm = false;
        } else {
            try {
                $db = Database::getInstance();
                
                // Find user with this verification code
                $user = $db->fetchOne(
                    "SELECT user_id, first_name, email_verified, verification_code_expires FROM users WHERE user_id = ? AND verification_code = ?",
                    [$user_id, $verification_code]
                );
                
                if ($user) {
                    if ($user['email_verified']) {
                        $message = "Your email is already verified! You can now log in to your account.";
                        $messageType = 'success';
                        $showForm = false;
                    } elseif (strtotime($user['verification_code_expires']) < time()) {
                        $message = "Verification code has expired. Please register again to get a new code.";
                        $messageType = 'error';
                        $showForm = false;
                    } else {
                        // Verify the email
                        $result = $db->execute(
                            "UPDATE users SET email_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE user_id = ?",
                            [$user['user_id']]
                        );
                        
                        // Debug logging
                        error_log("Email verification update result for user {$user['user_id']}: " . ($result ? 'SUCCESS' : 'FAILED'));
                        
                        if ($result) {
                            error_log("Email verification update successful for user_id: {$user['user_id']}");
                            
                            if ($is_login_verification) {
                                error_log("Processing login verification flow");
                                
                                // Complete login process - get full user data first
                                $full_user = $db->fetchOne(
                                    "SELECT user_id, email, first_name, last_name FROM users WHERE user_id = ?",
                                    [$user['user_id']]
                                );
                                
                                if ($full_user) {
                                    error_log("Full user data retrieved for login verification");
                                    
                                    // Set a temporary flag to complete login on next page load
                                    $_SESSION['pending_login_user_id'] = $full_user['user_id'];
                                    
                                    // Debug logging
                                    error_log("Email verification completed - User ID: {$full_user['user_id']}, Email: {$full_user['email']}");
                                    
                                    // Verify the email_verified status was actually updated
                                    $verify_check = $db->fetchOne("SELECT email_verified FROM users WHERE user_id = ?", [$full_user['user_id']]);
                                    error_log("Email verification status after update: " . var_export($verify_check['email_verified'], true));
                                    
                                    // Clear unverified session variables
                                    unset($_SESSION['unverified_user_id'], $_SESSION['unverified_user_email'], $_SESSION['unverified_user_name']);
                                    
                                    // Set verification success flag
                                    $_SESSION['verification_success'] = true;
                                    
                                    error_log("Session variables set, redirecting to index.php");
                                    
                                    // Force session write and redirect
                                    session_write_close();
                                    header("Location: index.php?verified=1");
                                    exit();
                                } else {
                                    error_log("Failed to get full user data after verification - User ID: {$user['user_id']}");
                                    $message = "Failed to complete login process. Please try logging in again.";
                                    $messageType = 'error';
                                }
                            } else {
                                $message = "Email verified successfully! You can now log in to your account.";
                                unset($_SESSION['pending_verification_user_id']);
                            }
                            $messageType = 'success';
                            $showForm = false;
                        } else {
                            $message = "Failed to verify email. Please try again.";
                            $messageType = 'error';
                        }
                    }
                } else {
                    $message = "Invalid verification code. Please check your email and try again.";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = "An error occurred during verification. Please try again.";
                $messageType = 'error';
                error_log("Email verification error: " . $e->getMessage());
            }
        }
    }
}

// Handle resend code
if (isset($_POST['resend_code'])) {
    $user_id = $_SESSION['pending_verification_user_id'] ?? $_SESSION['unverified_user_id'] ?? null;
    $is_login_verification = isset($_SESSION['unverified_user_id']);
    
    // If no user_id in session, try to find the most recent unverified user
    if (!$user_id) {
        try {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT user_id, first_name, email FROM users WHERE email_verified = FALSE ORDER BY user_id DESC LIMIT 1"
            );
            
            if ($user) {
                $user_id = $user['user_id'];
                $_SESSION['pending_verification_user_id'] = $user_id;
            }
        } catch (Exception $e) {
            error_log("Error finding user for resend: " . $e->getMessage());
        }
    }
    
    if ($user_id) {
        try {
            $db = Database::getInstance();
            
            // Get user details
            $user = $db->fetchOne(
                "SELECT first_name, email FROM users WHERE user_id = ?",
                [$user_id]
            );
            
            if ($user) {
                // Generate new code
                $verification_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $verification_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Update code in database
                $db->execute(
                    "UPDATE users SET verification_code = ?, verification_code_expires = ? WHERE user_id = ?",
                    [$verification_code, $verification_expires, $user_id]
                );
                
                // Send new verification email
                require_once 'includes/email_service.php';
                $emailService = new EmailService();
                $emailSent = $emailService->sendVerificationEmail($user['email'], $user['first_name'], $verification_code);
                
                if ($emailSent) {
                    $message = "New verification code sent! Please check your email.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to send new verification code. Please try again.";
                    $messageType = 'error';
                }
            }
            
        } catch (Exception $e) {
            $message = "Failed to resend verification code. Please try again.";
            $messageType = 'error';
            error_log("Resend verification error: " . $e->getMessage());
        }
    } else {
        $message = "No pending verification found.";
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - EyeLux Store</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .success {
            color: #28a745;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .form-group {
            margin: 20px 0;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
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
        }
        .verification-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0,123,255,0.3);
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 10px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .success .icon {
            color: #28a745;
        }
        .error .icon {
            color: #dc3545;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .code-display {
            background: #007bff;
            color: white;
            font-size: 32px;
            font-weight: bold;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            letter-spacing: 5px;
        }
    </style>
</head>
<body>
    
    <div class="verification-container">
        <?php if ($messageType === 'success' && !$showForm): ?>
            <div class="success">
                <div class="icon">✅</div>
                <h1>Email Verified!</h1>
                <p><?php echo $message; ?></p>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php" class="btn btn-success">Go to Dashboard</a>
                <p style="margin-top: 15px; font-size: 14px; color: #666;">
                    You are already logged in! You don't need to log in again.
                </p>
            <?php elseif (isset($_SESSION['unverified_user_id'])): ?>
                <a href="index.php" class="btn btn-success">Go to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-success">Go to Login</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        <?php elseif ($showForm): ?>
            <h1>Verify Your Email</h1>
            
            <?php if ($message): ?>
                <div class="<?php echo $messageType; ?>">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="info">
                <?php if (isset($_SESSION['unverified_user_id'])): ?>
                    <p>We've sent a 6-digit verification code to your email address. Please enter it below to complete your login.</p>
                <?php elseif (isset($_SESSION['pending_verification_user_id'])): ?>
                    <p>We've sent a 6-digit verification code to your email address. Please enter it below to complete your registration.</p>
                <?php else: ?>
                    <p>Please enter the 6-digit verification code that was sent to your email address. If you don't have a code, please register or try logging in again.</p>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="verification_code">Verification Code</label>
                    <input type="text" id="verification_code" name="verification_code" 
                           class="verification-input" maxlength="6" pattern="[0-9]{6}" 
                           placeholder="000000" required>
                </div>
                <button type="submit" class="btn">Verify Email</button>
            </form>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="resend_code" class="btn btn-secondary">Resend Code</button>
            </form>
            
            <?php if (isset($_SESSION['unverified_user_id'])): ?>
                <a href="login.php" class="btn btn-secondary">Back to Login</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-secondary">Back to Registration</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="error">
                <div class="icon">❌</div>
                <h1>Verification Failed</h1>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <a href="register.php" class="btn">Try Again</a>
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-focus on verification code input
        document.getElementById('verification_code')?.focus();
        
        // Format input to only allow numbers
        document.getElementById('verification_code')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Auto-submit when 6 digits are entered
        document.getElementById('verification_code')?.addEventListener('input', function(e) {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>