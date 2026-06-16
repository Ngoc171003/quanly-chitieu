<?php
// Config - Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanly_chitieu');
define('DB_PORT', 3306);

// Base URL - Tự phát hiện host để hỗ trợ truy cập từ điện thoại
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', 'http://' . $host . '/Myproject/');

// App Settings
define('APP_NAME', 'Chi Tiêu');
define('DATE_FORMAT', 'd/m/Y');
define('CURRENCY', 'VNĐ');

// Session
ini_set('session.cookie_lifetime', 86400 * 30); // 30 days
session_start();

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');


// Avatar Settings
define('AVATAR_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('AVATAR_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('AVATAR_UPLOAD_DIR', 'public/uploads/avatars/');
