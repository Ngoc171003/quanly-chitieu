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

// SMTP Email Settings (for budget alerts)
// Thay đổi thông tin dưới đây để gửi email thật
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', ''); // your-email@gmail.com
define('SMTP_PASS', ''); // your Gmail App Password
define('SMTP_FROM_NAME', 'Chi Tiêu App');

// Budget Alert Thresholds
define('BUDGET_WARNING_THRESHOLD', 80);  // Cảnh báo khi đạt 80%
define('BUDGET_EXCEEDED_THRESHOLD', 100); // Cảnh báo khi vượt 100%

// Avatar Settings
define('AVATAR_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('AVATAR_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('AVATAR_UPLOAD_DIR', 'public/uploads/avatars/');
