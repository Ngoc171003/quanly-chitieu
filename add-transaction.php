<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$trans_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$transaction = null;
if ($trans_id) {
    $check = "SELECT id, category_id, wallet_id, amount, transaction_date, note FROM transactions WHERE id = ? AND user_id = ?";
    $result = $db->execute($check, [$trans_id, $user_id]);
    if ($result && $result->num_rows > 0) {
        $transaction = $result->fetch_assoc();
    }
}

$selected_type = strtolower($_GET['type'] ?? '');
$wallets = getUserWallets($user_id, $db);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $wallet_id = intval($_POST['wallet_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    // Validate inputs
    if ($category_id <= 0) {
        $error = 'Vui lòng chọn danh mục!';
    } elseif ($wallet_id <= 0) {
        $error = 'Vui lòng chọn ví!';
    } elseif (!isValidAmount($amount)) {
        $error = 'Số tiền phải lớn hơn 0!';
    } elseif (empty($transaction_date)) {
        $error = 'Vui lòng chọn ngày giao dịch!';
    } else {
        // Check category exists and belongs to user
        $cat_query = "SELECT id, type FROM categories WHERE id = ? AND user_id = ?";
        $cat_result = $db->execute($cat_query, [$category_id, $user_id]);
        
        // Check wallet exists and belongs to user
        $wallet_query = "SELECT id FROM wallets WHERE id = ? AND user_id = ?";
        $wallet_result = $db->execute($wallet_query, [$wallet_id, $user_id]);
        
        if (!$cat_result || $cat_result->num_rows == 0) {
            $error = 'Danh mục không hợp lệ!';
        } elseif (!$wallet_result || $wallet_result->num_rows == 0) {
            $error = 'Ví không hợp lệ!';
        } else {
            $category = $cat_result->fetch_assoc();
            $category_type = strtolower($category['type']);
            $wallet_effect = $category_type === 'thu' ? $amount : -$amount;
            
            if ($trans_id && $transaction) {
                $old_query = "SELECT t.amount, t.wallet_id, c.type FROM transactions t
                              JOIN categories c ON t.category_id = c.id
                              WHERE t.id = ? AND t.user_id = ?";
                $old_result = $db->execute($old_query, [$trans_id, $user_id]);
                $old_data = $old_result ? $old_result->fetch_assoc() : null;
                
                if (!$old_data) {
                    $error = 'Giao dịch không tồn tại!';
                } else {
                    $old_amount = floatval($old_data['amount']);
                    $old_wallet_id = intval($old_data['wallet_id']);
                    $old_type = strtolower($old_data['type']);
                    $old_effect = $old_type === 'thu' ? -$old_amount : $old_amount;
                    
                    if ($old_wallet_id === $wallet_id) {
                        $balance_delta = $old_effect + $wallet_effect;
                        $db->execute("UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?", [$balance_delta, $wallet_id, $user_id]);
                    } else {
                        $db->execute("UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?", [$old_effect, $old_wallet_id, $user_id]);
                        $db->execute("UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?", [$wallet_effect, $wallet_id, $user_id]);
                    }
                    
                    $update = "UPDATE transactions SET category_id = ?, wallet_id = ?, amount = ?, note = ?, transaction_date = ?
                              WHERE id = ? AND user_id = ?";
                    $update_result = $db->execute($update, [$category_id, $wallet_id, $amount, $note, $transaction_date, $trans_id, $user_id]);
                    
                    if ($update_result !== false) {
                        $success = 'Giao dịch đã được cập nhật!';
                        header('Refresh: 1; URL=' . BASE_URL . 'transactions.php');
                    } else {
                        $error = 'Có lỗi khi cập nhật!';
                    }
                }
            } else {
                $insert = "INSERT INTO transactions (user_id, category_id, wallet_id, amount, transaction_date, note)
                          VALUES (?, ?, ?, ?, ?, ?)";
                $insert_result = $db->execute($insert, [$user_id, $category_id, $wallet_id, $amount, $transaction_date, $note]);
                
                if ($insert_result !== false) {
                    $db->execute("UPDATE wallets SET balance = balance + ? WHERE id = ? AND user_id = ?", [$wallet_effect, $wallet_id, $user_id]);
                    $success = 'Giao dịch đã được thêm!';
                    header('Refresh: 1; URL=' . BASE_URL . 'transactions.php');
                } else {
                    $error = 'Có lỗi khi thêm giao dịch!';
                }
            }
        }
    }
}

// Get categories with proper filtering
$categories = getUserCategories($user_id, $db, $selected_type);

$page_title = ($trans_id ? 'Sửa' : 'Thêm') . ' Giao Dịch - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle"></i> 
                    <?php echo $trans_id ? 'Sửa Giao Dịch' : 'Thêm Giao Dịch'; ?>
                </h5>
            </div>
            <div class="card-body">
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

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Danh Mục <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Chọn danh mục</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($transaction && $transaction['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?> (<?php echo $cat['type']; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ví <span class="text-danger">*</span></label>
                        <select name="wallet_id" class="form-select" required>
                            <option value="">Chọn ví</option>
                            <?php if ($wallets->num_rows == 0): ?>
                                <option value="">(Chưa có ví) - Vui lòng tạo ví trước</option>
                            <?php else: ?>
                                <?php while ($wallet = $wallets->fetch_assoc()): ?>
                                <option value="<?php echo $wallet['id']; ?>" 
                                        <?php echo ($transaction && $transaction['wallet_id'] == $wallet['id']) ? 'selected' : ''; ?> >
                                    <?php echo e($wallet['name']); ?> (<?php echo formatCurrency($wallet['balance']); ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <?php if ($wallets->num_rows == 0): ?>
                        <div class="form-text text-danger">Bạn cần tạo ít nhất một ví trước khi thêm giao dịch.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Số Tiền <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="amount" class="form-control amount-input" placeholder="0" 
                                   value="<?php echo $transaction ? (int)$transaction['amount'] : ''; ?>" required>
                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ngày Giao Dịch <span class="text-danger">*</span></label>
                        <input type="date" name="transaction_date" class="form-control" 
                               value="<?php echo $transaction ? $transaction['transaction_date'] : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ghi Chú</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Nhập ghi chú..."><?php echo $transaction ? e($transaction['note']) : ''; ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Lưu
                        </button>
                        <a href="<?php echo BASE_URL; ?>transactions.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Hủy
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
