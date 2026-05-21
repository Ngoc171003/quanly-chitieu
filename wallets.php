<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$wallet_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$wallet = null;

if ($wallet_id) {
    $wallet = getWalletById($wallet_id, $user_id, $db);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $balance = floatval($_POST['balance'] ?? 0);

    if (empty($name)) {
        $error = 'Vui lòng nhập tên ví!';
    } else {
        if ($wallet_id && $wallet) {
            $update = "UPDATE wallets SET name = ?, balance = ? WHERE id = ? AND user_id = ?";
            $result = $db->execute($update, [$name, $balance, $wallet_id, $user_id]);
            if ($result !== false && $db->affectedRows() >= 0) {
                $success = 'Ví đã được cập nhật!';
                header('Refresh: 1; URL=' . BASE_URL . 'wallets.php');
            } else {
                $error = 'Có lỗi khi cập nhật ví!';
            }
        } else {
            $insert = "INSERT INTO wallets (user_id, name, balance) VALUES (?, ?, ?)";
            $result = $db->execute($insert, [$user_id, $name, $balance]);
            if ($result !== false) {
                $success = 'Ví đã được tạo!';
                header('Refresh: 1; URL=' . BASE_URL . 'wallets.php');
            } else {
                $error = 'Có lỗi khi tạo ví!';
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && $wallet_id) {
    $checkTransactions = "SELECT COUNT(*) as total FROM transactions WHERE wallet_id = ? AND user_id = ?";
    $checkResult = $db->execute($checkTransactions, [$wallet_id, $user_id]);
    $total = $checkResult ? intval($checkResult->fetch_assoc()['total']) : 0;
    if ($total > 0) {
        $error = 'Không thể xóa ví đang có giao dịch. Vui lòng di chuyển hoặc xóa giao dịch trước.';
    } else {
        $delete = "DELETE FROM wallets WHERE id = ? AND user_id = ?";
        $deleteResult = $db->execute($delete, [$wallet_id, $user_id]);
        if ($deleteResult !== false) {
            $success = 'Ví đã được xóa!';
            header('Refresh: 1; URL=' . BASE_URL . 'wallets.php');
            exit;
        } else {
            $error = 'Có lỗi khi xóa ví!';
        }
    }
}

$wallets = getUserWallets($user_id, $db);
$page_title = 'Ví - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-wallet"></i> Quản lý Ví</h5>
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

                <form method="POST" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Tên Ví <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo $wallet ? e($wallet['name']) : ''; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Số Dư Ban Đầu</label>
                        <div class="input-group">
                            <input type="number" min="0" step="0.01" name="balance" class="form-control" value="<?php echo $wallet ? e($wallet['balance']) : '0'; ?>">
                            <span class="input-group-text"><?php echo CURRENCY; ?></span>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $wallet ? 'Cập nhật ví' : 'Tạo ví mới'; ?>
                        </button>
                        <?php if ($wallet): ?>
                        <a href="<?php echo BASE_URL; ?>wallets.php" class="btn btn-secondary">Hủy</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-list"></i> Danh sách Ví</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tên Ví</th>
                                <th class="text-end">Số Dư</th>
                                <th class="text-center">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($wallets->num_rows == 0): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">Chưa có ví nào. Hãy tạo ví để bắt đầu.</td>
                            </tr>
                            <?php else: ?>
                                <?php while ($item = $wallets->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo e($item['name']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($item['balance']); ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo BASE_URL; ?>wallets.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                         <a href="<?php echo BASE_URL; ?>wallets.php?action=delete&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn chắc chắn muốn xóa ví này chứ?');" title="Xóa">
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
    </div>
</div>

<?php include 'views/footer.php'; ?>
