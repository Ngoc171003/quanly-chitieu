<?php
// Test setup untuk kiểm tra cấu hình và lỗi database
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'app/config.php';
require_once 'app/Database.php';

echo "=== TEST SETUP ===\n";
echo "1. Kiểm tra Database Connection...\n";

try {
    $db = new Database();
    $db->connect();
    echo "✓ Database Connection: OK\n\n";
    
    // Kiểm tra bảng tồn tại
    echo "2. Kiểm tra Bảng Database...\n";
    
    $tables = ['users', 'categories', 'transactions', 'budgets'];
    foreach ($tables as $table) {
        $check = $db->getConnection()->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "✓ Bảng '$table': Tồn tại\n";
        } else {
            echo "✗ Bảng '$table': CHƯA TẠO - Cần chạy schema.sql\n";
        }
    }
    
    echo "\n3. Kiểm tra Dữ Liệu Mẫu...\n";
    $users = $db->getConnection()->query("SELECT COUNT(*) as cnt FROM users");
    $user_count = $users ? $users->fetch_assoc()['cnt'] : 0;
    echo "   Users: $user_count bản ghi\n";
    
    $trans = $db->getConnection()->query("SELECT COUNT(*) as cnt FROM transactions");
    $trans_count = $trans ? $trans->fetch_assoc()['cnt'] : 0;
    echo "   Transactions: $trans_count bản ghi\n";
    
    echo "\n✅ Setup Test Hoàn Thành!\n";
    
} catch (Exception $e) {
    echo "❌ LỖI: " . $e->getMessage() . "\n";
}
?>
