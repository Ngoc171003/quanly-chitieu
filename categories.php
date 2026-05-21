<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $cat_id = intval($_GET['id'] ?? 0);
    // Check if category has no transactions
    $check = "SELECT id FROM transactions WHERE category_id = ? LIMIT 1";
    $check_result = $db->execute($check, [$cat_id]);
    if ($check_result && $check_result->num_rows == 0) {
        $delete = "DELETE FROM categories WHERE id = ? AND user_id = ?";
        $db->execute($delete, [$cat_id, $user_id]);
        $success = 'Danh mục đã được xóa!';
    } else {
        $error = 'Không thể xóa danh mục đang có giao dịch!';
    }
}

$edit_cat = null;
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
if ($edit_id) {
    $edit_query = "SELECT id, name, type FROM categories WHERE id = ? AND user_id = ? LIMIT 1";
    $edit_result = $db->execute($edit_query, [$edit_id, $user_id]);
    if ($edit_result && $edit_result->num_rows > 0) {
        $edit_cat = $edit_result->fetch_assoc();
    }
}

// Handle add/edit category
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $cat_id = intval($_POST['category_id'] ?? 0);
    
    if (empty($name)) {
        $error = 'Tên danh mục không được trống!';
    } elseif (!$cat_id && !in_array(strtolower($type), ['thu', 'chi'])) {
        $error = 'Loại danh mục không hợp lệ!';
    } else {
        if ($cat_id) {
            // Update - name only, not type
            $update = "UPDATE categories SET name = ? WHERE id = ? AND user_id = ?";
            $update_result = $db->execute($update, [$name, $cat_id, $user_id]);
            if ($update_result !== false) {
                $success = 'Danh mục đã được cập nhật!';
                header('Refresh: 1; URL=' . BASE_URL . 'categories.php');
            } else {
                $error = 'Có lỗi khi cập nhật!';
            }
        } else {
            // Insert
            $insert = "INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)";
            $insert_result = $db->execute($insert, [$user_id, $name, $type]);
            if ($insert_result !== false) {
                $success = 'Danh mục đã được thêm!';
                header('Refresh: 1; URL=' . BASE_URL . 'categories.php');
            } else {
                $error = 'Danh mục này đã tồn tại!';
            }
        }
    }
}

// Get categories using parameterized query
$categories = getUserCategories($user_id, $db);

$page_title = 'Danh Mục - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-list"></i> Quản Lý Danh Mục</h1>
    </div>
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
                <h6 class="mb-0"><i class="fas fa-plus"></i> <?php echo $edit_cat ? 'Sửa Danh Mục' : 'Thêm Danh Mục'; ?></h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit_cat): ?>
                        <input type="hidden" name="category_id" value="<?php echo $edit_cat['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Tên Danh Mục <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="VD: Ăn uống" value="<?php echo $edit_cat ? e($edit_cat['name']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loại <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" <?php echo $edit_cat ? 'disabled' : 'required'; ?>>
                            <option value="">Chọn loại</option>
                            <option value="Thu" <?php echo ($edit_cat && strtolower($edit_cat['type']) == 'thu') ? 'selected' : ''; ?>>Thu</option>
                            <option value="Chi" <?php echo ($edit_cat && strtolower($edit_cat['type']) == 'chi') ? 'selected' : ''; ?>>Chi</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> <?php echo $edit_cat ? 'Cập nhật' : 'Thêm'; ?>
                    </button>
                    <?php if ($edit_cat): ?>
                        <a href="categories.php" class="btn btn-secondary w-100 mt-2">Hủy</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="fas fa-list"></i> Danh Sách Danh Mục</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tên</th>
                                <th>Loại</th>
                                <th class="text-center" style="width: 120px">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo e($cat['name']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo strtolower($cat['type']) == 'thu' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo strtolower($cat['type']) == 'thu' ? 'Thu' : 'Chi'; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo BASE_URL; ?>categories.php?edit_id=<?php echo $cat['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary me-1" title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                     <a href="<?php echo BASE_URL; ?>categories.php?action=delete&id=<?php echo $cat['id']; ?>" 
                                        class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn chắc chắn muốn xóa danh mục này chứ?');" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
