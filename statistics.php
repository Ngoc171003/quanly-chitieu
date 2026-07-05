<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));
$tab = $_GET['tab'] ?? 'month';

// Validate
if ($month < 1 || $month > 12) $month = intval(date('m'));
if ($year < 2020 || $year > 2030) $year = intval(date('Y'));
if (!in_array($tab, ['day', 'month', 'year'])) $tab = 'month';

// Fetch all data
$daily_stats = getDailyStats($user_id, $db, $month, $year);
$monthly_stats = getMonthlyStatsProcessed($user_id, $db, $year);
$yearly_stats = getYearlyStats($user_id, $db);

// Category breakdown for selected month (Chi)
$category_chi_result = getCategoryStats($user_id, $db, 'chi', $month, $year);
$categories_chi = [];
$total_chi = 0;
while ($row = $category_chi_result->fetch_assoc()) {
    $row['total'] = (float) $row['total'];
    $categories_chi[] = $row;
    $total_chi += $row['total'];
}

// Category breakdown for selected month (Thu)
$category_thu_result = getCategoryStats($user_id, $db, 'thu', $month, $year);
$categories_thu = [];
$total_thu = 0;
while ($row = $category_thu_result->fetch_assoc()) {
    $row['total'] = (float) $row['total'];
    $categories_thu[] = $row;
    $total_thu += $row['total'];
}

// Transaction count
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));
$transaction_count = getTransactionCount($user_id, $db, $start_date, $end_date);

// Savings rate
$savings_rate = $total_thu > 0 ? round((($total_thu - $total_chi) / $total_thu) * 100, 1) : 0;

// Average daily expense
$avg_daily = $daily_stats['days_in_month'] > 0 ? $total_chi / $daily_stats['days_in_month'] : 0;

