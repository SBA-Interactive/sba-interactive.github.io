<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sba_database');
define('DB_USER', 'sba_user');
define('DB_PASS', 'sba_password');

// Email configuration
define('ADMIN_EMAIL', 'contact@sbainteractive.com');
define('EMAIL_FROM', 'no-reply@sbainteractive.com');
define('EMAIL_SUBJECT_PREFIX', '[SBA Interactive] ');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
