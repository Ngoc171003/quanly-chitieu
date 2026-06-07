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

// 3. Create budget_alerts table
echo "\n3. Tạo bảng 'budget_alerts'...\n";
$result = $conn->query("SHOW TABLES LIKE 'budget_alerts'");
if ($result->num_rows == 0) {
    $sql = "CREATE TABLE budget_alerts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        month INT NOT NULL,
        year INT NOT NULL,
        alert_type ENUM('warning','exceeded') NOT NULL,
        percentage DECIMAL(5,2) DEFAULT 0,
        budget_amount DECIMAL(15,2) DEFAULT 0,
        spent_amount DECIMAL(15,2) DEFAULT 0,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_alert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_alert (user_id, month, year, alert_type),
        INDEX idx_user_period (user_id, year, month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) {
        echo "   ✅ Đã tạo bảng 'budget_alerts'\n";
    } else {
        echo "   ❌ Lỗi: " . $conn->error . "\n";
    }
} else {
    echo "   ⏭️ Bảng 'budget_alerts' đã tồn tại\n";
}

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
