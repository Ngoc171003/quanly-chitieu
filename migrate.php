<?php
/**
 * Database Migration Script
 * Chạy một lần để tự động cập nhật schema cho các tính năng mới
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'app/config.php';
require_once 'app/Database.php';

$db = new Database();
$db->connect();
$conn = $db->getConnection();

echo "<!DOCTYPE html>";
echo "<html lang='vi'>";
echo "<head><meta charset='UTF-8'><title>Database Migration</title>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; }</style>";
echo "</head><body>";
echo "<h1>🔄 Cập Nhật Database</h1>";

try {
    // 1. Kiểm tra bảng wallets
    echo "<h3>1️⃣ Kiểm tra/Tạo bảng Wallets...</h3>";
    $check = $conn->query("SHOW TABLES LIKE 'wallets'");
    
    if ($check->num_rows == 0) {
        echo "<p>⏳ Đang tạo bảng wallets...</p>";
        $sql = "CREATE TABLE wallets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            balance DECIMAL(15, 2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_wallet_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✅ Bảng wallets đã được tạo</p>";
        } else {
            echo "<p style='color: red;'>❌ Lỗi tạo bảng: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Bảng wallets đã tồn tại</p>";
    }
    
    // 2. Kiểm tra cột wallet_id trong transactions
    echo "<h3>2️⃣ Kiểm tra/Thêm cột wallet_id vào transactions...</h3>";
    $check = $conn->query("SHOW COLUMNS FROM transactions WHERE Field = 'wallet_id'");
    
    if ($check->num_rows == 0) {
        echo "<p>⏳ Đang thêm cột wallet_id...</p>";
        
        $sqls = [
            "ALTER TABLE transactions ADD COLUMN wallet_id INT NOT NULL DEFAULT 1 AFTER user_id",
            "ALTER TABLE transactions ADD CONSTRAINT fk_transaction_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT",
            "ALTER TABLE transactions ADD INDEX idx_user_wallet (user_id, wallet_id)"
        ];
        
        foreach ($sqls as $sql) {
            if ($conn->query($sql)) {
                echo "<p style='color: green;'>✅ " . substr($sql, 0, 50) . "...</p>";
            } else {
                echo "<p style='color: red;'>❌ Lỗi: " . $conn->error . "</p>";
            }
        }
    } else {
        echo "<p style='color: green;'>✅ Cột wallet_id đã tồn tại</p>";
    }
    
    // 3. Tạo ví mặc định cho mỗi người dùng
    echo "<h3>3️⃣ Kiểm tra/Tạo ví mặc định...</h3>";
    $users = $conn->query("SELECT id FROM users");
    $wallet_count = 0;
    
    while ($user = $users->fetch_assoc()) {
        $user_id = $user['id'];
        $check = $conn->query("SELECT id FROM wallets WHERE user_id = $user_id LIMIT 1");
        
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO wallets (user_id, name, balance) VALUES ($user_id, 'Ví Tiền Mặt', 0)");
            $wallet_count++;
        }
    }
    
    if ($wallet_count > 0) {
        echo "<p style='color: green;'>✅ Đã tạo $wallet_count ví mặc định</p>";
    } else {
        echo "<p style='color: green;'>✅ Tất cả người dùng đã có ví</p>";
    }
    
    // 4. Cập nhật wallet_id cho các giao dịch cũ (nếu có)
    echo "<h3>4️⃣ Cập nhật giao dịch cũ...</h3>";
    $check = $conn->query("SELECT COUNT(*) as cnt FROM transactions WHERE wallet_id = 0 OR wallet_id IS NULL");
    $count = $check->fetch_assoc()['cnt'];
    
    if ($count > 0) {
        $conn->query("UPDATE transactions t 
                     JOIN (SELECT user_id, MIN(id) as wallet_id FROM wallets GROUP BY user_id) w 
                     ON t.user_id = w.user_id 
                     SET t.wallet_id = w.wallet_id 
                     WHERE t.wallet_id = 0 OR t.wallet_id IS NULL");
        echo "<p style='color: green;'>✅ Đã cập nhật $count giao dịch</p>";
    } else {
        echo "<p style='color: green;'>✅ Tất cả giao dịch đã có wallet_id</p>";
    }
    
    echo "<h2 style='color: green;'>✅ Cập Nhật Database Hoàn Tất!</h2>";
    echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>← Quay lại Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</h2>";
}

echo "</body></html>";
?>
