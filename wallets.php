<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        $error = 'Yêu cầu không hợp lệ (CSRF Token invalid)!';
    } else {
        $wallet_id = intval($_GET['id'] ?? 0);
        
        // Check if there is more than 1 wallet
        $count_query = "SELECT COUNT(*) as count FROM wallets WHERE user_id = ?";
        $count_res = $db->execute($count_query, [$user_id]);
        $wallet_count = intval($count_res->fetch_assoc()['count'] ?? 0);
        
        if ($wallet_count <= 1) {
            $error = 'Bạn phải giữ lại ít nhất một ví!';
        } else {
            // Check if wallet has transactions
            $check_trans = "SELECT id FROM transactions WHERE wallet_id = ? LIMIT 1";
            $check_trans_res = $db->execute($check_trans, [$wallet_id]);
            if ($check_trans_res && $check_trans_res->num_rows > 0) {
                $error = 'Không thể xóa ví đang có giao dịch! Hãy xóa hoặc chuyển giao dịch sang ví khác trước.';
            } else {
                // Get wallet details to check default
                $wallet_check = "SELECT is_default FROM wallets WHERE id = ? AND user_id = ?";
                $wallet_check_res = $db->execute($wallet_check, [$wallet_id, $user_id]);
                if ($wallet_check_res && $wallet_check_res->num_rows > 0) {
                    $wallet_data = $wallet_check_res->fetch_assoc();
                    
                    // Delete wallet
                    $delete_query = "DELETE FROM wallets WHERE id = ? AND user_id = ?";
                    $db->execute($delete_query, [$wallet_id, $user_id]);
                    
                    // If deleted wallet was default, set another one as default
                    if ($wallet_data['is_default']) {
                        $set_default = "UPDATE wallets SET is_default = 1 WHERE user_id = ? LIMIT 1";
                        $db->execute($set_default, [$user_id]);
                    }
                    
                    $success = 'Ví đã được xóa thành công!';
                }
            }
        }
    }
}

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Yêu cầu không hợp lệ (CSRF Token invalid)!';
    } else {
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'cash');
        $initial_balance = floatval($_POST['initial_balance'] ?? 0);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $wallet_id = intval($_POST['wallet_id'] ?? 0);
        
        if (empty($name)) {
            $error = 'Tên ví không được trống!';
        } elseif (!in_array($type, ['cash', 'bank', 'ewallet'])) {
            $error = 'Loại ví không hợp lệ!';
        } else {
            // Assign icon based on type
            $icon = 'fas fa-wallet';
            if ($type == 'bank') {
                $icon = 'fas fa-university';
            } elseif ($type == 'ewallet') {
                $icon = 'fas fa-mobile-alt';
            }
            
            if ($wallet_id > 0) {
                // Update
                $update = "UPDATE wallets SET name = ?, type = ?, initial_balance = ?, icon = ? WHERE id = ? AND user_id = ?";
                $result = $db->execute($update, [$name, $type, $initial_balance, $icon, $wallet_id, $user_id]);
                
                if ($result !== false) {
                    // Update default status if requested
                    if ($is_default) {
                        // Remove default from all other wallets
                        $db->execute("UPDATE wallets SET is_default = 0 WHERE user_id = ?", [$user_id]);
                        $db->execute("UPDATE wallets SET is_default = 1 WHERE id = ? AND user_id = ?", [$wallet_id, $user_id]);
                    } else {
                        // Check if we are trying to unset default from the only default wallet
                        $default_check = $db->execute("SELECT id FROM wallets WHERE user_id = ? AND is_default = 1 AND id != ?", [$user_id, $wallet_id]);
                        if ($default_check->num_rows == 0) {
                            // Keep it as default since there are no other default wallets
                            $db->execute("UPDATE wallets SET is_default = 1 WHERE id = ? AND user_id = ?", [$wallet_id, $user_id]);
                        }
                    }
                    $success = 'Ví đã được cập nhật thành công!';
                } else {
                    $error = 'Lỗi khi cập nhật ví!';
                }
            } else {
                // Add new
                if ($is_default) {
                    // Remove default from all other wallets
                    $db->execute("UPDATE wallets SET is_default = 0 WHERE user_id = ?", [$user_id]);
                }
                
                // If it's the first wallet, it must be default
                $wallet_count_res = $db->execute("SELECT COUNT(*) as count FROM wallets WHERE user_id = ?", [$user_id]);
                $wallet_count = intval($wallet_count_res->fetch_assoc()['count'] ?? 0);
                if ($wallet_count == 0) {
                    $is_default = 1;
                }
                
                $insert = "INSERT INTO wallets (user_id, name, type, initial_balance, icon, is_default) VALUES (?, ?, ?, ?, ?, ?)";
                $result = $db->execute($insert, [$user_id, $name, $type, $initial_balance, $icon, $is_default]);
                
                if ($result !== false) {
                    $success = 'Ví mới đã được tạo!';
                } else {
                    $error = 'Lỗi khi tạo ví!';
                }
            }
        }
    }
}

