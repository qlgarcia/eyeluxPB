<?php
// Password reset page
require_once 'includes/config.php';
require_once 'includes/database.php';

$message = '';
$messageType = '';
$showForm = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $db = Database::getInstance();
        
        // Check if token is valid and not expired
        $user = $db->fetchOne(
            "SELECT user_id, first_name FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()",
            [$token]
        );
        
        if ($user) {
            $showForm = true;
            
            // Handle form submission
            if ($_POST && isset($_POST['new_password'])) {
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                if ($newPassword !== $confirmPassword) {
                    $message = "Passwords do not match.";
                    $messageType = 'error';
                } elseif (strlen($newPassword) < 6) {
                    $message = "Password must be at least 6 characters long.";
                    $messageType = 'error';
                } else {
                    // Update password and clear reset token
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $result = $db->execute(
                        "UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?",
                        [$hashedPassword, $user['user_id']]
                    );
                    
                    if ($result) {
                        $message = "Password updated successfully! You can now log in with your new password.";
                        $messageType = 'success';
                        $showForm = false;
                    } else {
                        $message = "Failed to update password. Please try again.";
                        $messageType = 'error';
                    }
                }
            }
        } else {
            $message = "Invalid or expired reset token. Please request a new password reset.";
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = "An error occurred. Please try again.";
        $messageType = 'error';
        error_log("Password reset error: " . $e->getMessage());
    }
} else {
    $message = "No reset token provided.";
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EyeLux Store</title>
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
        .reset-container {
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
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($messageType === 'success'): ?>
            <div class="success">
                <div class="icon">✅</div>
                <h1>Password Reset Success!</h1>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <a href="login.php" class="btn">Go to Login</a>
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        <?php elseif ($showForm): ?>
            <h1>Reset Your Password</h1>
            <?php if ($message): ?>
                <div class="<?php echo $messageType; ?>">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php else: ?>
            <div class="error">
                <div class="icon">❌</div>
                <h1>Reset Failed</h1>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            <a href="forgot-password.php" class="btn">Request New Reset</a>
            <a href="index.php" class="btn btn-secondary">Back to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>





