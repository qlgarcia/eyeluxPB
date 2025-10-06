<?php
// Google OAuth callback handler
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/google_oauth_service.php';

// Start session only if none is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
$success_message = '';

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    try {
        $googleOAuth = new GoogleOAuthService();
        
        // Exchange code for access token
        $tokenData = $googleOAuth->getAccessToken($code);
        
        if ($tokenData && isset($tokenData['access_token'])) {
            // Get user info from Google
            $userInfo = $googleOAuth->getUserInfo($tokenData['access_token']);
            
            if ($userInfo) {
                $db = Database::getInstance();
                
                // Check if user already exists
                $existingUser = $db->fetchOne(
                    "SELECT * FROM users WHERE google_id = ? OR email = ?",
                    [$userInfo['id'], $userInfo['email']]
                );
                
                if ($existingUser) {
                    // Update existing user with Google ID if needed
                    if (!$existingUser['google_id']) {
                        $db->execute(
                            "UPDATE users SET google_id = ?, auth_provider = 'google', profile_picture_url = ?, email_verified = TRUE WHERE user_id = ?",
                            [$userInfo['id'], $userInfo['picture'] ?? null, $existingUser['user_id']]
                        );
                    }
                    
                    // Log user in
                    $_SESSION['user_id'] = $existingUser['user_id'];
                    $_SESSION['user_email'] = $existingUser['email'];
                    $_SESSION['user_name'] = $existingUser['first_name'] . ' ' . $existingUser['last_name'];
                    
                    // Migrate guest cart to user cart
                    migrateGuestCartToUser($existingUser['user_id'], session_id());
                    
                    $success_message = 'Successfully logged in with Google!';
                } else {
                    // Create new user
                    $user_id = $db->insert(
                        "INSERT INTO users (google_id, first_name, last_name, email, password, auth_provider, profile_picture_url, email_verified) VALUES (?, ?, ?, ?, '', 'google', ?, TRUE)",
                        [
                            $userInfo['id'],
                            $userInfo['given_name'] ?? '',
                            $userInfo['family_name'] ?? '',
                            $userInfo['email'],
                            $userInfo['picture'] ?? null
                        ]
                    );
                    
                    if ($user_id) {
                        // Log user in
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_email'] = $userInfo['email'];
                        $_SESSION['user_name'] = ($userInfo['given_name'] ?? '') . ' ' . ($userInfo['family_name'] ?? '');
                        
                        // Migrate guest cart to user cart
                        migrateGuestCartToUser($user_id, session_id());
                        
                        $success_message = 'Account created and logged in with Google!';
                    } else {
                        $error_message = 'Failed to create account. Please try again.';
                    }
                }
                
                // Redirect to home page
                if ($success_message) {
                    header('Location: index.php?google_login=success');
                    exit;
                }
            } else {
                $error_message = 'Failed to get user information from Google.';
            }
        } else {
            $error_message = 'Failed to get access token from Google.';
        }
        
    } catch (Exception $e) {
        $error_message = 'An error occurred during Google authentication.';
        error_log("Google OAuth error: " . $e->getMessage());
    }
} elseif (isset($_GET['error'])) {
    $error_message = 'Google authentication was cancelled or failed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Authentication - EyeLux Store</title>
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
        .auth-container {
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
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 10px;
            transition: background 0.3s;
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
    <div class="auth-container">
        <?php if ($success_message): ?>
            <div class="success">
                <div class="icon">✅</div>
                <h1>Authentication Successful!</h1>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
            <a href="index.php" class="btn">Go to Dashboard</a>
        <?php elseif ($error_message): ?>
            <div class="error">
                <div class="icon">❌</div>
                <h1>Authentication Failed</h1>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
            <a href="login.php" class="btn">Back to Login</a>
            <a href="register.php" class="btn btn-secondary">Back to Registration</a>
        <?php endif; ?>
    </div>
</body>
</html>
