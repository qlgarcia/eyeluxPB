<?php
// Google OAuth configuration
// IMPORTANT: Configure BOTH development (localhost) and production credentials below.
// Get them from: https://console.cloud.google.com/

// Detect host and scheme to choose correct credentials and build accurate redirect URI
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
$scheme = $is_https ? 'https' : 'http';

// Environment detection: treat InfinityFree domain as production
$is_production = (stripos($host, 'infinityfreeapp.com') !== false);

if ($is_production) {
    // PRODUCTION (eyelux.infinityfreeapp.com) — use environment variables for security
    $client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? 'YOUR_PRODUCTION_GOOGLE_CLIENT_ID';
    $client_secret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'YOUR_PRODUCTION_GOOGLE_CLIENT_SECRET';
    $redirect_uri = $scheme . '://' . $host . '/google-callback.php';
} else {
    // DEVELOPMENT (localhost) — use environment variables for security
    $client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? 'YOUR_DEVELOPMENT_GOOGLE_CLIENT_ID';
    $client_secret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'YOUR_DEVELOPMENT_GOOGLE_CLIENT_SECRET';
    $redirect_uri = 'http://localhost/pb/EYELUX_ORIG/google-callback.php';
}

return [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'scopes' => [
        'openid',
        'email',
        'profile'
    ],
    'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url' => 'https://oauth2.googleapis.com/token',
    'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo'
];

/*
SECURE GOOGLE OAUTH SETUP INSTRUCTIONS:

1. Go to https://console.cloud.google.com/
2. Create a new project or select existing
3. Go to APIs & Services > Credentials
4. Click "Create Credentials" > "OAuth client ID"
5. Select "Web application"
6. Add authorized redirect URI (DEV): http://localhost/pb/EYELUX_ORIG/google-callback.php
   Add authorized redirect URI (PROD): https://eyelux.infinityfreeapp.com/google-callback.php
7. Copy Client ID and Client Secret

SECURE CONFIGURATION (RECOMMENDED):
8. Set environment variables:
   - GOOGLE_CLIENT_ID=your_actual_client_id
   - GOOGLE_CLIENT_SECRET=your_actual_client_secret

LOCAL DEVELOPMENT:
   Create a .env file in your project root with:
   GOOGLE_CLIENT_ID=your_development_client_id
   GOOGLE_CLIENT_SECRET=your_development_client_secret

PRODUCTION (InfinityFree):
   Set environment variables in your hosting panel:
   GOOGLE_CLIENT_ID=your_production_client_id
   GOOGLE_CLIENT_SECRET=your_production_client_secret

9. Test with (DEV): http://localhost/pb/EYELUX_ORIG/simple-google-test.php
   Test with (PROD): http://eyelux.infinityfreeapp.com/login.php

NOTE: No APIs need to be enabled for basic OAuth login!
Google Identity services work without enabling specific APIs.

SECURITY: Never commit real credentials to version control!
Use environment variables or secure configuration files.
*/
?>
