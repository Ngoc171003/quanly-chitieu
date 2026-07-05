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
        $goal_id = intval($_GET['id'] ?? 0);
        $result = deleteSavingsGoal($goal_id, $user_id, $db);
        if ($result !== false) {
            $success = 'Mục tiêu đã được xóa thành công!';
        } else {
            $error = 'Lỗi khi xóa mục tiêu! Mục tiêu đã hoàn thành không thể xóa.';
        }
    }
}

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Yêu cầu không hợp lệ (CSRF Token invalid)!';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'create' || $action == 'edit') {
            $name = trim($_POST['name'] ?? '');
            $target_amount = floatval($_POST['target_amount'] ?? 0);
            $target_date = trim($_POST['target_date'] ?? '');
            $goal_id = intval($_POST['goal_id'] ?? 0);
            
            if (empty($name)) {
                $error = 'Tên mục tiêu không được trống!';
            } elseif ($target_amount <= 0) {
                $error = 'Số tiền mục tiêu phải lớn hơn 0!';
            } elseif (empty($target_date)) {
                $error = 'Vui lòng chọn ngày dự kiến hoàn thành!';
            } else {
                if ($action == 'edit' && $goal_id > 0) {
                    $result = updateSavingsGoal($goal_id, $user_id, $name, $target_amount, $target_date, $db);
                    if ($result !== false) {
                        $success = 'Mục tiêu đã được cập nhật!';
                    } else {
                        $error = 'Lỗi khi cập nhật mục tiêu!';
                    }
                } else {
                    $result = createSavingsGoal($user_id, $name, $target_amount, $target_date, $db);
                    if ($result !== false) {
                        $success = 'Mục tiêu mới đã được tạo!';
                    } else {
                        $error = 'Lỗi khi tạo mục tiêu!';
                    }
                }
            }
        } elseif ($action == 'contribute') {
            $goal_id = intval($_POST['goal_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $wallet_id = isset($_POST['wallet_id']) && $_POST['wallet_id'] !== '' ? intval($_POST['wallet_id']) : null;
            
            if ($goal_id > 0 && $amount > 0) {
                $res = contributeToSavingsGoal($goal_id, $user_id, $amount, $wallet_id, $db);
                if ($res) {
                    $success = "Đã đóng góp thành công " . formatCurrency($amount) . " vào mục tiêu!";
                    // Check if goal is now completed
                    $goal = getSavingsGoalById($goal_id, $user_id, $db);
                    if ($goal && $goal['status'] == 'completed') {
                        $success .= " 🎉 Chúc mừng! Mục tiêu \"" . $goal['name'] . "\" đã hoàn thành!";
                    }
                } else {
                    $error = "Có lỗi xảy ra khi đóng góp!";
                }
            } else {
                $error = "Số tiền đóng góp hoặc mục tiêu không hợp lệ!";
            }
        }
    }
}

// Fetch all goals
$all_goals = getSavingsGoals($user_id, $db);
$wallets = getAllWalletsWithBalance($user_id, $db);

$page_title = 'Mục Tiêu Tiết Kiệm - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h1><i class="fas fa-bullseye text-primary"></i> Mục Tiêu Tiết Kiệm</h1>
        <p class="text-muted mb-0">Tạo và quản lý các mục tiêu tiết kiệm của bạn. Theo dõi tiến độ và đóng góp mỗi ngày.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button class="btn btn-primary" id="addGoalBtn" data-bs-toggle="modal" data-bs-target="#goalModal">
            <i class="fas fa-plus"></i> Tạo Mục Tiêu Mới
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

<?php
$active_goals = [];
$completed_goals = [];
if ($all_goals && $all_goals->num_rows > 0) {
    while ($g = $all_goals->fetch_assoc()) {
        if ($g['status'] === 'completed') {
            $completed_goals[] = $g;
        } else {
            $active_goals[] = $g;
        }
    }
}
?>

<!-- Active Goals -->
<h5 class="mb-3 fw-bold"><i class="fas fa-spinner text-primary me-2"></i>Mục tiêu đang thực hiện (<?php echo count($active_goals); ?>)</h5>

