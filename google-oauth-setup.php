<?php
// Google OAuth Setup Guide
echo "<h2>🔧 Google OAuth Setup Guide</h2>";

echo "<h3>Step 1: Create Google Cloud Project</h3>";
echo "<ol>";
echo "<li>Go to <a href='https://console.cloud.google.com/' target='_blank'>Google Cloud Console</a></li>";
echo "<li>Click 'Select a project' → 'New Project'</li>";
echo "<li>Enter project name: 'EyeLux Store'</li>";
echo "<li>Click 'Create'</li>";
echo "</ol>";

echo "<h3>Step 2: Create OAuth Credentials (No APIs needed!)</h3>";
echo "<ol>";
echo "<li>Go to 'APIs & Services' → 'Credentials'</li>";
echo "<li>Click '+ Create Credentials' → 'OAuth client ID'</li>";
echo "<li>Select 'Web application'</li>";
echo "<li>Enter name: 'EyeLux Store Web Client'</li>";
echo "<li>Add authorized redirect URI: <code>http://localhost/Eyewear1/google-callback.php</code></li>";
echo "<li>Click 'Create'</li>";
echo "<li>Copy the Client ID and Client Secret</li>";
echo "</ol>";

echo "<h3>Step 3: Update Configuration</h3>";
echo "<p>Update <code>includes/google_config.php</code> with your credentials:</p>";
echo "<pre>";
echo "<?php\n";
echo "return [\n";
echo "    'client_id' => 'YOUR_CLIENT_ID_HERE.apps.googleusercontent.com',\n";
echo "    'client_secret' => 'YOUR_CLIENT_SECRET_HERE',\n";
echo "    'redirect_uri' => 'http://localhost/Eyewear1/google-callback.php',\n";
echo "    // ... rest of config\n";
echo "];\n";
echo "</pre>";

echo "<h3>Step 2: Create OAuth Credentials (No APIs needed!)</h3>";
echo "<ol>";
echo "<li>Go to 'APIs & Services' → 'Credentials'</li>";
echo "<li>Click '+ Create Credentials' → 'OAuth client ID'</li>";
echo "<li>Select 'Web application'</li>";
echo "<li>Enter name: 'EyeLux Store Web Client'</li>";
echo "<li>Add authorized redirect URI: <code>http://localhost/Eyewear1/google-callback.php</code></li>";
echo "<li>Click 'Create'</li>";
echo "<li>Copy the Client ID and Client Secret</li>";
echo "</ol>";

echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<strong>✅ Good News:</strong> You don't need to enable any APIs! Google Identity services work out of the box.";
echo "</div>";
echo "<p>After updating the config, test the Google login button.</p>";

echo "<h3>Current Configuration Status:</h3>";
$config = require 'includes/google_config.php';
echo "<ul>";
echo "<li>Client ID: " . (strpos($config['client_id'], 'YOUR_') === false ? "✅ Configured" : "❌ Not configured") . "</li>";
echo "<li>Client Secret: " . (strpos($config['client_secret'], 'YOUR_') === false ? "✅ Configured" : "❌ Not configured") . "</li>";
echo "<li>Redirect URI: " . $config['redirect_uri'] . "</li>";
echo "</ul>";

echo "<h3>Common Issues:</h3>";
echo "<ul>";
echo "<li><strong>Error 401: invalid_client</strong> - Client ID/Secret not configured correctly</li>";
echo "<li><strong>Redirect URI mismatch</strong> - Make sure the URI in Google Console matches exactly</li>";
echo "<li><strong>Scope errors</strong> - Modern Google OAuth uses 'openid', 'email', 'profile' scopes</li>";
echo "</ul>";

echo "<h3>Need Help?</h3>";
echo "<p>If you're still having issues, check the <a href='https://developers.google.com/identity/protocols/oauth2/web-server' target='_blank'>Google OAuth2 Documentation</a></p>";
?>
