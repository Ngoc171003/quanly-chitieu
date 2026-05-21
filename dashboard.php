<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get dashboard data
$dashboard = getDashboardData($user_id, $db, $month, $year);
$recent_transactions = getRecentTransactions($user_id, $db, 5);
$category_stats = getCategoryStats($user_id, $db, 'chi', $month, $year);
$monthly_summary = getMonthlySummary($user_id, $db, $year);
$is_budget_exceeded = isBudgetExceeded($user_id, $db, $month, $year);
$wallets_result = getUserWallets($user_id, $db);
$wallets = [];
while ($row = $wallets_result->fetch_assoc()) {
    $wallets[] = $row;
}

// Compute wallet groups for modal details
$wallet_cash_total = 0;
$wallet_account_total = 0;
$wallet_savings_total = 0;
foreach ($wallets as $w) {
    $wallet_name_lower = mb_strtolower($w['name'], 'UTF-8');
    if (strpos($wallet_name_lower, 'tiền mặt') !== false || strpos($wallet_name_lower, 'tien mat') !== false) {
        $wallet_cash_total += floatval($w['balance']);
    } elseif (strpos($wallet_name_lower, 'tiết kiệm') !== false || strpos($wallet_name_lower, 'tiet kiem') !== false) {
        $wallet_savings_total += floatval($w['balance']);
    } else {
        $wallet_account_total += floatval($w['balance']);
    }
}

// Get categories for pie chart
$pie_data = [];
while ($row = $category_stats->fetch_assoc()) {
    $pie_data[] = ['label' => $row['name'], 'value' => (float)$row['total']];
}

// Get monthly data for bar chart
$monthly_income = [];
$monthly_expense = [];
$months_labels = [];

while ($row = $monthly_summary->fetch_assoc()) {
    $month_num = intval($row['month']);
    $months_labels[] = 'Tháng ' . $month_num;
    $monthly_income[] = floatval($row['income'] ?? 0);
    $monthly_expense[] = floatval($row['expenses'] ?? 0);
}

$page_title = 'Dashboard - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-4">
            <i class="fas fa-chart-line"></i> Dashboard
        </h1>
    </div>
    <div class="col-md-4">
        <div class="input-group">
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
        </div>
    </div>
</div>

<!-- Key Metrics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-2"><i class="fas fa-arrow-up text-success"></i> Tổng Thu</p>
                        <h3 class="text-success"><?php echo formatCurrency($dashboard['income']); ?></h3>
                    </div>
                    <span class="badge bg-success rounded-circle p-3">
                        <i class="fas fa-wallet"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-2"><i class="fas fa-arrow-down text-danger"></i> Tổng Chi</p>
                        <h3 class="text-danger"><?php echo formatCurrency($dashboard['expenses']); ?></h3>
                    </div>
                    <span class="badge bg-danger rounded-circle p-3">
                        <i class="fas fa-shopping-bag"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <a href="#" class="text-decoration-none text-reset" data-bs-toggle="modal" data-bs-target="#walletDetailModal">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-2"><i class="fas fa-coins text-warning"></i> Tổng Số Dư Ví</p>
                            <h3 class="text-warning"><?php echo formatCurrency($dashboard['wallet_total']); ?></h3>
                        </div>
                        <span class="badge bg-warning rounded-circle p-3">
                            <i class="fas fa-piggy-bank"></i>
                        </span>
                    </div>
                    <div class="mt-3 text-sm text-muted">
                        Xem chi tiết số dư ví
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-2"><i class="fas fa-balance-scale text-info"></i> Số Dư Tài Khoản</p>
                        <h3 class="<?php echo $dashboard['balance'] >= 0 ? 'text-info' : 'text-danger'; ?>">
                            <?php echo formatCurrency($dashboard['balance']); ?>
                        </h3>
                    </div>
                    <span class="badge bg-info rounded-circle p-3">
                        <i class="fas fa-calculator"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Budget Alert -->
