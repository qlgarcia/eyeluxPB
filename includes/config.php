<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'eyelux_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_NAME', 'EyeLux');
define('SITE_URL', 'http://localhost');
define('UPLOAD_PATH', 'uploads/');

// Session configuration (only set if not in CLI mode and no session is active)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
    ini_set('session.cookie_lifetime', 86400); // 24 hours
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.save_handler', 'files');
    ini_set('session.save_path', sys_get_temp_dir());
    ini_set('session.auto_start', 0);
    session_start(); // Start the session safely
}

// Error reporting (set to 0 in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('America/New_York');
?>