<?php if (!empty($active_goals)): ?>
<div class="row">
    <?php foreach ($active_goals as $goal): ?>
    <?php
        $current = floatval($goal['current_amount_live'] ?? $goal['current_amount']);
        $percent = $goal['target_amount'] > 0 ? min(100, ($current / $goal['target_amount']) * 100) : 0;
        $remaining = max(0, floatval($goal['target_amount']) - $current);
        $target_dt = new DateTime($goal['target_date']);
        $now = new DateTime();
        $days_left = max(0, (int)$now->diff($target_dt)->format('%r%a'));
        $is_overdue = $target_dt < $now;
    ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-between p-4">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-light p-3 d-flex align-items-center justify-content-center text-primary" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-truncate" style="max-width: 180px;"><?php echo e($goal['name']); ?></h5>
                                <span class="badge <?php echo $is_overdue ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?php echo $is_overdue ? 'Quá hạn' : $days_left . ' ngày còn lại'; ?>
                                </span>
                            </div>
                        </div>
                        <span class="badge <?php echo $percent >= 70 ? 'bg-warning text-dark' : 'bg-primary'; ?> fs-6"><?php echo round($percent); ?>%</span>
                    </div>

                    <!-- Progress bar -->
                    <div class="mb-3">
                        <div class="progress" style="height: 10px; border-radius: 8px;">
                            <div class="progress-bar <?php echo $percent >= 100 ? 'bg-success' : ($percent >= 70 ? 'bg-warning' : 'bg-primary'); ?>" 
                                 role="progressbar" style="width: <?php echo $percent; ?>%; border-radius: 8px; transition: width .6s ease;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Đã tiết kiệm:</small>
                            <span class="fw-bold text-success"><?php echo formatCurrency($current); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Mục tiêu:</small>
                            <span class="fw-bold"><?php echo formatCurrency($goal['target_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Còn thiếu:</small>
                            <span class="fw-bold text-danger"><?php echo formatCurrency($remaining); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Hạn hoàn thành:</small>
                            <small class="fw-semibold <?php echo $is_overdue ? 'text-danger' : ''; ?>"><?php echo date('d/m/Y', strtotime($goal['target_date'])); ?></small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between gap-2 border-top pt-3">
                    <button class="btn btn-sm btn-primary flex-grow-1 contribute-btn"
                        data-goal-id="<?php echo $goal['id']; ?>"
                        data-goal-name="<?php echo e($goal['name']); ?>"
                        data-goal-current="<?php echo $current; ?>"
                        data-goal-target="<?php echo $goal['target_amount']; ?>"
                        data-bs-toggle="modal" data-bs-target="#contributeModal">
                        <i class="fas fa-hand-holding-usd me-1"></i> Đóng góp
                    </button>
                    <button class="btn btn-sm btn-outline-primary edit-goal-btn"
                        data-id="<?php echo $goal['id']; ?>"
                        data-name="<?php echo e($goal['name']); ?>"
                        data-target="<?php echo $goal['target_amount']; ?>"
                        data-date="<?php echo $goal['target_date']; ?>"
                        data-bs-toggle="modal" data-bs-target="#goalModal"
                        title="Sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="<?php echo BASE_URL; ?>savings.php?action=delete&id=<?php echo $goal['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Bạn có chắc muốn xóa mục tiêu này?');"
                       title="Xóa">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body text-center py-5">
        <i class="fas fa-bullseye text-muted mb-3" style="font-size: 3rem;"></i>
        <p class="text-muted mb-3">Bạn chưa có mục tiêu tiết kiệm nào đang thực hiện.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#goalModal">
            <i class="fas fa-plus me-1"></i> Tạo mục tiêu đầu tiên
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Completed Goals -->
<?php if (!empty($completed_goals)): ?>
<h5 class="mb-3 mt-4 fw-bold"><i class="fas fa-check-circle text-success me-2"></i>Mục tiêu đã hoàn thành (<?php echo count($completed_goals); ?>)</h5>
<div class="row">
    <?php foreach ($completed_goals as $goal): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-0 shadow-sm" style="opacity: 0.85;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 d-flex align-items-center justify-content-center text-success" style="width: 50px; height: 50px; font-size: 1.5rem;">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0"><?php echo e($goal['name']); ?></h5>
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Hoàn thành</span>
                        </div>
                    </div>
                </div>
                <div class="progress mb-2" style="height: 8px; border-radius: 8px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%; border-radius: 8px;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Đã tiết kiệm:</small>
                    <span class="fw-bold text-success"><?php echo formatCurrency($goal['current_amount']); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Mục tiêu:</small>
                    <span class="fw-bold"><?php echo formatCurrency($goal['target_amount']); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add/Edit Goal Modal -->
<div class="modal fade" id="goalModal" tabindex="-1" aria-labelledby="goalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="goalModalLabel">Tạo Mục Tiêu Mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="goalForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" id="goal_action" value="create">
                <input type="hidden" name="goal_id" id="goal_id" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="goal_name" class="form-label fw-semibold">Tên mục tiêu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="goal_name" name="name" required placeholder="VD: Mua laptop, Du lịch Đà Nẵng...">
                    </div>
                    <div class="mb-3">
                        <label for="goal_target_amount" class="form-label fw-semibold">Số tiền mục tiêu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="any" min="1" class="form-control" id="goal_target_amount" name="target_amount" required placeholder="0">
                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="goal_target_date" class="form-label fw-semibold">Ngày dự kiến hoàn thành <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="goal_target_date" name="target_date" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary flex-grow-1">Lưu Mục Tiêu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contribute Modal -->
<div class="modal fade" id="contributeModal" tabindex="-1" aria-labelledby="contributeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="contributeModalLabel">Đóng góp vào mục tiêu</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="contributeForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="contribute">
                <input type="hidden" name="goal_id" id="contribute_goal_id" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mục tiêu</label>
                        <input type="text" class="form-control" id="contribute_goal_name" readonly>
                    </div>
                    <div class="mb-2 p-2 rounded-3 bg-light">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Đã tiết kiệm:</small>
                            <small class="fw-bold text-success" id="contribute_current">0</small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Mục tiêu:</small>
                            <small class="fw-bold" id="contribute_target">0</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="contribute_amount" class="form-label fw-semibold">Số tiền đóng góp <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="any" min="1" class="form-control" name="amount" id="contribute_amount" required placeholder="0">
                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="contribute_wallet" class="form-label fw-semibold">Khấu trừ từ ví <small class="text-muted">(tùy chọn)</small></label>
                        <select class="form-select" name="wallet_id" id="contribute_wallet">
                            <option value="">Không khấu trừ</option>
                            <?php foreach ($wallets as $w): ?>
                                <option value="<?php echo $w['id']; ?>"><?php echo e($w['name']); ?> (<?php echo formatCurrency($w['balance']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-secondary flex-grow-1" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary flex-grow-1">Gửi đóng góp</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Goal - Reset form
    const addGoalBtn = document.getElementById('addGoalBtn');
    if (addGoalBtn) {
        addGoalBtn.addEventListener('click', function() {
            document.getElementById('goalModalLabel').innerText = 'Tạo Mục Tiêu Mới';
            document.getElementById('goal_action').value = 'create';
            document.getElementById('goal_id').value = '0';
            document.getElementById('goal_name').value = '';
            document.getElementById('goal_target_amount').value = '';
            document.getElementById('goal_target_date').value = '';
        });
    }

    // Edit Goal - Populate form
    const editBtns = document.querySelectorAll('.edit-goal-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('goalModalLabel').innerText = 'Chỉnh Sửa Mục Tiêu';
            document.getElementById('goal_action').value = 'edit';
            document.getElementById('goal_id').value = this.getAttribute('data-id');
            document.getElementById('goal_name').value = this.getAttribute('data-name');
            document.getElementById('goal_target_amount').value = this.getAttribute('data-target');
            document.getElementById('goal_target_date').value = this.getAttribute('data-date');
        });
    });

    // Contribute Modal - Populate
    const contributeModal = document.getElementById('contributeModal');
    if (contributeModal) {
        contributeModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button) {
                document.getElementById('contribute_goal_id').value = button.getAttribute('data-goal-id');
                document.getElementById('contribute_goal_name').value = button.getAttribute('data-goal-name');
                document.getElementById('contribute_amount').value = '';
                
                const current = parseFloat(button.getAttribute('data-goal-current') || 0);
                const target = parseFloat(button.getAttribute('data-goal-target') || 0);
                document.getElementById('contribute_current').textContent = new Intl.NumberFormat('vi-VN').format(current) + ' VNĐ';
                document.getElementById('contribute_target').textContent = new Intl.NumberFormat('vi-VN').format(target) + ' VNĐ';
            }
        });
    }
});
</script>

<?php include 'views/footer.php'; ?>
