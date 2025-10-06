<?php
// Forgot password page
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/email_service.php';

$message = '';
$messageType = '';

if ($_POST && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    try {
        $db = Database::getInstance();
        
        // Check if user exists
        $user = $db->fetchOne(
            "SELECT user_id, first_name, email FROM users WHERE email = ?",
            [$email]
        );
        
        if ($user) {
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiryTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save reset token to database
            $result = $db->execute(
                "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?",
                [$resetToken, $expiryTime, $user['user_id']]
            );
            
            if ($result) {
                // Send reset email
                $emailService = new EmailService();
                $emailSent = $emailService->sendPasswordResetEmail($user['email'], $user['first_name'], $resetToken);
                
                if ($emailSent) {
                    $message = "Password reset instructions have been sent to your email address.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to send reset email. Please try again.";
                    $messageType = 'error';
                }
            } else {
                $message = "Failed to process reset request. Please try again.";
                $messageType = 'error';
            }
        } else {
            $message = "No account found with that email address.";
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = "An error occurred. Please try again.";
        $messageType = 'error';
        error_log("Forgot password error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EyeLux Store</title>
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
        .forgot-container {
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
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0,123,255,0.3);
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
    </style>
</head>
<body>
    <div class="forgot-container">
        <h1>Forgot Password?</h1>
        
        <?php if ($message): ?>
            <?php if ($messageType === 'success'): ?>
                <div class="success">
                    <div class="icon">✅</div>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <a href="login.php" class="btn">Back to Login</a>
            <?php else: ?>
                <div class="error">
                    <div class="icon">❌</div>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="info">
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email address">
                </div>
                <button type="submit" class="btn">Send Reset Link</button>
            </form>
        <?php endif; ?>
        
        <a href="login.php" class="btn btn-secondary">Back to Login</a>
        <a href="index.php" class="btn btn-secondary">Back to Home</a>
    </div>
</body>
</html>