// Fetch all wallets for view
$wallets = getAllWalletsWithBalance($user_id, $db);
$page_title = 'Quản Lý Ví - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h1><i class="fas fa-wallet text-primary"></i> Quản Lý Ví</h1>
        <p class="text-muted mb-0">Tạo nhiều ví khác nhau để quản lý các nguồn tiền của bạn (tiền mặt, thẻ ngân hàng, ví điện tử).</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" id="addWalletBtn" data-bs-toggle="modal" data-bs-target="#walletModal">
            <i class="fas fa-plus"></i> Thêm Ví Mới
        </button>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <?php foreach ($wallets as $w): ?>
    <?php 
    $type_label = 'Tiền mặt';
    $type_badge_color = 'bg-success';
    if ($w['type'] == 'bank') {
        $type_label = 'Ngân hàng';
        $type_badge_color = 'bg-primary';
    } elseif ($w['type'] == 'ewallet') {
        $type_label = 'Ví điện tử';
        $type_badge_color = 'bg-info';
    }
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center text-primary" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="<?php echo e($w['icon'] ?? 'fas fa-wallet'); ?>"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-truncate" style="max-width: 150px;"><?php echo e($w['name']); ?></h5>
                                <span class="badge <?php echo $type_badge_color; ?>"><?php echo $type_label; ?></span>
                            </div>
                        </div>
                        <?php if ($w['is_default']): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Mặc định</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <small class="text-muted d-block">Số dư ban đầu:</small>
                        <span class="text-muted fw-bold"><?php echo formatCurrency($w['initial_balance']); ?></span>
                        
                        <small class="text-muted d-block mt-2">Số dư hiện tại:</small>
                        <h3 class="fw-extrabold mb-0 <?php echo $w['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($w['balance']); ?>
                        </h3>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <button class="btn btn-sm btn-outline-primary edit-wallet-btn" 
                            data-id="<?php echo $w['id']; ?>"
                            data-name="<?php echo e($w['name']); ?>"
                            data-type="<?php echo $w['type']; ?>"
                            data-balance="<?php echo (float)$w['initial_balance']; ?>"
                            data-default="<?php echo $w['is_default']; ?>"
                            data-bs-toggle="modal" 
                            data-bs-target="#walletModal"
                            title="Sửa ví">
                        <i class="fas fa-edit"></i> Sửa
                    </button>
                    
                    <?php if (!$w['is_default']): ?>
                    <a href="<?php echo BASE_URL; ?>wallets.php?action=delete&id=<?php echo $w['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                       class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Bạn có chắc muốn xóa ví này? Tất cả các dữ liệu sẽ không bị ảnh hưởng nếu không liên kết, nhưng ví không thể xóa nếu có giao dịch.');"
                       title="Xóa ví">
                        <i class="fas fa-trash"></i> Xóa
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled title="Không thể xóa ví mặc định">
                        <i class="fas fa-trash"></i> Xóa
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add/Edit Wallet Modal -->
<div class="modal fade" id="walletModal" tabindex="-1" aria-labelledby="walletModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="walletModalLabel">Thêm Ví Mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="walletForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="wallet_id" id="wallet_id" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="wallet_name" class="form-label fw-semibold">Tên Ví <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wallet_name" name="name" required placeholder="Ví dụ: Tiền mặt, MB Bank, Momo...">
                    </div>
                    
                    <div class="mb-3">
                        <label for="wallet_type" class="form-label fw-semibold">Loại Ví <span class="text-danger">*</span></label>
                        <select class="form-select" id="wallet_type" name="type" required>
                            <option value="cash">Tiền mặt</option>
                            <option value="bank">Ngân hàng</option>
                            <option value="ewallet">Ví điện tử</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wallet_balance" class="form-label fw-semibold">Số Dư Ban Đầu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="any" class="form-control" id="wallet_balance" name="initial_balance" required placeholder="0" value="0">
                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                        </div>
                        <small class="text-muted">Nhập số tiền hiện có trong ví tại thời điểm tạo ví.</small>
                    </div>
                    
                    <div class="mb-3 form-check form-switch pt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="wallet_default" name="is_default">
                        <label class="form-check-label fw-semibold" for="wallet_default">Đặt làm ví mặc định</label>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary flex-grow-1">Lưu Lại</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Wallet reset
    const addWalletBtn = document.getElementById('addWalletBtn');
    if (addWalletBtn) {
        addWalletBtn.addEventListener('click', function() {
            document.getElementById('walletModalLabel').innerText = 'Thêm Ví Mới';
            document.getElementById('wallet_id').value = '0';
            document.getElementById('wallet_name').value = '';
            document.getElementById('wallet_type').value = 'cash';
            document.getElementById('wallet_balance').value = '0';
            document.getElementById('wallet_balance').removeAttribute('readonly');
            document.getElementById('wallet_default').checked = false;
        });
    }

    // Edit Wallet data population
    const editBtns = document.querySelectorAll('.edit-wallet-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const type = this.getAttribute('data-type');
            const balance = this.getAttribute('data-balance');
            const isDefault = this.getAttribute('data-default') == '1';

            document.getElementById('walletModalLabel').innerText = 'Chỉnh Sửa Ví';
            document.getElementById('wallet_id').value = id;
            document.getElementById('wallet_name').value = name;
            document.getElementById('wallet_type').value = type;
            document.getElementById('wallet_balance').value = balance;
            document.getElementById('wallet_default').checked = isDefault;
        });
    });
});
</script>

<?php include 'views/footer.php'; ?>
