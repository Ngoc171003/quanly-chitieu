<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$error = '';
$success = $_SESSION['savings_success_msg'] ?? '';
unset($_SESSION['savings_success_msg']);
$completed_goal = $_SESSION['savings_completed_goal'] ?? '';
unset($_SESSION['savings_completed_goal']);

// Handle Savings Goal Contribution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'contribute') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Yêu cầu không hợp lệ (CSRF Token invalid)!';
    } else {
        $goal_id = intval($_POST['goal_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $wallet_id = isset($_POST['wallet_id']) && $_POST['wallet_id'] !== '' ? intval($_POST['wallet_id']) : null;
        
        if ($goal_id > 0 && $amount > 0) {
            $res = contributeToSavingsGoal($goal_id, $user_id, $amount, $wallet_id, $db);
            if ($res) {
                $_SESSION['savings_success_msg'] = "Đã đóng góp thành công " . formatCurrency($amount) . " vào mục tiêu.";
                header("Location: dashboard.php?month=" . $month . "&year=" . $year);
                exit;
            } else {
                $error = "Có lỗi xảy ra khi đóng góp vào mục tiêu!";
            }
        } else {
            $error = "Số tiền đóng góp hoặc mục tiêu không hợp lệ!";
        }
    }
}

// Get dashboard data
$dashboard = getDashboardData($user_id, $db, $month, $year);
$metrics_comparison = getMetricsComparison($user_id, $db, $month, $year);
$recent_transactions_result = getRecentTransactions($user_id, $db, 5);
$category_stats_result = getCategoryStats($user_id, $db, 'chi', $month, $year);
$expense_comparison = getMonthlyExpenseComparison($user_id, $db, $month, $year);
$expense_trend = getRecentExpenseSummary($user_id, $db, 6);
$income_expense_trend = getRecentIncomeExpenseSummary($user_id, $db, 6);
$is_budget_exceeded = isBudgetExceeded($user_id, $db, $month, $year);

// Store transactions in array for multiple use
$recent_transactions = [];
while ($row = $recent_transactions_result->fetch_assoc()) {
    $recent_transactions[] = $row;
}

$months_labels = $income_expense_trend['labels'];
$monthly_income = $income_expense_trend['income'];
$monthly_expense = $income_expense_trend['expense'];

// Process category stats for charts and tables
$category_stats = [];
$total_category_spending = 0;
while ($row = $category_stats_result->fetch_assoc()) {
    $row['total'] = (float) $row['total'];
    $category_stats[] = $row;
    $total_category_spending += $row['total'];
}

$pie_data = [];
$other_total = 0;
$count = 0;
foreach ($category_stats as $row) {
    if ($count < 3) {
        $pie_data[] = ['label' => $row['name'], 'value' => $row['total']];
    } else {
        $other_total += $row['total'];
    }
    $count++;
}
if ($other_total > 0) {
    $pie_data[] = ['label' => 'Khác', 'value' => $other_total];
}

$top_spending_data = array_slice($category_stats, 0, 5);

$chart_colors = ['#4f8bff', '#6db3ff', '#7dc7ff', '#ff759c', '#ffb26b', '#45c49f', '#9966FF', '#FF6384', '#36A2EB', '#FFCE56'];

if (!empty($dashboard['budget'])) {
    $percentage = ($dashboard['expenses'] / $dashboard['budget']) * 100;
    $display_percentage = min(100, round($percentage, 1));
    if ($percentage > 100) {
        $progress_class = 'bg-danger';
    } elseif ($percentage > 90) {
        $progress_class = 'bg-warning';
    } elseif ($percentage > 70) {
        $progress_class = 'bg-warning';
    } else {
        $progress_class = 'bg-success';
    }
} else {
    $display_percentage = 0;
    $progress_class = 'bg-success';
}

$page_title = 'Dashboard - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle"></i> <?php echo e($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?php echo e($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row align-items-end mb-4">
    <div class="col-md-6">
        <h1 class="mb-2 fw-bold text-primary">
            <i class="fas fa-chart-pie me-2"></i>Tổng quan tài chính
        </h1>
        <p class="text-muted">Phân tích và theo dõi số liệu tài chính cá nhân trong tháng.</p>
    </div>
    <div class="col-md-6 d-flex dashboard-filters gap-2 justify-content-md-end mt-3 mt-md-0">
        <div class="d-flex gap-2 w-100">
            <select class="form-select" id="monthSelect" onchange="changeMonth()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                    Tháng <?php echo $m; ?>
                </option>
                <?php endfor; ?>
            </select>
            <select class="form-select" id="yearSelect" onchange="changeMonth()">
                <?php for ($y = 2024; $y <= 2027; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                    Năm <?php echo $y; ?>
                </option>
                <?php endfor; ?>
            </select>
            <a href="<?php echo BASE_URL; ?>export.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-primary export-btn">
                <i class="fas fa-file-download"></i>
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm metric-card metric-assets h-100">
            <div class="card-body d-flex justify-content-between align-items-center gap-3">
                <div>
                    <p class="text-uppercase fw-semibold text-muted small mb-2"><i class="fas fa-vault me-1"></i> Tổng tài sản</p>
                    <h3 class="mb-2 fw-bold text-primary"><?php echo formatCurrency($dashboard['total_assets']); ?></h3>
                    <div class="metric-trend <?php echo $metrics_comparison['assets_percent'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-<?php echo $metrics_comparison['assets_percent'] >= 0 ? 'arrow-trend-up' : 'arrow-trend-down'; ?>"></i>
                        <span><?php echo abs($metrics_comparison['assets_percent']); ?>% <?php echo $metrics_comparison['assets_percent'] >= 0 ? 'tăng' : 'giảm'; ?> so với tháng trước</span>
                    </div>
                </div>
                <div class="metric-icon bg-primary text-white">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm metric-card metric-income h-100">
            <div class="card-body d-flex justify-content-between align-items-center gap-3">
                <div>
                    <p class="text-uppercase fw-semibold text-muted small mb-2"><i class="fas fa-hand-holding-dollar me-1"></i> Tổng thu</p>
                    <h3 class="mb-2 text-success fw-bold"><?php echo formatCurrency($dashboard['income']); ?></h3>
                    <div class="metric-trend <?php echo $metrics_comparison['income_percent'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-<?php echo $metrics_comparison['income_percent'] >= 0 ? 'arrow-trend-up' : 'arrow-trend-down'; ?>"></i>
                        <span><?php echo abs($metrics_comparison['income_percent']); ?>% <?php echo $metrics_comparison['income_percent'] >= 0 ? 'tăng' : 'giảm'; ?> so với tháng trước</span>
                    </div>
                </div>
                <div class="metric-icon bg-success text-white">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm metric-card metric-expense h-100">
            <div class="card-body d-flex justify-content-between align-items-center gap-3">
                <div>
                    <p class="text-uppercase fw-semibold text-muted small mb-2"><i class="fas fa-file-invoice-dollar me-1"></i> Tổng chi</p>
                    <h3 class="mb-2 text-danger fw-bold"><?php echo formatCurrency($dashboard['expenses']); ?></h3>
                    <div class="metric-trend <?php echo $metrics_comparison['expense_percent'] >= 0 ? 'text-danger' : 'text-success'; ?>">
                        <i class="fas fa-<?php echo $metrics_comparison['expense_percent'] >= 0 ? 'arrow-trend-up' : 'arrow-trend-down'; ?>"></i>
                        <span><?php echo abs($metrics_comparison['expense_percent']); ?>% <?php echo $metrics_comparison['expense_percent'] >= 0 ? 'tăng' : 'giảm'; ?> so với tháng trước</span>
                    </div>
                </div>
                <div class="metric-icon bg-danger text-white">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm metric-card metric-balance h-100">
            <div class="card-body d-flex justify-content-between align-items-center gap-3">
                <div>
                    <p class="text-uppercase fw-semibold text-muted small mb-2"><i class="fas fa-scale-balanced me-1"></i> Số dư hiện tại</p>
                    <h3 class="mb-2 <?php echo $dashboard['income_expense_diff'] >= 0 ? 'text-info' : 'text-warning'; ?> fw-bold">
                        <?php echo formatCurrency($dashboard['income_expense_diff']); ?>
                    </h3>
                    <div class="metric-trend <?php echo $dashboard['income_expense_diff'] >= 0 ? 'text-success' : 'text-warning'; ?>">
                        <i class="fas fa-<?php echo $dashboard['income_expense_diff'] >= 0 ? 'face-smile' : 'face-frown'; ?>"></i>
                        <span><?php echo $dashboard['income_expense_diff'] >= 0 ? 'Tốt - Tài chính ổn định' : 'Cần chú ý - Chi vượt thu'; ?></span>
                    </div>
                </div>
                <div class="metric-icon bg-info text-white">
                    <i class="fa-solid fa-piggy-bank"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100 chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h6 class="card-title mb-1 fw-bold text-primary"><i class="fas fa-chart-area me-2"></i>Thu - Chi trong tháng</h6>
                        <p class="text-muted small mb-0">Theo dõi dòng tiền hàng ngày và xu hướng chi tiêu.</p>
                    </div>
                    <span class="badge bg-light text-primary border"><i class="far fa-calendar-alt me-1"></i> Theo tháng</span>
                </div>
                <canvas id="barChart" height="300"></canvas>
                <div class="chart-summary row text-center mt-4 g-3">
                    <div class="col-4">
                        <div class="summary-value text-success fw-bold"><?php echo formatCurrency($dashboard['income']); ?></div>
                        <small class="text-muted">Thu</small>
                    </div>
                    <div class="col-4">
                        <div class="summary-value text-danger fw-bold"><?php echo formatCurrency($dashboard['expenses']); ?></div>
                        <small class="text-muted">Chi</small>
                    </div>
                    <div class="col-4">
                        <div class="summary-value <?php echo $dashboard['income_expense_diff'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo formatCurrency($dashboard['income_expense_diff']); ?></div>
                        <small class="text-muted">Số dư</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100 chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h6 class="card-title mb-1 fw-bold text-warning"><i class="fas fa-chart-pie me-2"></i>Chi tiêu theo danh mục</h6>
                        <p class="text-muted small mb-0">Xem phần trăm chi tiêu lớn nhất.</p>
                    </div>
                    <span class="badge bg-light text-warning border"><i class="fas fa-trophy me-1"></i> Top 3</span>
                </div>
                <canvas id="pieChart" height="300"></canvas>
                <div class="category-list mt-4">
                    <?php foreach ($pie_data as $index => $category): ?>
                    <div class="category-item d-flex justify-content-between align-items-center">
                        <div class="category-label">
                            <span class="category-dot" style="background-color: <?php echo $chart_colors[$index % count($chart_colors)]; ?>"></span>
                            <?php echo e($category['label']); ?>
                        </div>
                        <div class="text-muted small"><?php echo formatCurrency($category['value']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fetch active savings goals and wallets
$savings_goals = getSavingsGoals($user_id, $db, 'active');
$wallets_for_modal = getAllWalletsWithBalance($user_id, $db);
?>
<div class="row g-2 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 dashboard-summary-card">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-2">
                    <div>
                        <p class="text-uppercase text-muted small mb-1" style="letter-spacing:.08em;font-size:.7rem;"><i class="fas fa-bullseye text-primary me-1"></i> Mục tiêu tiết kiệm</p>
                        <h6 class="fw-bold mb-0 text-success"><i class="fas fa-piggy-bank me-2"></i>Tiến độ mục tiêu</h6>
                    </div>
                    <a href="<?php echo BASE_URL; ?>savings.php" class="btn btn-sm btn-outline-primary rounded-circle"><i class="fas fa-arrow-right"></i></a>
                </div>
                <?php if ($savings_goals && $savings_goals->num_rows > 0): ?>
                    <?php while ($goal = $savings_goals->fetch_assoc()): ?>
                        <?php
                            $dash_current = floatval($goal['current_amount_live'] ?? $goal['current_amount']);
                            $percent = $goal['target_amount'] > 0 ? min(100, ($dash_current / $goal['target_amount']) * 100) : 0;
                        ?>
                        <div class="mb-3 p-2 rounded-3" style="background:rgba(79,139,255,.05);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small"><?php echo e($goal['name']); ?></strong>
                                <span class="badge <?php echo $percent >= 100 ? 'bg-success' : ($percent >= 70 ? 'bg-warning text-dark' : 'bg-primary'); ?>"><?php echo round($percent); ?>%</span>
                            </div>
                            <div class="progress mb-1" style="height:6px;border-radius:6px;">
                                <div class="progress-bar <?php echo $percent >= 100 ? 'bg-success' : ($percent >= 70 ? 'bg-warning' : 'bg-primary'); ?>" role="progressbar" style="width:<?php echo $percent; ?>%;border-radius:6px;"></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted"><?php echo formatCurrency($dash_current); ?> / <?php echo formatCurrency($goal['target_amount']); ?></small>
                                <button class="btn btn-sm btn-outline-primary py-0 px-2 contribute-btn"
                                    data-goal-id="<?php echo $goal['id']; ?>"
                                    data-goal-name="<?php echo e($goal['name']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#contributeModal"
                                    style="font-size:.72rem;">Đóng góp</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-bullseye text-muted mb-2" style="font-size:2rem;"></i>
                        <p class="text-muted small mb-2">Chưa có mục tiêu nào.</p>
                        <a href="<?php echo BASE_URL; ?>savings.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Tạo mục tiêu</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Contribution Modal -->
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
                    <input type="hidden" name="goal_id" id="modal_goal_id" value="0">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="modal_goal_name_display" class="form-label fw-semibold">Mục tiêu</label>
                            <input type="text" class="form-control" id="modal_goal_name_display" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="contribute_amount" class="form-label fw-semibold">Số tiền đóng góp <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="any" min="1" class="form-control" name="amount" id="contribute_amount" required placeholder="0">
                                <span class="input-group-text"><?php echo CURRENCY; ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="contribute_wallet_id" class="form-label fw-semibold">Khấu trừ từ ví <small class="text-muted">(tùy chọn)</small></label>
                            <select class="form-select" name="wallet_id" id="contribute_wallet_id">
                                <option value="">Không khấu trừ</option>
                                <?php foreach ($wallets_for_modal as $w): ?>
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

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 dashboard-summary-card">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-2">
                    <div>
                        <p class="text-uppercase text-muted small mb-1" style="letter-spacing:.08em;font-size:.7rem;"><i class="fas fa-wallet text-danger me-1"></i> Ngân sách</p>
                        <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-file-invoice-dollar me-2"></i>Tháng <?php echo $month; ?>/<?php echo $year; ?></h6>
                    </div>
                    <?php
                        $pct = round($display_percentage ?? 0, 0);
                        $badgeColor = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning text-dark' : 'bg-success');
                    ?>
                    <span class="badge <?php echo $badgeColor; ?>"><?php echo $pct; ?>%</span>
                </div>

                <!-- Progress bar -->
                <div class="mb-3">
                    <div class="progress" style="height:10px;border-radius:8px;">
                        <div class="progress-bar <?php echo $progress_class ?? 'bg-success'; ?>" role="progressbar"
                             style="width:<?php echo $display_percentage ?? 0; ?>%;border-radius:8px;transition:width .6s ease;"></div>
                    </div>
                </div>

                <!-- Giới hạn / Đã tiêu / Còn lại -->
                <div class="d-flex flex-column gap-2 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:8px;height:8px;border-radius:50%;background:#dde3f7;"></div>
                            <small class="text-muted">Giới hạn</small>
                        </div>
                        <small class="fw-semibold"><?php echo formatCurrency($dashboard['budget']); ?></small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:8px;height:8px;border-radius:50%;background:var(--danger-color);"></div>
                            <small class="text-muted">Đã tiêu</small>
                        </div>
                        <small class="fw-semibold text-danger"><?php echo formatCurrency($dashboard['expenses']); ?></small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:8px;height:8px;border-radius:50%;background:var(--success-color);"></div>
                            <small class="text-muted">Còn lại</small>
                        </div>
                        <small class="fw-semibold text-success"><?php echo formatCurrency(max(0,$dashboard['budget_remaining'])); ?></small>
                    </div>
                </div>

                <!-- Số ngày còn lại & tốc độ chi tiêu -->
                <?php
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $today = (int)date('j');
                    $daysLeft = max(0, $daysInMonth - $today);
                    $dailyRate = $today > 0 ? $dashboard['expenses'] / $today : 0;
                    $projectedTotal = $dailyRate * $daysInMonth;
                ?>
                <div class="row g-2 mt-auto">
                    <div class="col-6">
                        <div class="p-2 rounded-3 text-center" style="background:rgba(79,139,255,.07);">
                            <div class="fw-bold text-primary" style="font-size:1.1rem;"><?php echo $daysLeft; ?></div>
                            <div class="text-muted" style="font-size:.68rem;">Ngày còn lại</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3 text-center" style="background:rgba(255,178,107,.07);">
                            <div class="fw-bold text-warning" style="font-size:1.1rem;"><?php echo formatCurrency($dailyRate); ?></div>
                            <div class="text-muted" style="font-size:.68rem;">Chi mỗi ngày</div>
                        </div>
                    </div>
                </div>

                <!-- Cảnh báo -->
                <?php if ($dashboard['budget_exceeded']): ?>
                <div class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size:.8rem;">
                    <i class="fas fa-exclamation-circle me-1"></i> Vượt ngân sách <?php echo formatCurrency($dashboard['budget_overflow']); ?>
                </div>
                <?php elseif ($pct >= 80): ?>
                <div class="alert alert-warning mt-3 mb-0 py-2 px-3" style="font-size:.8rem;">
                    <i class="fas fa-triangle-exclamation me-1"></i> Gần đạt giới hạn ngân sách!
                </div>
                <?php else: ?>
                <div class="alert alert-info mt-3 mb-0 py-2 px-3" style="font-size:.8rem;">
                    <i class="fas fa-circle-check me-1"></i> Còn lại <strong><?php echo formatCurrency($dashboard['budget_remaining']); ?></strong> để chi an toàn.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 ai-advisor-card">
            <div class="card-body d-flex flex-column justify-content-between h-100">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-robot text-info fs-4"></i>
                            <h6 class="fw-bold mb-0 text-info">AI Financial Advisor</h6>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="aiRefreshBtn" onclick="loadAiAdvice(true)">
                            Phân tích AI
                        </button>
                    </div>
                    
                    <div id="aiLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                        <p class="text-muted small mt-2">AI đang phân tích dữ liệu...</p>
                    </div>

                    <div id="aiContent" style="display: none; font-size: 0.85rem; line-height: 1.5;">
                        <!-- JS sẽ đổ dữ liệu 5 mục vào đây -->
                    </div>

                    <div id="aiError" style="display: none;" class="alert alert-danger py-2 px-3 small mb-0 mt-3">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="aiErrorMsg">Không thể tải phân tích</span>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm mt-3 w-100" id="aiDetailBtn" onclick="openAiDetailModal()" style="display: none;">
                    <i class="fas fa-arrow-right me-2"></i>Xem chi tiết đầy đủ
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100 transaction-card">
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-history me-2"></i>Giao Dịch Gần Đây</h6>
                    <a href="<?php echo BASE_URL; ?>transactions.php" class="text-primary small text-decoration-none fw-semibold">Xem tất cả <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Ngày</th>
                                <th>Ví</th>
                                <th>Nội dung</th>
                                <th>Danh mục</th>
                                <th>Số tiền</th>
                                <th class="pe-3">Loại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $trans_count = 0;
                            if (count($recent_transactions) > 0):
                                foreach ($recent_transactions as $trans): 
                                    if ($trans_count >= 5) break;
                                    $is_income = strtolower($trans['category_type']) == 'thu';
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <small class="text-muted"><?php echo formatDate($trans['transaction_date'], 'd/m/Y'); ?></small>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: #e3f2fd; color: #1976d2;">
                                        <?php echo e($trans['wallet_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: #f8f9fa; color: #495057; border: 1px solid #dee2e6;">
                                        <i class="fas fa-tag text-muted me-1"></i> <?php echo e($trans['category_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="fw-semibold text-muted"><?php echo e($trans['category_name']); ?></small>
                                </td>
                                <td>
                                    <small class="fw-bold">
                                        <?php echo formatCurrency($trans['amount']); ?>
                                    </small>
                                </td>
                                <td class="pe-3">
                                    <span class="badge <?php echo $is_income ? 'bg-success' : 'bg-danger'; ?> text-uppercase">
                                        <?php echo $is_income ? 'Thu' : 'Chi'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php 
                                    $trans_count++;
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center p-4 text-muted">
                                    Chưa có giao dịch nào
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100 top-spending-card">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-fire me-2"></i>Top Chi Tiêu Tháng <?php echo $month; ?></h6>
            </div>
            <div class="card-body p-0">
                <div class="top-spending-list">
                    <?php 
                    if (count($top_spending_data) > 0):
                        foreach ($top_spending_data as $top): 
                    ?>
                    <div class="spending-item d-flex justify-content-between align-items-center p-3 border-bottom">
                        <div class="flex-grow-1 d-flex align-items-center gap-3">
                            <div class="spending-icon bg-light text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark"><?php echo e($top['name']); ?></div>
                                <small class="text-muted"><i class="fas fa-receipt me-1"></i><?php echo $top['count']; ?> giao dịch</small>
                            </div>
                        </div>
                        <div class="text-end fw-bold text-danger">
                            <?php echo formatCurrency($top['total']); ?>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <div class="p-3 text-center text-muted">
                        <p class="mb-0">Chưa có dữ liệu chi tiêu</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeMonth() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = '?month=' + month + '&year=' + year;
}

// Pie Chart Data
let pieData = <?php echo json_encode($pie_data); ?>;
const maxCategories = 3;

if (pieData.length > maxCategories) {
    const topCategories = pieData.slice(0, maxCategories);
    const otherTotal = pieData.slice(maxCategories).reduce((sum, cat) => sum + cat.value, 0);
    topCategories.push({ label: 'Khác', value: otherTotal });
    pieData = topCategories;
}

const pieLabels = pieData.map(d => d.label);
const pieValues = pieData.map(d => d.value);

const colors = [
    '#4f8bff', '#6db3ff', '#7dc7ff', '#ff759c', '#ffb26b',
    '#45c49f', '#9966FF', '#FF6384', '#36A2EB', '#FFCE56'
];

const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: pieLabels,
        datasets: [{
            data: pieValues,
            backgroundColor: colors.slice(0, pieLabels.length),
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Bar Chart Data
const monthLabels = <?php echo json_encode($months_labels); ?>;
const incomeData = <?php echo json_encode($monthly_income); ?>;
const expenseData = <?php echo json_encode($monthly_expense); ?>;

const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [
            {
                label: 'Thu',
                data: incomeData,
                borderColor: '#45c49f',
                backgroundColor: 'rgba(69, 196, 159, 0.1)',
                tension: 0.35,
                pointBackgroundColor: '#45c49f',
                pointBorderColor: '#45c49f',
                fill: true
            },
            {
                label: 'Chi',
                data: expenseData,
                borderColor: '#ff6b6b',
                backgroundColor: 'rgba(255, 107, 107, 0.12)',
                tension: 0.35,
                pointBackgroundColor: '#ff6b6b',
                pointBorderColor: '#ff6b6b',
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                position: 'top'
            }
        }
    }
});

</script>

<!-- AI Detail Modal -->
<div class="modal fade" id="aiDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary bg-opacity-10 border-0">
                <h5 class="modal-title">
                    <i class="fas fa-sparkles text-warning me-2"></i>Chi tiết phân tích AI
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="aiDetailContent">
                    <!-- Content filled by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store current advice data
let currentAdvice = null;

// Load AI advice on page load
document.addEventListener('DOMContentLoaded', function() {
    loadAiAdvice();
});

function loadAiAdvice(force = false) {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    
    const aiLoading = document.getElementById('aiLoading');
    const aiContent = document.getElementById('aiContent');
    const aiError = document.getElementById('aiError');
    const aiRefreshBtn = document.getElementById('aiRefreshBtn');
    
    aiLoading.style.display = 'block';
    aiContent.style.display = 'none';
    aiError.style.display = 'none';
    aiRefreshBtn.disabled = true;
    
    fetch('<?php echo BASE_URL; ?>api/ai_analysis.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ month: month, year: year, force: force })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.advice) {
            displayAiAdvice(data.advice);
            currentAdvice = data.advice;
            aiContent.style.display = 'block';
            document.getElementById('aiDetailBtn').style.display = 'block';
        } else {
            showAiError(data.error || 'Không thể tải phân tích');
        }
    })
    .catch(error => {
        showAiError('Lỗi kết nối: ' + error.message);
    })
    .finally(() => {
        aiLoading.style.display = 'none';
        aiRefreshBtn.disabled = false;
    });
}

function formatCurrencyJS(amount) {
    if (amount === undefined || amount === null || isNaN(amount)) return '0 VNĐ';
    return Math.round(Number(amount)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' VNĐ';
}

function displayAiAdvice(advice) {
    const contentDiv = document.getElementById('aiContent');
    let html = '';

    // 1. Tóm tắt
    if (advice.summary) {
        html += `<div class="mb-2 d-flex align-items-center gap-2">
            <span class="fs-5">📊</span>
            <div>
                <strong class="d-block mb-0 text-dark" style="font-size: 0.9rem;">Tóm tắt tình hình</strong>
                <span class="text-muted small">Phân tích tổng quan thu chi</span>
            </div>
        </div>`;
    }

    // 2. Xu hướng
    if (advice.spending_trend) {
        html += `<div class="mb-2 d-flex align-items-center gap-2">
            <span class="fs-5">📈</span>
            <div>
                <strong class="d-block mb-0 text-dark" style="font-size: 0.9rem;">Xu hướng chi tiêu</strong>
                <span class="text-muted small">Đánh giá danh mục chi lớn</span>
            </div>
        </div>`;
    }

    // 3. Phát hiện
    if (advice.anomalies) {
        const hasAnomalies = advice.anomalies.found && advice.anomalies.items && advice.anomalies.items.length > 0;
        html += `<div class="mb-2 d-flex align-items-center gap-2">
            <span class="fs-5">⚠</span>
            <div>
                <strong class="d-block mb-0 text-dark" style="font-size: 0.9rem;">Phát hiện bất thường</strong>
                <span class="text-muted small ${hasAnomalies ? 'text-danger fw-semibold' : ''}">${hasAnomalies ? 'Có khoản chi cần chú ý' : 'Không có bất thường'}</span>
            </div>
        </div>`;
    }

    // 4. Dự đoán
    if (advice.prediction) {
        html += `<div class="mb-2 d-flex align-items-center gap-2">
            <span class="fs-5">🔮</span>
            <div>
                <strong class="d-block mb-0 text-dark" style="font-size: 0.9rem;">Dự đoán & Cảnh báo</strong>
                <span class="text-muted small">Dự báo rủi ro cuối tháng</span>
            </div>
        </div>`;
    }

    // 5. Khuyến nghị
    if (advice.recommendations && advice.recommendations.length > 0) {
        html += `<div class="mb-2 d-flex align-items-center gap-2">
            <span class="fs-5">💡</span>
            <div>
                <strong class="d-block mb-0 text-dark" style="font-size: 0.9rem;">Khuyến nghị từ AI</strong>
                <span class="text-muted small">Giải pháp tối ưu tài chính</span>
            </div>
        </div>`;
    }

    contentDiv.innerHTML = `<div class="d-flex flex-column gap-1">${html}</div>`;
    contentDiv.style.display = 'block';
}

function showAiError(errorMsg) {
    document.getElementById('aiLoading').style.display = 'none';
    document.getElementById('aiContent').style.display = 'none';
    document.getElementById('aiErrorMsg').textContent = errorMsg;
    document.getElementById('aiError').style.display = 'block';
}

function getStatusColor(status) {
    if (!status) return '#9ca3af';
    
    if (status.includes('ổn định') || status.includes('Tốt') || status.includes('tốt')) {
        return '#45c49f';
    } else if (status.includes('Cần') || status.includes('cải thiện')) {
        return '#ffb26b';
    } else if (status.includes('Rất cần') || status.includes('Yếu') || status.includes('chú ý')) {
        return '#ff6b6b';
    }
    return '#4f8bff';
}

function openAiDetailModal() {
    if (!currentAdvice) return;

    const detailContent = document.getElementById('aiDetailContent');
    const statusColor = getStatusColor(currentAdvice.status);

    let html = `
    <div class="ai-detail-full">
        <!-- Overview Card -->
        <div class="card border-0 mb-4 shadow-sm" style="background-color: #f8f9fa;">
            <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm" style="width: 70px; height: 70px; border: 4px solid ${statusColor};">
                        <span class="fw-bold fs-4" style="color: ${statusColor};">${currentAdvice.score || 0}</span>
                    </div>
                    <div>
                        <div class="badge px-3 py-2 mb-1" style="background-color: ${statusColor}; color: white; border-radius: 20px; font-weight: 600;">
                            ${currentAdvice.status || '—'}
                        </div>
                        <div class="text-muted small">Chỉ số đánh giá tổng quan từ Cố vấn tài chính AI</div>
                    </div>
                </div>
                ${currentAdvice.warning && currentAdvice.warning.trim() ? `
                <div class="alert alert-danger py-2 px-3 mb-0 flex-grow-1 flex-md-grow-0" style="font-size: 0.82rem; border: none; border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle me-2 text-danger"></i>${currentAdvice.warning}
                </div>` : ''}
            </div>
        </div>

        <div class="row g-3">
            <!-- 1. Tóm tắt tình hình tài chính -->
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>1. Tóm tắt tình hình</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 rounded text-center" style="background-color: rgba(25, 135, 84, 0.08);">
                                    <div class="text-muted small mb-1" style="font-size: 0.72rem;">Thu nhập</div>
                                    <div class="fw-bold text-success mb-1" style="font-size: 0.85rem;">
                                        ${currentAdvice.summary?.income_amount || '—'}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500;">
                                        ${currentAdvice.summary?.income_change && currentAdvice.summary.income_change !== 'N/A' ? `${currentAdvice.summary.income_change} so với t.trước` : 'N/A so với t.trước'}
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded text-center" style="background-color: rgba(220, 53, 69, 0.08);">
                                    <div class="text-muted small mb-1" style="font-size: 0.72rem;">Chi tiêu</div>
                                    <div class="fw-bold text-danger mb-1" style="font-size: 0.85rem;">
                                        ${currentAdvice.summary?.expense_amount || '—'}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.68rem; font-weight: 500;">
                                        ${currentAdvice.summary?.expense_change && currentAdvice.summary.expense_change !== 'N/A' ? `${currentAdvice.summary.expense_change} so với t.trước` : 'N/A so với t.trước'}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-2 rounded text-center" style="background-color: rgba(13, 110, 253, 0.08);">
                                    <div class="text-muted small mb-1" style="font-size: 0.72rem;">Số dư hiện tại</div>
                                    <div class="fw-bold text-primary" style="font-size: 0.95rem;">
                                        ${currentAdvice.summary?.balance || 'N/A'}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mb-0 bg-light p-2 rounded" style="font-size: 0.8rem; line-height: 1.4;">
                            ${currentAdvice.summary?.note || '—'}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Phân tích xu hướng chi tiêu -->
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-chart-line me-2"></i>2. Xu hướng chi tiêu</h6>
                    </div>
                    <div class="card-body">
                        <div class="p-2 rounded mb-3" style="background-color: #f8f9fa;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small" style="font-size: 0.75rem;">Chi nhiều nhất</span>
                                <span class="badge bg-warning text-dark">${currentAdvice.spending_trend?.top_category_percent || '—'}</span>
                            </div>
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;">${currentAdvice.spending_trend?.top_category || 'Chưa có dữ liệu'}</div>
                        </div>
                        ${currentAdvice.spending_trend?.rising_categories && currentAdvice.spending_trend.rising_categories.length > 0 ? `
                        <div class="mb-3">
                            <div class="text-muted small mb-1" style="font-size: 0.72rem;">Danh mục tăng cao:</div>
                            <div class="d-flex flex-wrap gap-1">
                                ${currentAdvice.spending_trend.rising_categories.map(c => `<span class="badge bg-danger bg-opacity-10 text-danger" style="font-size: 0.7rem; font-weight: 500;">${c}</span>`).join('')}
                            </div>
                        </div>` : ''}
                        <p class="small text-muted mb-0 bg-light p-2 rounded" style="font-size: 0.8rem; line-height: 1.4;">
                            ${currentAdvice.spending_trend?.note || '—'}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 3. Phát hiện bất thường -->
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-shield-alt me-2"></i>3. Phát hiện bất thường</h6>
                    </div>
                    <div class="card-body">
                        ${currentAdvice.anomalies?.found && currentAdvice.anomalies.items && currentAdvice.anomalies.items.length > 0 ? `
                        <div class="alert alert-danger py-2 px-3 mb-2" style="font-size: 0.8rem; border: none; border-radius: 6px;">
                            <i class="fas fa-exclamation-triangle me-2"></i>Cần chú ý khoản chi đột biến
                        </div>
                        <ul class="ps-3 mb-0 small text-danger" style="font-size: 0.8rem; line-height: 1.4;">
                            ${currentAdvice.anomalies.items.map(item => `<li>${item}</li>`).join('')}
                        </ul>
                        ` : `
                        <div class="alert alert-success py-2 px-3 mb-0" style="font-size: 0.8rem; border: none; border-radius: 6px; background-color: rgba(25, 135, 84, 0.08); color: #198754;">
                            <i class="fas fa-check-circle me-2"></i>Không phát hiện khoản chi tiêu nào bất thường trong tháng này.
                        </div>
                        `}
                    </div>
                </div>
            </div>

            <!-- 4. Dự đoán cuối tháng -->
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100 bg-white">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-magic me-2"></i>4. Dự đoán & Cảnh báo</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded" style="background-color: #f8f9fa;">
                            <div>
                                <div class="text-muted small" style="font-size: 0.72rem;">Dự kiến chi cuối tháng</div>
                                <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                    ${currentAdvice.prediction?.projected_expense ? formatCurrencyJS(currentAdvice.prediction.projected_expense) : '—'}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Rủi ro vượt quỹ</div>
                                <span class="badge ${currentAdvice.prediction?.risk === 'Cao' ? 'bg-danger' : currentAdvice.prediction?.risk === 'Trung bình' ? 'bg-warning text-dark' : 'bg-success'}">
                                    ${currentAdvice.prediction?.risk || 'Thấp'}
                                </span>
                            </div>
                        </div>
                        <p class="small text-muted mb-0 bg-light p-2 rounded" style="font-size: 0.8rem; line-height: 1.4;">
                            ${currentAdvice.prediction?.note || '—'}
                        </p>
                    </div>
                </div>
            </div>

            <!-- 5. Khuyến nghị & Tối ưu -->
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-white">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="fw-bold text-primary mb-0"><i class="fas fa-lightbulb me-2 text-warning"></i>5. Khuyến nghị từ Cố vấn AI</h6>
                    </div>
                    <div class="card-body pt-2">
                        ${currentAdvice.recommendations && currentAdvice.recommendations.length > 0 ? `
                        <div class="list-group list-group-flush border-0">
                            ${currentAdvice.recommendations.map(rec => `
                                <div class="list-group-item px-0 py-2 border-0 d-flex gap-2 align-items-start small text-muted" style="font-size: 0.82rem;">
                                    <i class="fas fa-check-circle text-success mt-1" style="font-size: 0.85rem; flex-shrink: 0;"></i>
                                    <span>${rec}</span>
                                </div>
                            `).join('')}
                        </div>
                        ` : `
                        <p class="small text-muted mb-0">Không có khuyến nghị cụ thể nào.</p>
                        `}
                    </div>
                </div>
            </div>

        </div>
    </div>
    `;
    
    detailContent.innerHTML = html;
    new bootstrap.Modal(document.getElementById('aiDetailModal')).show();
}

</script>

<?php if (!empty($success)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss success alert after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(a => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(a);
            if (bsAlert) bsAlert.close();
        });
    }, 5000);
});
</script>
<?php endif; ?>

<?php if (!empty($completed_goal)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show a celebration alert for completed goal
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show shadow-lg';
    alertDiv.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:350px;border-left:5px solid #198754;';
    alertDiv.innerHTML = '<div class="d-flex align-items-center gap-2"><i class="fas fa-trophy text-warning" style="font-size:1.5rem;"></i><div><strong>Chúc mừng!</strong><br>Mục tiêu <strong><?php echo e($completed_goal); ?></strong> đã hoàn thành!</div></div><button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    document.body.appendChild(alertDiv);
    setTimeout(function() { alertDiv.remove(); }, 8000);
});
</script>
<?php endif; ?>

<script>
// Contribute modal population
document.addEventListener('DOMContentLoaded', function() {
    const contributeModal = document.getElementById('contributeModal');
    if (contributeModal) {
        contributeModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button) {
                document.getElementById('modal_goal_id').value = button.getAttribute('data-goal-id');
                document.getElementById('modal_goal_name_display').value = button.getAttribute('data-goal-name');
                document.getElementById('contribute_amount').value = '';
            }
        });
    }
});
</script>

<?php include 'views/footer.php'; ?>
