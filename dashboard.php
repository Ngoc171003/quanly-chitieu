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
$recent_transactions_result = getRecentTransactions($user_id, $db, 10);
$category_stats = getCategoryStats($user_id, $db, 'chi', $month, $year);
$monthly_summary = getMonthlySummary($user_id, $db, $year);
$is_budget_exceeded = isBudgetExceeded($user_id, $db, $month, $year);
$wallets_result = getUserWallets($user_id, $db);
$wallets = [];
while ($row = $wallets_result->fetch_assoc()) {
    $wallets[] = $row;
}

// Store transactions in array for multiple use
$recent_transactions = [];
while ($row = $recent_transactions_result->fetch_assoc()) {
    $recent_transactions[] = $row;
}

// Compute wallet groups for modal details
$wallet_cash_total = 0;
$wallet_account_total = 0;
$wallet_savings_total = 0;
foreach ($wallets as $w) {
    $wallet_type = $w['type'] ?? 'Tiền mặt';
    if ($wallet_type === 'Tiền mặt') {
        $wallet_cash_total += floatval($w['balance']);
    } elseif ($wallet_type === 'Tiết kiệm') {
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

// Get top spending categories
$top_spending_data = [];
$top_spending_result = getCategoryStats($user_id, $db, 'chi', $month, $year);
if ($top_spending_result) {
    $spending_count = 0;
    while ($top = $top_spending_result->fetch_assoc()) {
        if ($spending_count >= 5) break;
        $top_spending_data[] = $top;
        $spending_count++;
    }
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
    <div class="col-md-4 d-flex dashboard-filters gap-2 align-items-center">
        <div class="flex-grow-1">
            <select class="form-select" id="monthSelect" onchange="changeMonth()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                    Tháng <?php echo $m; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="flex-grow-1">
            <select class="form-select" id="yearSelect" onchange="changeMonth()">
                <?php for ($y = 2024; $y <= 2027; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                    Năm <?php echo $y; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <a href="<?php echo BASE_URL; ?>export.php" class="btn btn-primary export-btn" style="white-space: nowrap;">
            <i class="fas fa-download"></i> Xuất Excel
        </a>
    </div>
</div>

<!-- Key Metrics - Visual Hierarchy -->
<div class="row mb-4">
    <!-- Primary: Tổng Số Dư Ví - Large Card -->
    <div class="col-lg-6 col-md-12">
        <a href="#" class="text-decoration-none text-reset" data-bs-toggle="modal" data-bs-target="#walletDetailModal">
            <div class="card border-0 shadow-sm h-100 card-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2"><i class="fas fa-coins"></i> Tổng Số Dư Ví</p>
                            <h2 class="text-warning mb-3"><?php echo formatCurrency($dashboard['wallet_total']); ?></h2>
                            <small class="text-muted">Nhấn để xem chi tiết các ví</small>
                        </div>
                        <span class="badge bg-warning rounded-circle p-4">
                            <i class="fas fa-piggy-bank" style="font-size: 1.5rem;"></i>
                        </span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Secondary Metrics -->
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-arrow-up text-success"></i> Tổng Thu</p>
                <h4 class="text-success"><?php echo formatCurrency($dashboard['income']); ?></h4>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-arrow-down text-danger"></i> Tổng Chi</p>
                <h4 class="text-danger"><?php echo formatCurrency($dashboard['expenses']); ?></h4>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-exchange-alt text-primary"></i> Chênh Lệch</p>
                <h4 class="<?php echo $dashboard['income_expense_diff'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo formatCurrency($dashboard['income_expense_diff']); ?>
                </h4>
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
                    <div class="col-md-8">
                        <h6 class="card-title mb-3">Ngân Sách Tháng <?php echo $month; ?>/<?php echo $year; ?></h6>
                        <div class="progress" style="height: 30px;">
                            <?php 
                            $percentage = ($dashboard['expenses'] / $dashboard['budget']) * 100;
                            if ($percentage > 100) {
                                $progress_class = 'bg-danger';
                            } elseif ($percentage > 90) {
                                $progress_class = 'bg-warning';
                            } elseif ($percentage > 70) {
                                $progress_class = 'bg-warning';
                            } else {
                                $progress_class = 'bg-success';
                            }
                            $display_percentage = min(100, $percentage);
                            ?>
                            <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" 
                                 style="width: <?php echo $display_percentage; ?>%">
                                <strong><?php echo round($percentage, 1); ?>%</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end ps-md-3">
                        <div class="mb-2">
                            <small class="text-muted d-block">Giới hạn</small>
                            <strong><?php echo formatCurrency($dashboard['budget']); ?></strong>
                        </div>
                        <div>
                            <small class="text-muted d-block">Đã tiêu</small>
                            <strong><?php echo formatCurrency($dashboard['expenses']); ?></strong>
                        </div>
                    </div>
                </div>
                
                <?php if ($dashboard['budget_exceeded']): ?>
                    <div class="alert alert-danger mt-3 mb-0 py-2 px-3">
                        <i class="fas fa-exclamation-circle"></i> <strong>Đã vượt ngân sách <?php echo formatCurrency($dashboard['budget_overflow']); ?></strong>
                    </div>
                <?php else: ?>
                    <div class="row mt-3 small text-center">
                        <div class="col-6">
                            <span class="text-muted">Còn lại:</span> <strong class="text-success"><?php echo formatCurrency($dashboard['budget_remaining']); ?></strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted">Trung bình/ngày:</span> <strong><?php echo formatCurrency($dashboard['budget_remaining'] / (date('t') - date('d') + 1)); ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
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

<!-- Recent Transactions & Wallets & Top Spending -->
<div class="row mb-4">
    <!-- Giao dịch gần đây -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-history"></i> Giao Dịch Gần Đây</h6>
            </div>
            <div class="card-body p-0">
                <div class="transaction-list">
                    <?php 
                    $trans_count = 0;
                    foreach ($recent_transactions as $trans): 
                        if ($trans_count >= 6) break;
                        $is_income = strtolower($trans['category_type']) == 'thu';
                    ?>
                    <div class="transaction-item d-flex justify-content-between align-items-center p-3 border-bottom">
                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                            <div class="transaction-icon">
                                <span class="badge rounded-circle p-3 <?php echo $is_income ? 'bg-success' : 'bg-danger'; ?>">
                                    <i class="fas fa-<?php echo $is_income ? 'arrow-down' : 'arrow-up'; ?>" style="color: white;"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-bold text-truncate"><?php echo e($trans['category_name']); ?></div>
                                <small class="text-muted"><?php echo formatDate($trans['transaction_date']); ?></small>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold <?php echo $is_income ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($is_income ? '+' : '-') . formatCurrency($trans['amount']); ?>
                            </div>
                        </div>
                    </div>
                    <?php 
                        $trans_count++;
                    endforeach; 
                    ?>
                </div>
            </div>
            <div class="card-footer text-center">
                <a href="<?php echo BASE_URL; ?>transactions.php" class="btn btn-sm btn-outline-primary">
                    Xem Tất Cả <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Ví của tôi & Top Chi Tiêu -->
    <div class="col-lg-6">
        <!-- Ví của tôi -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-wallet"></i> Ví của tôi</h6>
            </div>
            <div class="card-body">
                <?php if (count($wallets) === 0): ?>
                    <p class="text-muted mb-0">Chưa có ví nào. <a href="<?php echo BASE_URL; ?>wallets.php">Tạo ví</a></p>
                <?php else: ?>
                    <div class="wallet-list">
                        <?php 
                        $wallet_total_balance = array_sum(array_column($wallets, 'balance'));
                        foreach ($wallets as $wallet): 
                            $wallet_percentage = $wallet_total_balance > 0 ? ($wallet['balance'] / $wallet_total_balance) * 100 : 0;
                        ?>
                        <div class="wallet-item mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold"><?php echo e($wallet['name']); ?></div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($wallet['balance']); ?></div>
                                    <small class="text-muted"><?php echo round($wallet_percentage, 0); ?>%</small>
                                </div>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $wallet_percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>wallets.php" class="btn btn-sm btn-outline-primary w-100 mt-3">
                        Quản lý ví
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Chi Tiêu Tháng -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-fire"></i> Top Chi Tiêu Tháng <?php echo $month; ?></h6>
            </div>
            <div class="card-body p-0">
                <div class="top-spending-list">
                    <?php 
                    if (count($top_spending_data) > 0):
                        foreach ($top_spending_data as $top): 
                    ?>
                    <div class="spending-item d-flex justify-content-between align-items-center p-3 border-bottom">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo e($top['name']); ?></div>
                            <small class="text-muted"><?php echo $top['count']; ?> giao dịch</small>
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

// Pie Chart Data - Top 5 + Others
let pieData = <?php echo json_encode($pie_data); ?>;
const maxCategories = 5;

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
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
            {
                label: 'Thu',
                data: incomeData,
                backgroundColor: '#45c49f',
                borderRadius: 4
            },
            {
                label: 'Chi',
                data: expenseData,
                backgroundColor: '#ff6b6b',
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