<?php if ($dashboard['budget'] > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="card-title mb-2">Ngân Sách Tháng <?php echo $month; ?>/<?php echo $year; ?></h6>
                        <div class="progress" style="height: 25px;">
                            <?php 
                            $percentage = min(100, ($dashboard['expenses'] / $dashboard['budget']) * 100);
                            $progress_class = $percentage > 100 ? 'bg-danger' : ($percentage > 80 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%">
                                <?php echo round($percentage, 1); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-1">
                            <strong>Giới hạn:</strong> <?php echo formatCurrency($dashboard['budget']); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Còn lại:</strong> 
                            <span class="<?php echo $dashboard['budget_remaining'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo formatCurrency($dashboard['budget_remaining']); ?>
                            </span>
                        </p>
                        <?php if ($is_budget_exceeded): ?>
                        <div class="alert alert-danger mt-2 mb-0 py-1 px-2">
                            <i class="fas fa-exclamation-triangle"></i> Bạn đã vượt quá ngân sách!
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-4">Phân Bổ Chi Tiêu</h6>
                <canvas id="pieChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-4">Thu Chi Theo Tháng</h6>
                <canvas id="barChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-history"></i> Giao Dịch Gần Đây</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày</th>
                                <th>Danh Mục</th>
                                <th>Ghi Chú</th>
                                <th class="text-end">Số Tiền</th>
                                <th class="text-center">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($trans = $recent_transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatDate($trans['transaction_date']); ?></td>
                                <td>
                                    <span class="badge <?php echo strtolower($trans['category_type']) == 'thu' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo e($trans['category_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo e(substr($trans['note'] ?? '', 0, 20)); ?></td>
                                <td class="text-end <?php echo strtolower($trans['category_type']) == 'thu' ? 'text-success' : 'text-danger'; ?>">
                                    <strong><?php echo (strtolower($trans['category_type']) == 'thu' ? '+' : '-') . formatCurrency($trans['amount']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo BASE_URL; ?>edit-transaction.php?id=<?php echo $trans['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-center">
                <a href="<?php echo BASE_URL; ?>transactions.php" class="btn btn-sm btn-primary">
                    Xem Tất Cả
                </a>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-list"></i> Danh Mục</h6>
            </div>
            <div class="card-body">
                <?php
                $cat_query = "SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type, name";
                $categories_result = $db->execute($cat_query, [$user_id]);
                $current_type = null;
                while ($cat = $categories_result->fetch_assoc()):
                    if ($current_type !== $cat['type']):
                        if ($current_type !== null) echo '</ul>';
                        echo '<h6 class="mt-3 mb-2"><strong>' . (strtolower($cat['type']) == 'thu' ? '📥 Thu' : '📤 Chi') . '</strong></h6>';
                        echo '<ul class="list-unstyled">';
                        $current_type = $cat['type'];
                    endif;
                ?>
                    <li class="mb-2">
                        <a href="<?php echo BASE_URL; ?>transactions.php?category=<?php echo $cat['id']; ?>" class="text-decoration-none">
                            <i class="fas fa-circle text-muted" style="font-size: 6px;"></i> <?php echo e($cat['name']); ?>
                        </a>
                    </li>
                <?php endwhile; ?>
                <?php if ($current_type !== null): ?></ul><?php endif; ?>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-wallet"></i> Ví của tôi</h6>
            </div>
            <div class="card-body">
                        <?php if (count($wallets) === 0): ?>
                    <p class="text-muted">Chưa có ví nào. Hãy tạo ví trong mục Ví.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($wallets as $wallet): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo e($wallet['name']); ?>
                            <span><?php echo formatCurrency($wallet['balance']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>wallets.php" class="btn btn-sm btn-outline-primary mt-3 w-100">
                    Quản lý ví
                </a>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="mb-3">Hành Động Nhanh</h6>
                <a href="<?php echo BASE_URL; ?>add-transaction.php?type=chi" class="btn btn-danger btn-sm w-100 mb-2">
                    <i class="fas fa-plus"></i> Thêm Chi
                </a>
                <a href="<?php echo BASE_URL; ?>add-transaction.php?type=thu" class="btn btn-success btn-sm w-100 mb-2">
                    <i class="fas fa-plus"></i> Thêm Thu
                </a>
                <a href="<?php echo BASE_URL; ?>export.php" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-download"></i> Xuất Excel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Wallet Detail Modal -->
<div class="modal fade" id="walletDetailModal" tabindex="-1" aria-labelledby="walletDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="walletDetailModalLabel"><i class="fas fa-piggy-bank"></i> Chi Tiết Tổng Số Dư Ví</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="list-group mb-3">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Tiền mặt</strong>
                            <div class="text-muted small">Ví tiền mặt</div>
                        </div>
                        <span><?php echo formatCurrency($wallet_cash_total); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Số dư tài khoản</strong>
                            <div class="text-muted small">Tất cả ví không phải tiền mặt hoặc tiết kiệm</div>
                        </div>
                        <span><?php echo formatCurrency($wallet_account_total); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Khoản tiết kiệm</strong>
                            <div class="text-muted small">Ví tiết kiệm</div>
                        </div>
                        <span><?php echo formatCurrency($wallet_savings_total); ?></span>
                    </div>
                </div>
                <?php if (count($wallets) === 0): ?>
                <div class="alert alert-secondary" role="alert">
                    Bạn chưa có ví nào. Hãy tạo ví để xem báo cáo chi tiết.
                </div>
                <?php endif; ?>
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Tổng cộng</strong>
                        <strong><?php echo formatCurrency($dashboard['wallet_total']); ?></strong>
                    </div>
                    <?php if (count($wallets) > 0): ?>
                    <div class="small text-muted mb-2">Chi tiết các ví hiện có:</div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($wallets as $wallet): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo e($wallet['name']); ?>
                            <span><?php echo formatCurrency($wallet['balance']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo BASE_URL; ?>wallets.php" class="btn btn-outline-primary">Quản lý ví</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
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
const pieData = <?php echo json_encode($pie_data); ?>;
const pieLabels = pieData.map(d => d.label);
const pieValues = pieData.map(d => d.value);

const colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
    '#FF9F40', '#FF6384', '#C9CBCF'
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
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
            {
                label: 'Thu',
                data: incomeData,
                backgroundColor: '#28a745',
                borderRadius: 4
            },
            {
                label: 'Chi',
                data: expenseData,
                backgroundColor: '#dc3545',
                borderRadius: 4
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

<?php include 'views/footer.php'; ?>
