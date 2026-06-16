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
$category_stats_result = getCategoryStats($user_id, $db, 'chi', $month, $year);
$expense_comparison = getMonthlyExpenseComparison($user_id, $db, $month, $year);
$expense_trend = getRecentExpenseSummary($user_id, $db, 6);
$is_budget_exceeded = isBudgetExceeded($user_id, $db, $month, $year);

// Store transactions in array for multiple use
$recent_transactions = [];
while ($row = $recent_transactions_result->fetch_assoc()) {
    $recent_transactions[] = $row;
}

// Process category stats for charts and tables
$category_stats = [];
$total_category_spending = 0;
while ($row = $category_stats_result->fetch_assoc()) {
    $row['total'] = (float) $row['total'];
    $category_stats[] = $row;
    $total_category_spending += $row['total'];
}

$pie_data = [];
foreach ($category_stats as $row) {
    $pie_data[] = ['label' => $row['name'], 'value' => $row['total']];
}

$top_spending_data = array_slice($category_stats, 0, 5);

$months_labels = $expense_trend['labels'];
$monthly_expense = $expense_trend['totals'];

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
        <a href="<?php echo BASE_URL; ?>export.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-primary export-btn" style="white-space: nowrap;">
            <i class="fas fa-file-excel"></i> Xuất Excel
        </a>
    </div>
</div>

<!-- Key Metrics - Visual Hierarchy -->
<div class="row mb-4 justify-content-center">
    <!-- Secondary Metrics -->
    <div class="col-lg-4 col-md-6 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-arrow-up text-success"></i> Tổng Thu</p>
                <h4 class="text-success"><?php echo formatCurrency($dashboard['income']); ?></h4>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-arrow-down text-danger"></i> Tổng Chi</p>
                <h4 class="text-danger"><?php echo formatCurrency($dashboard['expenses']); ?></h4>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6 col-sm-6 mb-3">
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

<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-calendar-alt"></i> Tổng Chi Tháng Trước</p>
                <h4 class="text-danger mb-0"><?php echo formatCurrency($expense_comparison['previous_total']); ?></h4>
                <small class="text-muted">Tháng <?php echo $expense_comparison['previous_month_label']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-calendar-check"></i> Tổng Chi Tháng Hiện Tại</p>
                <h4 class="text-danger mb-0"><?php echo formatCurrency($expense_comparison['current_total']); ?></h4>
                <small class="text-muted">Tháng <?php echo $expense_comparison['current_month_label']; ?></small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-arrow-right"></i> Chênh Lệch</p>
                <h4 class="<?php echo $expense_comparison['diff'] >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                    <?php echo formatCurrency($expense_comparison['diff']); ?>
                </h4>
                <small class="text-muted">So với tháng trước</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted mb-2"><i class="fas fa-percentage"></i> Tỷ lệ tăng/giảm</p>
                <h4 class="mb-0 <?php echo $expense_comparison['percent'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo ($expense_comparison['percent'] >= 0 ? '+' : '') . $expense_comparison['percent']; ?>%
                </h4>
                <small class="text-muted">So với tháng trước</small>
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
                <h6 class="card-title mb-4">Chi tiêu 6 tháng gần nhất</h6>
                <canvas id="barChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions & Top Spending -->
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
    
    <!-- Top Chi Tiêu Tháng -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
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
const expenseData = <?php echo json_encode($monthly_expense); ?>;

const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
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
