<?php
// Config - Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanly_chitieu');
define('DB_PORT', 3306);

// Base URL - Tự phát hiện host, giao thức (HTTP/HTTPS) và thư mục con của dự án để không bị lỗi khi đổi tên hoặc di chuyển thư mục
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$protocol = 'http://';
if ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) || 
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
    $protocol = 'https://';
}

// Tự động phát hiện thư mục con của dự án trong URL
$project_root = str_replace('\\', '/', dirname(__DIR__));
$script_filename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$base_path = '';

if (!empty($project_root) && !empty($script_filename) && stripos($script_filename, $project_root) === 0) {
    $relative_path = substr($script_filename, strlen($project_root));
    if (!empty($relative_path) && substr($script_name, -strlen($relative_path)) === $relative_path) {
        $base_path = substr($script_name, 0, strlen($script_name) - strlen($relative_path));
    }
}

if (empty($base_path)) {
    $project_folder_name = basename($project_root);
    $pos = stripos($script_name, '/' . $project_folder_name . '/');
    if ($pos !== false) {
        $base_path = substr($script_name, 0, $pos + strlen($project_folder_name) + 1);
    } else {
        $base_path = '/' . $project_folder_name . '/';
    }
}

$base_path = '/' . trim(str_replace('\\', '/', $base_path), '/') . '/';
if ($base_path === '//') {
    $base_path = '/';
}

define('BASE_URL', $protocol . $host . $base_path);

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
