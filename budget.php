<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Yêu cầu không hợp lệ (CSRF Token invalid)!';
    } else {
        $month = intval($_POST['month'] ?? $month);
        $year = intval($_POST['year'] ?? $year);
    $amount = floatval($_POST['amount'] ?? 0);
    
    if ($amount <= 0) {
        $error = 'Số tiền phải lớn hơn 0!';
    } else {
        // Check if budget exists using parameterized query
        $check = "SELECT id FROM budgets WHERE user_id = ? AND month = ? AND year = ?";
        $result = $db->execute($check, [$user_id, $month, $year]);
        
        if ($result && $result->num_rows > 0) {
            $update = "UPDATE budgets SET limit_amount = ? WHERE user_id = ? AND month = ? AND year = ?";
            $update_result = $db->execute($update, [$amount, $user_id, $month, $year]);
            if ($update_result !== false) {
                $success = 'Ngân sách đã được cập nhật!';
            } else {
                $error = 'Có lỗi khi cập nhật!';
            }
        } else {
            $insert = "INSERT INTO budgets (user_id, limit_amount, month, year) VALUES (?, ?, ?, ?)";
            $insert_result = $db->execute($insert, [$user_id, $amount, $month, $year]);
            if ($insert_result !== false) {
                $success = 'Ngân sách đã được thiết lập!';
            } else {
                $error = 'Có lỗi!';
            }
        }
    }
}
}

// Get current budget using parameterized query
$budget_query = "SELECT id, limit_amount FROM budgets WHERE user_id = ? AND month = ? AND year = ?";
$budget_result = $db->execute($budget_query, [$user_id, $month, $year]);
$current_budget = ($budget_result && $budget_result->num_rows > 0) ? $budget_result->fetch_assoc()['limit_amount'] : 0;

// Get expenses by category for pie chart using parameterized query
$expense_query = "SELECT c.id, c.name, SUM(t.amount) as total, COUNT(t.id) as count
                  FROM transactions t
                  JOIN categories c ON t.category_id = c.id
                  WHERE t.user_id = ? AND LOWER(c.type) = 'chi'
                  AND MONTH(t.transaction_date) = ?
                  AND YEAR(t.transaction_date) = ?
                  GROUP BY c.id, c.name
                  ORDER BY total DESC";

$expenses_result = $db->execute($expense_query, [$user_id, $month, $year]);
$expenses_data = [];
if ($expenses_result) {
    while ($row = $expenses_result->fetch_assoc()) {
        $expenses_data[] = $row;
    }
}

$page_title = 'Ngân Sách - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="mb-4">
    <h1><i class="fas fa-chart-pie"></i> Ngân Sách</h1>
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
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-cog"></i> Thiết Lập Ngân Sách</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Tháng <span class="text-danger">*</span></label>
                        <select name="month" id="budgetMonth" class="form-select" onchange="changeBudgetPeriod()" required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                Tháng <?php echo $m; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Năm <span class="text-danger">*</span></label>
                        <select name="year" id="budgetYear" class="form-select" onchange="changeBudgetPeriod()" required>
                            <?php for ($y = 2024; $y <= 2027; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                Năm <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giới Hạn Chi Tiêu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="amount" class="form-control amount-input" placeholder="0" 
                                   value="<?php echo (int)$current_budget; ?>" required>
                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Lưu
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Phân Tích Ngân Sách Tháng <?php echo $month; ?>/<?php echo $year; ?></h6>
            </div>
            <div class="card-body">
                <?php
                $dashboard = getDashboardData($user_id, $db, $month, $year);
                if ($current_budget > 0):
                    $percentage = min(100, ($dashboard['expenses'] / $current_budget) * 100);
                    $progress_class = $percentage > 100 ? 'bg-danger' : ($percentage > 80 ? 'bg-warning' : 'bg-success');
                ?>
                <h5>Ngân Sách: <?php echo formatCurrency($current_budget); ?></h5>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%">
                        <?php echo round($percentage, 1); ?>%
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-md-4">
                        <p class="text-muted mb-1">Đã Chi</p>
                        <h5 class="text-danger"><?php echo formatCurrency($dashboard['expenses']); ?></h5>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted mb-1">Còn Lại</p>
                        <h5 class="<?php echo $dashboard['budget_remaining'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($dashboard['budget_remaining']); ?>
                        </h5>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted mb-1">Tổng Thu</p>
                        <h5 class="text-info"><?php echo formatCurrency($dashboard['income']); ?></h5>
                    </div>
                </div>

                <?php if ($dashboard['expenses'] > $current_budget): ?>
                <div class="alert alert-danger mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Cảnh báo!</strong> Bạn đã vượt quá ngân sách <?php echo formatCurrency($dashboard['expenses'] - $current_budget); ?>!
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> Vui lòng thiết lập ngân sách cho tháng này!
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($expenses_data)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-list"></i> Chi Tiêu Theo Danh Mục</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Danh Mục</th>
                                <th class="text-end">Số Tiền</th>
                                <th class="text-end">% Ngân Sách</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses_data as $exp): ?>
                            <tr>
                                <td><?php echo e($exp['name']); ?></td>
                                <td class="text-end"><strong><?php echo formatCurrency($exp['total']); ?></strong></td>
                                <td class="text-end">
                                    <?php echo $current_budget > 0 ? round(($exp['total'] / $current_budget) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function changeBudgetPeriod() {
    const month = document.getElementById('budgetMonth').value;
    const year = document.getElementById('budgetYear').value;
    window.location.href = '?month=' + month + '&year=' + year;
}
</script>

<?php include 'views/footer.php'; ?>
