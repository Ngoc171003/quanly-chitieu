<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$filter_type = $_GET['type'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        header('Location: ' . BASE_URL . 'transactions.php?error=csrf');
        exit;
    } else {
        $trans_id = intval($_GET['id']);
        $check = "SELECT t.id, t.amount, c.type FROM transactions t
                  JOIN categories c ON t.category_id = c.id
                  WHERE t.id = ? AND t.user_id = ?";
        $check_result = $db->execute($check, [$trans_id, $user_id]);
        if ($check_result && $check_result->num_rows > 0) {
            $data = $check_result->fetch_assoc();
            $delete_query = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
            $db->execute($delete_query, [$trans_id, $user_id]);
            header('Location: ' . BASE_URL . 'transactions.php?deleted=1');
            exit;
        }
    }
}

// Build filters array for getTransactions function
$filters = [
    'type' => !empty($filter_type) ? strtolower($filter_type) : '',
    'category_id' => !empty($filter_category) ? intval($filter_category) : '',
    'date_from' => !empty($filter_date_from) ? $filter_date_from : '',
    'date_to' => !empty($filter_date_to) ? $filter_date_to : ''
];

// Get transactions using parameterized query function
$transactions = getTransactions($user_id, $db, $filters);

// Get categories for filter dropdown
$categories = getUserCategories($user_id, $db);

$page_title = 'Giao Dịch - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="mb-4">
    <h1><i class="fas fa-exchange-alt"></i> Giao Dịch</h1>
</div>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Loại</label>
                <select name="type" class="form-select">
                            <option value="">Tất Cả</option>
                    <option value="thu" <?php echo strtolower($filter_type) == 'thu' ? 'selected' : ''; ?>>Thu</option>
                    <option value="chi" <?php echo strtolower($filter_type) == 'chi' ? 'selected' : ''; ?>>Chi</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Danh Mục</label>
                <select name="category" class="form-select">
                    <option value="">Tất Cả</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo e($cat['name']); ?> (<?php echo strtolower($cat['type']) == 'thu' ? 'Thu' : 'Chi'; ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Từ Ngày</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo e($filter_date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Đến Ngày</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo e($filter_date_to); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Lọc
                </button>
                <a href="<?php echo BASE_URL; ?>transactions.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Add Transaction Button -->
<div class="mb-3">
    <a href="<?php echo BASE_URL; ?>add-transaction.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Thêm Giao Dịch
    </a>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> Giao dịch đã được xóa!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] == 'csrf'): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle"></i> Yêu cầu không hợp lệ (CSRF Token invalid)!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Transactions Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ngày</th>
                        <th>Danh Mục</th>
                        <th>Ghi Chú</th>
                        <th class="text-end">Số Tiền</th>
                        <th class="text-center" style="width: 120px">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows == 0): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox" style="font-size: 2rem;"></i>
                            <p class="mt-2">Không có giao dịch nào</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php while ($trans = $transactions->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo formatDate($trans['transaction_date']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo strtolower($trans['category_type']) == 'thu' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo e($trans['category_name']); ?>
                                </span>
                            </td>
                            <td><?php echo e($trans['note'] ?? '—'); ?></td>
                            <td class="text-end">
                                <strong class="<?php echo strtolower($trans['category_type']) == 'thu' ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo (strtolower($trans['category_type']) == 'thu' ? '+' : '-') . formatCurrency($trans['amount']); ?>
                                </strong>
                            </td>
                            <td class="text-center">
                                <a href="<?php echo BASE_URL; ?>edit-transaction.php?id=<?php echo $trans['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>transactions.php?action=delete&id=<?php echo $trans['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" 
                                   class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn chắc chắn muốn xóa giao dịch này chứ?');" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
