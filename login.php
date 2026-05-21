<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

if (isAuthenticated()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    if ($action == 'login') {
        $login_key = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($login_key) || empty($password)) {
            $error = 'Vui lòng nhập email/tên đăng nhập và mật khẩu!';
        } else {
            // Use parameterized query to prevent SQL injection
            $query = "SELECT id, full_name, email, username, password FROM users WHERE email = ? OR username = ?";
            $result = $db->execute($query, [$login_key, $login_key]);
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'] ?? $user['username'];
                    $_SESSION['user'] = ['id' => $user['id'], 'name' => $_SESSION['user_name'], 'email' => $user['email']];
                    header('Location: ' . BASE_URL . 'dashboard.php');
                    exit;
                } else {
                    $error = 'Email hoặc mật khẩu không đúng!';
                }
            } else {
                $error = 'Email hoặc tên đăng nhập không tồn tại!';
            }
        }
    } elseif ($action == 'register') {
        $full_name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            $error = 'Vui lòng điền đầy đủ thông tin!';
        } elseif (strlen($username) < 3) {
            $error = 'Tên đăng nhập phải có ít nhất 3 ký tự!';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ!';
        } elseif ($password !== $confirm_password) {
            $error = 'Mật khẩu xác nhận không khớp!';
        } else {
            // Check if email or username exists using parameterized query
            $check_query = "SELECT id FROM users WHERE email = ? OR username = ?";
            $check_result = $db->execute($check_query, [$email, $username]);
            
            if ($check_result && $check_result->num_rows > 0) {
                $error = 'Email hoặc tên đăng nhập đã được đăng ký!';
            } else {
                // Hash password using BCRYPT
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                // Insert new user using parameterized query
                $insert_query = "INSERT INTO users (username, full_name, email, password) VALUES (?, ?, ?, ?)";
                $insert_result = $db->execute($insert_query, [$username, $full_name, $email, $hashed_password]);
                
                if ($insert_result !== false) {
                    $user_id = $db->lastInsertId();
                    
                    // Create default categories for new user
                    $categories = [
                        ['Lương', 'Thu'],
                        ['Thưởng', 'Thu'],
                        ['Ăn uống', 'Chi'],
                        ['Di chuyển', 'Chi'],
                        ['Nhà ở', 'Chi'],
                        ['Mua sắm', 'Chi'],
                        ['Y tế', 'Chi'],
                        ['Giáo dục', 'Chi'],
                        ['Giải trí', 'Chi']
                    ];
                    
                    $cat_query = "INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)";
                    foreach ($categories as $cat) {
                        $db->execute($cat_query, [$user_id, $cat[0], $cat[1]]);
                    }
                    
                    // Create default wallet for new user
                    $wallet_query = "INSERT INTO wallets (user_id, name, balance) VALUES (?, 'Ví Tiền Mặt', 0)";
                    $db->execute($wallet_query, [$user_id]);
                    
                    $success = 'Đăng ký thành công! Vui lòng đăng nhập.';
                    // Clear form
                    $_POST = [];
                } else {
                    $error = 'Có lỗi khi đăng ký. Vui lòng thử lại!';
                }
            }
        }
    }
}

$page_title = 'Login - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2><i class="fas fa-wallet text-primary"></i> <?php echo APP_NAME; ?></h2>
                        <p class="text-muted">Quản lý chi tiêu cá nhân đa nền tảng</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo e($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs mb-4" id="authTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">
                                Đăng Nhập
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">
                                Đăng Ký
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Login Form -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label class="form-label">Email hoặc Tên đăng nhập</label>
                                    <input type="text" name="email" class="form-control" placeholder="Email hoặc tên đăng nhập" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu</label>
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt"></i> Đăng Nhập
                                </button>
                            </form>
                            <p class="text-center text-muted mt-3 small">
                                Demo: ngongoc@gmail.com / 123456
                            </p>
                        </div>

                        <!-- Register Form -->
                        <div class="tab-pane fade" id="register" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="register">
                                <div class="mb-3">
                                    <label class="form-label">Họ và tên</label>
                                    <input type="text" name="name" class="form-control" placeholder="Nguyen Van A" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tên đăng nhập</label>
                                    <input type="text" name="username" class="form-control" placeholder="vd: NguyenA" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu</label>
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Xác nhận mật khẩu</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus"></i> Đăng Ký
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
