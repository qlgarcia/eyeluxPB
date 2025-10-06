<?php
/**
 * Session cleanup tool to fix any session issues
 */

session_start();

echo "<h2>Session Cleanup Tool</h2>";

// Show current session
echo "<h3>Current Session State:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Session Data: <pre>" . print_r($_SESSION, true) . "</pre>";

// Clean session
if (isset($_GET['clean'])) {
    echo "<h3>Cleaning Session...</h3>";
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Start a fresh session
    session_start();
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "✅ Session cleaned and restarted!<br>";
    echo "New Session ID: " . session_id();
    echo "</div>";
}

// Force logout
if (isset($_GET['force_logout'])) {
    echo "<h3>Force Logout...</h3>";
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "✅ Force logout completed!<br>";
    echo "You are now logged out.";
    echo "</div>";
    
    echo "<script>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 2000);
    </script>";
    echo "Redirecting to login page in 2 seconds...";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
a { display: inline-block; padding: 10px 15px; margin: 5px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; }
a:hover { background: #005a87; }
pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>

<h3>Actions:</h3>
<p>
    <a href="?clean=1">Clean Session</a>
    <a href="?force_logout=1">Force Logout</a>
    <a href="login.php">Go to Login</a>
    <a href="index.php">Go to Home</a>
</p>

<p><strong>Instructions:</strong></p>
<ol>
    <li>If you're having login issues, click "Clean Session" first</li>
    <li>Then try logging in again</li>
    <li>If still having issues, use "Force Logout" and then try logging in</li>
</ol>




