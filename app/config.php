<?php
// Config - Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanly_chitieu');
define('DB_PORT', 3306);

// Base URL
define('BASE_URL', 'http://localhost/Myproject/');

// App Settings
define('APP_NAME', 'Chi Tiêu');
define('DATE_FORMAT', 'd/m/Y');
define('CURRENCY', 'VNĐ');

// Session
ini_set('session.cookie_lifetime', 86400 * 30); // 30 days
session_start();

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');
