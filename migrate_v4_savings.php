<?php
require_once 'app/config.php';
require_once 'app/Database.php';

echo "<h2>Migration V4 - Mục tiêu tiết kiệm</h2>";
echo "<pre>";

$conn = $db->getConnection();

echo "1. Khởi tạo bảng 'savings_goals'...\n";
$create_savings_goals_sql = "CREATE TABLE IF NOT EXISTS savings_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL,
    current_amount DECIMAL(15,2) DEFAULT 0.00,
    target_date DATE NOT NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_savings_goal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_savings_goal_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($create_savings_goals_sql)) {
    echo "   ✅ Tạo/kiểm tra bảng 'savings_goals' thành công!\n";
} else {
    echo "   ❌ Lỗi tạo bảng 'savings_goals': " . $conn->error . "\n";
}

echo "\n==========================================\n";
echo "✅ Migration V4 hoàn tất!\n";
echo "==========================================\n";
echo "</pre>";
echo "<p><a href='dashboard.php'>← Quay về Dashboard</a></p>";
?>
