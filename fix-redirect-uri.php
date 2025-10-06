<?php
// Quick fix for redirect URI mismatch
// This will help identify the correct redirect URI

echo "<h2>🔧 Google OAuth Redirect URI Fix</h2>";

echo "<h3>Current Configuration:</h3>";
echo "<p><strong>Redirect URI in config:</strong> http://localhost/Eyewear1/google-callback.php</p>";

echo "<h3>Possible Solutions:</h3>";
echo "<ol>";
echo "<li><strong>Check Google Console:</strong> Go to <a href='https://console.cloud.google.com/' target='_blank'>Google Cloud Console</a></li>";
echo "<li><strong>Go to:</strong> APIs & Services > Credentials</li>";
echo "<li><strong>Click on your OAuth client</strong></li>";
echo "<li><strong>Check 'Authorized redirect URIs'</strong></li>";
echo "<li><strong>Make sure it contains:</strong> <code>http://localhost/Eyewear1/google-callback.php</code></li>";
echo "</ol>";

echo "<h3>Common Redirect URIs:</h3>";
echo "<ul>";
echo "<li><code>http://localhost/Eyewear1/google-callback.php</code></li>";
echo "<li><code>http://localhost/Eyewear1/google-callback-debug.php</code></li>";
echo "<li><code>http://127.0.0.1/Eyewear1/google-callback.php</code></li>";
echo "<li><code>http://127.0.0.1/Eyewear1/google-callback-debug.php</code></li>";
echo "</ul>";

echo "<h3>Test Links:</h3>";
echo "<p><a href='test-google-config.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Test Configuration</a></p>";

echo "<h3>Quick Fix:</h3>";
echo "<p>Add ALL possible redirect URIs to your Google Console:</p>";
echo "<textarea rows='4' cols='80' readonly>";
echo "http://localhost/Eyewear1/google-callback.php\n";
echo "http://localhost/Eyewear1/google-callback-debug.php\n";
echo "http://127.0.0.1/Eyewear1/google-callback.php\n";
echo "http://127.0.0.1/Eyewear1/google-callback-debug.php";
echo "</textarea>";

echo "<p><strong>Copy these URLs and add them all to your Google Console!</strong></p>";
?>
