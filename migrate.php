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
    echo "<h3>✅ Không có migration wallet cần thực hiện.</h3>";
    echo "<p>Hệ thống hiện tại đã loại bỏ tính năng ví; nếu cần cập nhật schema khác, hãy chạy migration tương ứng.</p>";
    echo "<h2 style='color: green;'>✅ Migration Hoàn Tất!</h2>";
    echo "<p><a href='dashboard.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>← Quay lại Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Lỗi: " . $e->getMessage() . "</h2>";
}

echo "</body></html>";
?>
