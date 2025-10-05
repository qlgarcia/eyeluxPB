<?php
// Simple Google OAuth test
require_once 'includes/google_config.php';

$config = include 'includes/google_config.php';

echo "<h2>🔍 Google OAuth Test</h2>";

echo "<h3>1. Configuration:</h3>";
echo "<p><strong>Client ID:</strong> " . htmlspecialchars(substr($config['client_id'], 0, 20)) . "...</p>";
echo "<p><strong>Client Secret:</strong> " . htmlspecialchars(substr($config['client_secret'], 0, 10)) . "...</p>";
echo "<p><strong>Redirect URI:</strong> " . htmlspecialchars($config['redirect_uri']) . "</p>";

echo "<h3>2. Test Authorization URL:</h3>";
$authUrl = $config['auth_url'] . '?' . http_build_query([
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'scope' => implode(' ', $config['scopes']),
    'response_type' => 'code',
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

echo "<p><a href='" . htmlspecialchars($authUrl) . "' style='background: #4285f4; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🔗 Test Google Login</a></p>";

echo "<h3>3. Current URL:</h3>";
echo "<p>" . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</p>";

if (isset($_GET['code'])) {
    echo "<h3>4. Authorization Code Received:</h3>";
    echo "<p><strong>Code:</strong> " . htmlspecialchars(substr($_GET['code'], 0, 20)) . "...</p>";
    echo "<p><a href='google-callback.php?code=" . urlencode($_GET['code']) . "'>🔍 Test Callback</a></p>";
}

if (isset($_GET['error'])) {
    echo "<h3>4. OAuth Error:</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($_GET['error']) . "</p>";
    if (isset($_GET['error_description'])) {
        echo "<p><strong>Description:</strong> " . htmlspecialchars($_GET['error_description']) . "</p>";
    }
}

echo "<h3>5. Debug Links:</h3>";
echo "<p><a href='google-callback-debug.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔍 Debug Callback</a></p>";
echo "<p><a href='fix-redirect-uri.php' style='background: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔧 Fix Redirect URI</a></p>";
?>