$page_title = 'Thống Kê - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col-lg-5 col-md-12 mb-3 mb-lg-0">
        <h1 class="mb-0">
            <i class="fas fa-chart-bar"></i> Thống Kê Tài Chính
        </h1>
        <p class="text-muted mb-0 mt-1">Phân tích chi tiết thu chi của bạn</p>
    </div>
    <div class="col-lg-7 col-md-12">
        <div class="d-flex stats-filters gap-2 align-items-center justify-content-lg-end flex-wrap">
            <div class="filter-group">
                <select class="form-select" id="monthSelect">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                        Tháng <?php echo $m; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <select class="form-select" id="yearSelect">
                    <?php for ($y = 2020; $y <= 2030; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                        Năm <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button class="btn btn-primary" onclick="applyFilter()">
                <i class="fas fa-filter"></i> Lọc
            </button>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card stat-card--income">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon--income">
                    <i class="fa-solid fa-circle-arrow-up"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 small">Tổng Thu</p>
                    <h5 class="mb-0 fw-bold text-success"><?php echo formatCurrency($total_thu); ?></h5>
                    <small class="text-muted">Tháng <?php echo $month; ?>/<?php echo $year; ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card stat-card--expense">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon--expense">
                    <i class="fa-solid fa-circle-arrow-down"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 small">Tổng Chi</p>
                    <h5 class="mb-0 fw-bold text-danger"><?php echo formatCurrency($total_chi); ?></h5>
                    <small class="text-muted">Tháng <?php echo $month; ?>/<?php echo $year; ?></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card stat-card--balance">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon--balance">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 small">Số dư hiện tại</p>
                    <h5 class="mb-0 fw-bold <?php echo ($total_thu - $total_chi) >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatCurrency($total_thu - $total_chi); ?>
                    </h5>
                    <small class="text-muted">
                        Tiết kiệm: <?php echo $savings_rate; ?>%
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card stat-card--count">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon stat-icon--count">
                    <i class="fas fa-receipt"></i>
                </div>
                <div>
                    <p class="text-muted mb-1 small">Giao Dịch</p>
                    <h5 class="mb-0 fw-bold" style="color: var(--primary-color);"><?php echo $transaction_count; ?></h5>
                    <small class="text-muted">
                        TB: <?php echo formatCurrency($avg_daily); ?>/ngày
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="stats-tab-bar">
            <button class="stats-tab <?php echo $tab === 'day' ? 'active' : ''; ?>" data-tab="day" onclick="switchTab('day')">
                <i class="fas fa-calendar-day"></i>
                <span>Theo Ngày</span>
            </button>
            <button class="stats-tab <?php echo $tab === 'month' ? 'active' : ''; ?>" data-tab="month" onclick="switchTab('month')">
                <i class="fas fa-calendar-alt"></i>
                <span>Theo Tháng</span>
            </button>
            <button class="stats-tab <?php echo $tab === 'year' ? 'active' : ''; ?>" data-tab="year" onclick="switchTab('year')">
                <i class="fas fa-calendar"></i>
                <span>Theo Năm</span>
            </button>
        </div>
    </div>
</div>

<!-- Main Chart -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="card-title mb-0" id="chartTitle">
                <i class="fas fa-chart-line me-2" style="color: var(--primary-color);"></i>
                <span id="chartTitleText">Chi tiêu theo tháng - Năm <?php echo $year; ?></span>
            </h6>
            <div class="chart-legend d-flex gap-3">
                <span class="legend-item legend-item--income">
                    <span class="legend-dot"></span> Thu
                </span>
                <span class="legend-item legend-item--expense">
                    <span class="legend-dot"></span> Chi
                </span>
            </div>
        </div>
        <div class="chart-container" style="position: relative; height: 380px;">
            <canvas id="mainChart"></canvas>
        </div>
    </div>
</div>

<!-- Category Breakdown -->
<div class="row mb-4">
    <!-- Category Doughnut -->
    <div class="col-lg-5 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2" style="color: var(--accent-color);"></i>
                        Phân Bổ Chi Tiêu
                    </h6>
                    <span class="badge bg-danger"><?php echo count($categories_chi); ?> danh mục</span>
                </div>
                <div class="chart-container" style="position: relative; height: 300px;">
                    <canvas id="categoryChart"></canvas>
                </div>
                <?php if (empty($categories_chi)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                    <p class="mb-0">Chưa có dữ liệu chi tiêu</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Table -->
    <div class="col-lg-7 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list-ol me-2" style="color: var(--primary-color);"></i>
                        Chi Tiết Danh Mục — Tháng <?php echo $month; ?>/<?php echo $year; ?>
                    </h6>
                </div>

                <!-- Expense Categories -->
                <?php if (!empty($categories_chi)): ?>
                <div class="mb-3">
                    <small class="text-muted fw-bold text-uppercase">
                        <i class="fas fa-arrow-up text-danger"></i> Chi tiêu
                    </small>
                </div>
                <div class="category-table-wrapper">
                    <table class="table table-stats mb-4">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 25%">Danh mục</th>
                                <th style="width: 10%" class="text-center">Số GD</th>
                                <th style="width: 25%" class="text-end">Tổng tiền</th>
                                <th style="width: 35%">Tỷ lệ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories_chi as $index => $cat): 
                                $percent = $total_chi > 0 ? round(($cat['total'] / $total_chi) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $index + 1; ?></td>
                                <td class="fw-bold"><?php echo e($cat['name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo $cat['count']; ?></span>
                                </td>
                                <td class="text-end text-danger fw-bold">
                                    <?php echo formatCurrency($cat['total']); ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                        <small class="text-muted fw-bold" style="min-width: 42px;"><?php echo $percent; ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3">Tổng cộng</td>
                                <td class="text-end text-danger"><?php echo formatCurrency($total_chi); ?></td>
                                <td><small class="text-muted">100%</small></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Income Categories -->
                <?php if (!empty($categories_thu)): ?>
                <div class="mb-3 mt-2">
                    <small class="text-muted fw-bold text-uppercase">
                        <i class="fas fa-arrow-down text-success"></i> Thu nhập
                    </small>
                </div>
                <div class="category-table-wrapper">
                    <table class="table table-stats">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 25%">Danh mục</th>
                                <th style="width: 10%" class="text-center">Số GD</th>
                                <th style="width: 25%" class="text-end">Tổng tiền</th>
                                <th style="width: 35%">Tỷ lệ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories_thu as $index => $cat): 
                                $percent = $total_thu > 0 ? round(($cat['total'] / $total_thu) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $index + 1; ?></td>
                                <td class="fw-bold"><?php echo e($cat['name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $cat['count']; ?></span>
                                </td>
                                <td class="text-end text-success fw-bold">
                                    <?php echo formatCurrency($cat['total']); ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                        <small class="text-muted fw-bold" style="min-width: 42px;"><?php echo $percent; ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3">Tổng cộng</td>
                                <td class="text-end text-success"><?php echo formatCurrency($total_thu); ?></td>
                                <td><small class="text-muted">100%</small></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (empty($categories_chi) && empty($categories_thu)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                    <p class="mb-0">Chưa có dữ liệu trong tháng <?php echo $month; ?>/<?php echo $year; ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ===== DATA =====
const dailyData = <?php echo json_encode($daily_stats); ?>;
const monthlyData = <?php echo json_encode($monthly_stats); ?>;
const yearlyData = <?php echo json_encode($yearly_stats); ?>;
const currentMonth = <?php echo $month; ?>;
const currentYear = <?php echo $year; ?>;

const categoryData = <?php echo json_encode(array_map(function($c) { return ['label' => $c['name'], 'value' => $c['total']]; }, $categories_chi)); ?>;

const chartColors = [
    '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#ef4444',
    '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#10b981',
    '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6', '#a855f7'
];

const incomeColor = 'rgba(69, 196, 159, 0.85)';
const incomeColorLight = 'rgba(69, 196, 159, 0.15)';
const expenseColor = 'rgba(255, 107, 107, 0.85)';
const expenseColorLight = 'rgba(255, 107, 107, 0.15)';

// ===== CHART INSTANCES =====
let mainChart = null;
let categoryChart = null;

// ===== FORMAT CURRENCY =====
function fmtCurrency(value) {
    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
}

// ===== MAIN CHART =====
function createDailyChart() {
    const ctx = document.getElementById('mainChart').getContext('2d');
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: dailyData.labels,
            datasets: [
                {
                    label: 'Thu',
                    data: dailyData.incomes,
                    borderColor: incomeColor,
                    backgroundColor: incomeColorLight,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: incomeColor,
                    borderWidth: 2.5
                },
                {
                    label: 'Chi',
                    data: dailyData.expenses,
                    borderColor: expenseColor,
                    backgroundColor: expenseColorLight,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: expenseColor,
                    borderWidth: 2.5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                    padding: 12,
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + fmtCurrency(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { 
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 15,
                        font: { size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { size: 11 },
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                            return value;
                        }
                    }
                }
            }
        }
    });
}

function createMonthlyChart() {
    const ctx = document.getElementById('mainChart').getContext('2d');
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyData.labels,
            datasets: [
                {
                    label: 'Thu',
                    data: monthlyData.incomes,
                    backgroundColor: incomeColor,
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.7,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Chi',
                    data: monthlyData.expenses,
                    backgroundColor: expenseColor,
                    borderRadius: 6,
                    borderSkipped: false,
                    barPercentage: 0.7,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                    padding: 12,
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + fmtCurrency(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { size: 11 },
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                            return value;
                        }
                    }
                }
            }
        }
    });
}

function createYearlyChart() {
    const ctx = document.getElementById('mainChart').getContext('2d');
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: yearlyData.labels,
            datasets: [
                {
                    label: 'Thu',
                    data: yearlyData.incomes,
                    backgroundColor: 'rgba(69, 196, 159, 0.8)',
                    borderColor: 'rgba(69, 196, 159, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                },
                {
                    label: 'Chi',
                    data: yearlyData.expenses,
                    backgroundColor: 'rgba(255, 107, 107, 0.8)',
                    borderColor: 'rgba(255, 107, 107, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                    padding: 12,
                    titleFont: { size: 13, weight: '600' },
                    bodyFont: { size: 12 },
                    cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + fmtCurrency(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 12, weight: '600' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        font: { size: 11 },
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                            return value;
                        }
                    }
                }
            }
        }
    });
}

