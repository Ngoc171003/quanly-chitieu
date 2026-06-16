<?php
require_once 'app/config.php';
require_once 'app/Database.php';

echo "<h2>Migration V2 - Thêm Avatar, Currency, Budget Alerts</h2>";
echo "<pre>";

$conn = $db->getConnection();

// 1. Add avatar column to users table
echo "1. Thêm cột 'avatar' vào bảng users...\n";
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER full_name";
    if ($conn->query($sql)) {
        echo "   ✅ Đã thêm cột 'avatar'\n";
    } else {
        echo "   ❌ Lỗi: " . $conn->error . "\n";
    }
} else {
    echo "   ⏭️ Cột 'avatar' đã tồn tại\n";
}

// 2. Add currency column to users table
echo "\n2. Thêm cột 'currency' vào bảng users...\n";
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'currency'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN currency VARCHAR(10) DEFAULT 'VND' AFTER avatar";
    if ($conn->query($sql)) {
        echo "   ✅ Đã thêm cột 'currency'\n";
    } else {
        echo "   ❌ Lỗi: " . $conn->error . "\n";
    }
} else {
    echo "   ⏭️ Cột 'currency' đã tồn tại\n";
}

// 3. (Removed) - budget_alerts table no longer needed

// 4. Create uploads directory
echo "\n4. Tạo thư mục uploads/avatars...\n";
$avatar_dir = __DIR__ . '/public/uploads/avatars';
if (!is_dir($avatar_dir)) {
    if (mkdir($avatar_dir, 0755, true)) {
        echo "   ✅ Đã tạo thư mục: $avatar_dir\n";
    } else {
        echo "   ❌ Không thể tạo thư mục\n";
    }
} else {
    echo "   ⏭️ Thư mục đã tồn tại\n";
}

// Create .htaccess to protect uploads
$htaccess = $avatar_dir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Options -Indexes\n");
    echo "   ✅ Đã tạo .htaccess\n";
}

echo "\n==========================================\n";
echo "✅ Migration V2 hoàn tất!\n";
echo "==========================================\n";
echo "</pre>";
echo "<p><a href='dashboard.php'>← Quay về Dashboard</a></p>";
?>
