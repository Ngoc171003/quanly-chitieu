<?php
require_once 'app/config.php';
require_once 'app/Database.php';

echo "<h2>Migration V3 - Quản lý nhiều ví</h2>";
echo "<pre>";

$conn = $db->getConnection();

// 1. Create wallets table or alter existing one
echo "1. Kiểm tra và cập nhật bảng 'wallets'...\n";
$create_wallets_sql = "CREATE TABLE IF NOT EXISTS wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('cash', 'bank', 'ewallet') NOT NULL DEFAULT 'cash',
    initial_balance DECIMAL(15,2) DEFAULT 0.00,
    icon VARCHAR(50) DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_wallet_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($create_wallets_sql)) {
    echo "   ✅ Khởi tạo/kiểm tra bảng 'wallets' thành công!\n";
} else {
    echo "   ❌ Lỗi khởi tạo bảng 'wallets': " . $conn->error . "\n";
}

// Check columns of wallets table to apply modifications if it already existed
$columns_res = $conn->query("SHOW COLUMNS FROM wallets");
$columns = [];
while ($row = $columns_res->fetch_assoc()) {
    $columns[] = $row['Field'];
}

if (!in_array('type', $columns)) {
    echo "   ➕ Thêm cột 'type' vào bảng wallets...\n";
    $sql = "ALTER TABLE wallets ADD COLUMN type ENUM('cash', 'bank', 'ewallet') NOT NULL DEFAULT 'cash' AFTER name";
    if ($conn->query($sql)) {
        echo "      ✅ Đã thêm cột 'type'\n";
    } else {
        echo "      ❌ Lỗi thêm cột 'type': " . $conn->error . "\n";
    }
}

if (in_array('opening_balance', $columns)) {
    echo "   🔄 Đổi tên cột 'opening_balance' thành 'initial_balance'...\n";
    $sql = "ALTER TABLE wallets CHANGE COLUMN opening_balance initial_balance DECIMAL(15,2) DEFAULT 0.00";
    if ($conn->query($sql)) {
        echo "      ✅ Đã đổi tên cột thành công!\n";
    } else {
        echo "      ❌ Lỗi đổi tên cột: " . $conn->error . "\n";
    }
} elseif (!in_array('initial_balance', $columns)) {
    echo "   ➕ Thêm cột 'initial_balance' vào bảng wallets...\n";
    $sql = "ALTER TABLE wallets ADD COLUMN initial_balance DECIMAL(15,2) DEFAULT 0.00 AFTER type";
    if ($conn->query($sql)) {
        echo "      ✅ Đã thêm cột 'initial_balance'\n";
    } else {
        echo "      ❌ Lỗi thêm cột 'initial_balance': " . $conn->error . "\n";
    }
}

// 2. Add wallet_id to transactions table
echo "\n2. Thêm cột 'wallet_id' vào bảng transactions...\n";
$result = $conn->query("SHOW COLUMNS FROM transactions LIKE 'wallet_id'");
if ($result->num_rows == 0) {
    // Add wallet_id as nullable first so we can migrate existing transactions
    $sql = "ALTER TABLE transactions ADD COLUMN wallet_id INT DEFAULT NULL AFTER category_id";
    if ($conn->query($sql)) {
        echo "   ✅ Đã thêm cột 'wallet_id'\n";
    } else {
        echo "   ❌ Lỗi thêm cột 'wallet_id': " . $conn->error . "\n";
    }
} else {
    echo "   ⏭️ Cột 'wallet_id' đã tồn tại\n";
}