// ===== CATEGORY DOUGHNUT =====
function createCategoryChart() {
    if (categoryData.length === 0) return null;

    const maxCats = 6;
    let displayData = [...categoryData];
    if (displayData.length > maxCats) {
        const top = displayData.slice(0, maxCats);
        const otherTotal = displayData.slice(maxCats).reduce((s, c) => s + c.value, 0);
        top.push({ label: 'Khác', value: otherTotal });
        displayData = top;
    }

    const ctx = document.getElementById('categoryChart').getContext('2d');
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: displayData.map(d => d.label),
            datasets: [{
                data: displayData.map(d => d.value),
                backgroundColor: chartColors.slice(0, displayData.length),
                borderWidth: 3,
                borderColor: '#fff',
                hoverBorderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: { size: 12, weight: '500' }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                            return ctx.label + ': ' + fmtCurrency(ctx.raw) + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

// ===== TAB SWITCHING =====
let currentTab = '<?php echo $tab; ?>';

function switchTab(tab) {
    currentTab = tab;

    // Update tab buttons
    document.querySelectorAll('.stats-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    // Update chart title
    const titleText = document.getElementById('chartTitleText');
    const titles = {
        'day': 'Thu chi theo ngày — Tháng ' + currentMonth + '/' + currentYear,
        'month': 'Thu chi theo tháng — Năm ' + currentYear,
        'year': 'Thu chi theo năm — Tổng quan'
    };
    titleText.textContent = titles[tab];

    // Destroy old chart
    if (mainChart) {
        mainChart.destroy();
        mainChart = null;
    }

    // Create new chart with animation
    const container = document.querySelector('.chart-container');
    container.style.opacity = '0';
    container.style.transform = 'translateY(10px)';

    setTimeout(() => {
        switch (tab) {
            case 'day': mainChart = createDailyChart(); break;
            case 'month': mainChart = createMonthlyChart(); break;
            case 'year': mainChart = createYearlyChart(); break;
        }
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
    }, 200);

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

// ===== FILTER =====
function applyFilter() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = '?month=' + month + '&year=' + year + '&tab=' + currentTab;
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    // Init main chart
    switchTab(currentTab);

    // Init category chart
    categoryChart = createCategoryChart();

    // Animate summary cards
    document.querySelectorAll('.stat-card').forEach((card, i) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + i * 80);
    });
});
</script>

<?php include 'views/footer.php'; ?>
