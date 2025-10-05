<?php
// Google OAuth configuration
// IMPORTANT: You must configure these values with your actual Google OAuth credentials
// Get them from: https://console.cloud.google.com/

return [
    'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
    'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
    'redirect_uri' => 'http://localhost/pb/EYELUX_ORIG/google-callback.php',
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
MODERN GOOGLE OAUTH SETUP INSTRUCTIONS:
1. Go to https://console.cloud.google.com/
2. Create a new project or select existing
3. Go to APIs & Services > Credentials
4. Click "Create Credentials" > "OAuth client ID"
5. Select "Web application"
6. Add authorized redirect URI: http://localhost/pb/EYELUX_ORIG/google-callback.php
7. Copy Client ID and Client Secret
8. Replace YOUR_GOOGLE_CLIENT_ID and YOUR_GOOGLE_CLIENT_SECRET above
9. Test with: http://localhost/pb/EYELUX_ORIG/simple-google-test.php

NOTE: No APIs need to be enabled for basic OAuth login!
Google Identity services work without enabling specific APIs.
*/
?>