// 3. Migrate existing users and transactions
echo "\n3. Khởi tạo ví mặc định cho người dùng cũ và gán giao dịch...\n";
$users_result = $conn->query("SELECT id, username FROM users");
if ($users_result) {
    while ($user = $users_result->fetch_assoc()) {
        $u_id = $user['id'];
        $u_name = $user['username'];
        echo "   👤 Người dùng: $u_name (ID: $u_id)\n";
        
        // Check if user already has a default wallet
        $check_wallet_result = $conn->query("SELECT id FROM wallets WHERE user_id = $u_id AND is_default = 1 LIMIT 1");
        if ($check_wallet_result && $check_wallet_result->num_rows > 0) {
            $wallet = $check_wallet_result->fetch_assoc();
            $wallet_id = $wallet['id'];
            echo "      ⏭️ Người dùng đã có ví mặc định (ID: $wallet_id)\n";
        } else {
            // Create default wallet
            $insert_wallet_sql = "INSERT INTO wallets (user_id, name, type, initial_balance, icon, is_default) 
                                 VALUES ($u_id, 'Ví Tiền Mặt', 'cash', 0.00, 'fas fa-wallet', 1)";
            if ($conn->query($insert_wallet_sql)) {
                $wallet_id = $conn->insert_id;
                echo "      ✅ Đã tạo ví mặc định 'Ví Tiền Mặt' (ID: $wallet_id)\n";
            } else {
                echo "      ❌ Lỗi tạo ví mặc định: " . $conn->error . "\n";
                continue;
            }
        }
        
        // Update existing transactions for this user to point to this wallet if wallet_id is NULL
        $update_transactions_sql = "UPDATE transactions SET wallet_id = $wallet_id WHERE user_id = $u_id AND wallet_id IS NULL";
        if ($conn->query($update_transactions_sql)) {
            $affected = $conn->affected_rows;
            if ($affected > 0) {
                echo "      ✅ Đã cập nhật $affected giao dịch sang ví mới!\n";
            } else {
                echo "      ⏭️ Không có giao dịch cũ nào cần cập nhật.\n";
            }
        } else {
            echo "      ❌ Lỗi cập nhật giao dịch: " . $conn->error . "\n";
        }
    }
}

// 4. Clean up any existing foreign keys and make wallet_id NOT NULL
echo "\n4. Xử lý khóa ngoại cũ và chuyển cột 'wallet_id' sang NOT NULL...\n";
// Drop old foreign key if exists
$fk_check = $conn->query("SELECT * FROM information_schema.TABLE_CONSTRAINTS 
                          WHERE CONSTRAINT_SCHEMA = '" . DB_NAME . "' 
                          AND CONSTRAINT_NAME = 'fk_transaction_wallet' 
                          AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
if ($fk_check && $fk_check->num_rows > 0) {
    echo "   🗑️ Phát hiện khóa ngoại cũ 'fk_transaction_wallet', đang xóa...\n";
    if ($conn->query("ALTER TABLE transactions DROP FOREIGN KEY fk_transaction_wallet")) {
        echo "      ✅ Đã xóa khóa ngoại cũ!\n";
    } else {
        echo "      ❌ Lỗi xóa khóa ngoại cũ: " . $conn->error . "\n";
    }
}

// Set any remaining NULL values (just in case) to a default wallet
$conn->query("UPDATE transactions t JOIN wallets w ON t.user_id = w.user_id SET t.wallet_id = w.id WHERE t.wallet_id IS NULL AND w.is_default = 1");

// Make wallet_id NOT NULL
$alter_not_null_sql = "ALTER TABLE transactions MODIFY COLUMN wallet_id INT NOT NULL";
if ($conn->query($alter_not_null_sql)) {
    echo "   ✅ Đã đổi thuộc tính cột 'wallet_id' thành NOT NULL\n";
} else {
    echo "   ❌ Lỗi đổi thuộc tính cột 'wallet_id': " . $conn->error . "\n";
}

// Add foreign key constraint with ON DELETE CASCADE
echo "\n5. Thêm khóa ngoại mới với ON DELETE CASCADE...\n";
$add_fk_sql = "ALTER TABLE transactions ADD CONSTRAINT fk_transaction_wallet 
               FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE";
if ($conn->query($add_fk_sql)) {
    echo "   ✅ Đã thêm khóa ngoại fk_transaction_wallet thành công!\n";
} else {
    echo "   ❌ Lỗi thêm khóa ngoại: " . $conn->error . "\n";
}

echo "\n==========================================\n";
echo "✅ Migration V3 hoàn tất!\n";
echo "==========================================\n";
echo "</pre>";
echo "<p><a href='dashboard.php'>← Quay về Dashboard</a></p>";
?>
