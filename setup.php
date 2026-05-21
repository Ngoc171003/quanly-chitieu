<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiêu - Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #667eea;
            margin-bottom: 2rem;
            text-align: center;
        }
        .step {
            margin-bottom: 2rem;
        }
        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 1rem;
            font-weight: bold;
        }
        .step h5 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .step p {
            color: #666;
            margin-left: 56px;
            margin-bottom: 0;
        }
        .code-block {
            background: #f5f5f5;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            margin: 1rem 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
        .alert {
            margin-top: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>
            <i class="fas fa-cog"></i> Chi Tiêu - Hướng Dẫn Cài Đặt
        </h1>

        <?php
        $setup_complete = false;
        $errors = [];
        $success_messages = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $success_messages[] = "✓ Phiên bản PHP: " . PHP_VERSION;
        } else {
            $errors[] = "✗ Phiên bản PHP quá cũ (cần >= 7.0.0)";
        }

        // Check MySQL extension
        if (extension_loaded('mysqli')) {
            $success_messages[] = "✓ MySQLi extension đã cài đặt";
        } else {
            $errors[] = "✗ Cần cài đặt MySQLi extension";
        }

        // Check database connection using config.php settings
        $db_host = DB_HOST;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        $db_name = DB_NAME;

        $mysqli = new mysqli($db_host, $db_user, $db_pass);
        
        if ($mysqli->connect_error) {
            $errors[] = "✗ Lỗi kết nối MySQL: " . $mysqli->connect_error;
        } else {
            $success_messages[] = "✓ MySQL đang chạy";

            // Check if database exists
            $db_check = $mysqli->select_db($db_name);
            if (!$db_check) {
                // Create database
                if ($mysqli->query("CREATE DATABASE $db_name")) {
                    $success_messages[] = "✓ Database đã được tạo";
                    $mysqli->select_db($db_name);
                } else {
                    $errors[] = "✗ Không thể tạo database: " . $mysqli->error;
                }
            } else {
                $success_messages[] = "✓ Database đã tồn tại";
            }

            // Import schema
            if ($mysqli->select_db($db_name)) {
                $schema_file = __DIR__ . '/database/schema.sql';
                if (file_exists($schema_file)) {
                    $sql = file_get_contents($schema_file);
                    
                    // Split SQL statements
                    $statements = array_filter(array_map('trim', preg_split('/;(?=\s|$)/', $sql)));
                    
                    $imported = 0;
                    foreach ($statements as $statement) {
                        if (!empty($statement) && !preg_match('/^--/', $statement)) {
                            if ($mysqli->query($statement)) {
                                $imported++;
                            } else {
                                // Ignore table already exists errors
                                if (strpos($mysqli->error, 'already exists') === false) {
                                    $errors[] = "✗ Lỗi SQL: " . $mysqli->error;
                                }
                            }
                        }
                    }
                    
                    if ($imported > 0) {
                        $success_messages[] = "✓ Database được khởi tạo thành công";
                        $setup_complete = true;
                    }
                } else {
                    $errors[] = "✗ Không tìm thấy file schema.sql";
                }
            }

            $mysqli->close();
        }
        ?>

        <!-- Status Messages -->
        <?php if (!empty($success_messages)): ?>
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle"></i> Kiểm Tra Thành Công:</h5>
            <?php foreach ($success_messages as $msg): ?>
            <p class="mb-1 success"><i class="fas fa-check"></i> <?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle"></i> Lỗi phát hiện:</h5>
            <?php foreach ($errors as $error): ?>
            <p class="mb-1 error"><i class="fas fa-times"></i> <?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Setup Steps -->
        <div class="step">
            <h5><span class="step-number">1</span>Chuẩn Bị XAMPP</h5>
            <p>Đảm bảo Apache và MySQL đang chạy từ XAMPP Control Panel</p>
        </div>

        <div class="step">
            <h5><span class="step-number">2</span>Kiểm Tra Cấu Hình</h5>
            <p>Mở file <code>app/config.php</code> và kiểm tra cấu hình database:</p>
            <div class="code-block">
define('DB_HOST', 'localhost');<br>
define('DB_USER', 'root');<br>
define('DB_PASS', ''); // Nếu có mật khẩu, điền vào đây<br>
define('DB_NAME', 'chi_tieu_ca_nhan');
            </div>
        </div>

        <div class="step">
            <h5><span class="step-number">3</span>Tài Khoản Demo</h5>
            <p>Sau khi cài đặt, sử dụng tài khoản demo:</p>
            <div class="code-block">
Email: nguyenA@gmail.com<br>
Mật khẩu: 123456
            </div>
        </div>

        <div class="step">
            <h5><span class="step-number">4</span>Truy Cập Ứng Dụng</h5>
            <p>Mở trình duyệt và truy cập:</p>
            <div class="code-block">
http://localhost/Myproject/
            </div>
        </div>

        <?php if ($setup_complete): ?>
        <div class="alert alert-success mt-3">
            <i class="fas fa-check-circle"></i> 
            <strong>Tuyệt vời!</strong> Cài đặt hoàn tất. Bạn có thể đăng nhập ngay.
        </div>
        <a href="login.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> Đi đến Đăng Nhập
        </a>
        <?php else: ?>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-circle"></i> 
            <strong>Vui lòng khắc phục các lỗi trên trước khi tiếp tục!</strong>
        </div>
        <button class="btn btn-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Tải Lại
        </button>
        <?php endif; ?>

        <footer style="margin-top: 3rem; padding-top: 1rem; border-top: 1px solid #eee; text-align: center; color: #999; font-size: 0.9rem;">
            <p>Chi Tiêu v1.0 | Hệ thống quản lý chi tiêu cá nhân</p>
            <p style="margin-bottom: 0;">Tạo bởi: Ngô Thị Ngọc | Trường Đại Học Thủy Lợi</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
