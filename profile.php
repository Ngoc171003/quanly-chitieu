<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user info using parameterized query
$user_query = "SELECT id, username, email, full_name, avatar, currency, created_at FROM users WHERE id = ?";
$user_result = $db->execute($user_query, [$user_id]);
$user = $user_result && $user_result->num_rows > 0 ? $user_result->fetch_assoc() : null;

if (!$user) {
    // User not found, logout
    header('Location: ' . BASE_URL . 'logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Yêu cầu không hợp lệ (CSRF Token invalid)!';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            $error = 'Vui lòng điền đầy đủ thông tin!';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ!';
        } else {
            $update = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $update_result = $db->execute($update, [$name, $email, $user_id]);
            if ($update_result !== false) {
                $_SESSION['user_name'] = $name;
                $success = 'Hồ sơ đã được cập nhật!';
                $user['full_name'] = $name;
                $user['email'] = $email;
            } else {
                $error = 'Có lỗi khi cập nhật!';
            }
        }
    } elseif ($action == 'password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Get password from database again for verification
        $pwd_query = "SELECT password FROM users WHERE id = ?";
        $pwd_result = $db->execute($pwd_query, [$user_id]);
        $pwd_row = $pwd_result->fetch_assoc();
        
        if (empty($current_password) || empty($new_password)) {
            $error = 'Vui lòng điền đầy đủ thông tin!';
        } elseif (!password_verify($current_password, $pwd_row['password'])) {
            $error = 'Mật khẩu hiện tại không chính xác!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Mật khẩu xác nhận không khớp!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự!';
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $update = "UPDATE users SET password = ? WHERE id = ?";
            $update_result = $db->execute($update, [$hashed, $user_id]);
            if ($update_result !== false) {
                $success = 'Mật khẩu đã được cập nhật!';
            } else {
                $error = 'Có lỗi khi cập nhật!';
            }
        }
    } elseif ($action == 'avatar') {
        if (isset($_FILES['avatar_file'])) {
            $upload = handleAvatarUpload($_FILES['avatar_file'], $user_id);
            if ($upload['success']) {
                $old_avatar = $user['avatar'];
                if ($old_avatar) {
                    deleteAvatar($old_avatar);
                }
                $update = "UPDATE users SET avatar = ? WHERE id = ?";
                $db->execute($update, [$upload['path'], $user_id]);
                $_SESSION['user_avatar'] = $upload['path'];
                $user['avatar'] = $upload['path'];
                $success = 'Ảnh đại diện đã được cập nhật!';
            } else {
                $error = $upload['error'];
            }
        }
    } elseif ($action == 'remove_avatar') {
        if ($user['avatar']) {
            deleteAvatar($user['avatar']);
            $update = "UPDATE users SET avatar = NULL WHERE id = ?";
            $db->execute($update, [$user_id]);
            unset($_SESSION['user_avatar']);
            $user['avatar'] = null;
            $success = 'Đã xóa ảnh đại diện!';
        }
    } elseif ($action == 'currency') {
        $currency = $_POST['currency'] ?? 'VND';
        if (Currency::setUserCurrency($user_id, $db, $currency)) {
            $user['currency'] = $currency;
            $success = 'Tiền tệ hiển thị đã được cập nhật!';
        } else {
            $error = 'Tiền tệ không hợp lệ!';
        }
    }
}
}

$page_title = 'Hồ Sơ - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-circle"></i> Hồ Sơ Cá Nhân</h1>
    </div>
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

<div class="row">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?php echo BASE_URL . e($user['avatar']); ?>" alt="Avatar" class="rounded-circle mb-3 object-fit-cover shadow-sm" style="width: 120px; height: 120px; border: 3px solid white;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center shadow-sm" 
                             style="width: 120px; height: 120px; font-size: 3rem; border: 3px solid white;">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5><?php echo e($user['full_name']); ?></h5>
                <p class="text-muted small mb-3"><?php echo e($user['email']); ?></p>
                
                <hr>
                
                <form method="POST" enctype="multipart/form-data" class="text-start">
                    <input type="hidden" name="action" value="avatar">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Đổi ảnh đại diện</label>
                        <input type="file" name="avatar_file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <small class="text-muted" style="font-size: 0.7rem;">Tối đa 2MB (JPG, PNG, GIF)</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">
                        <i class="fas fa-upload"></i> Tải ảnh lên
                    </button>
                </form>
                
                <?php if (!empty($user['avatar'])): ?>
                <form method="POST" class="text-start" onsubmit="return confirm('Bạn có chắc muốn xóa ảnh đại diện?');">
                    <input type="hidden" name="action" value="remove_avatar">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-trash"></i> Xóa ảnh
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <!-- Profile Tab -->
        <ul class="nav nav-tabs mb-3" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                    <i class="fas fa-info-circle"></i> Thông Tin
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                    <i class="fas fa-lock"></i> Đổi Mật Khẩu
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                    <i class="fas fa-cog"></i> Cài Đặt
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Info Tab -->
            <div class="tab-pane fade show active" id="info" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="profile">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <div class="mb-3">
                                <label class="form-label">Họ và Tên <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($user['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?php echo e($user['email']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Password Tab -->
            <div class="tab-pane fade" id="password" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="password">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <div class="mb-3">
                                <label class="form-label">Mật Khẩu Hiện Tại <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mật Khẩu Mới <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Xác Nhận Mật Khẩu <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Đổi Mật Khẩu
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="currency">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
                            <h6 class="mb-3"><i class="fas fa-coins text-warning"></i> Đơn Vị Tiền Tệ</h6>
                            <p class="text-muted small mb-3">Tất cả giao dịch sẽ được quy đổi và hiển thị theo đơn vị tiền tệ bạn chọn. Tỷ giá được lấy theo thời gian thực.</p>
                            
                            <div class="mb-4">
                                <label class="form-label">Tiền tệ hiển thị</label>
                                <select name="currency" class="form-select" required>
                                    <?php 
                                    $currencies = Currency::getSupportedCurrencies();
                                    $userCurrency = $user['currency'] ?? 'VND';
                                    foreach ($currencies as $code => $info): 
                                    ?>
                                    <option value="<?php echo $code; ?>" <?php echo $code === $userCurrency ? 'selected' : ''; ?>>
                                        <?php echo $code; ?> - <?php echo $info['name']; ?> (<?php echo $info['symbol']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu Cài Đặt
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
