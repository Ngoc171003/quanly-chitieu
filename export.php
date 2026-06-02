<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth();

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2024 || $year > 2027) {
    $year = date('Y');
}

$page_title = 'Xuất Dữ Liệu - ' . APP_NAME;
?>

<?php include 'views/header.php'; ?>

<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-download"></i> Xuất Dữ Liệu</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-csv"></i> Xuất File CSV</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Xuất dữ liệu giao dịch của bạn ra file CSV để sử dụng trong Excel hoặc các ứng dụng khác.</p>
                
                <form method="POST" action="<?php echo BASE_URL; ?>api/export.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-calendar"></i> Tháng <span class="text-danger">*</span></label>
                            <select name="month" class="form-select" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                    Tháng <?php echo $m; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-calendar-alt"></i> Năm <span class="text-danger">*</span></label>
                            <select name="year" class="form-select" required>
                                <?php for ($y = 2024; $y <= 2027; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    Năm <?php echo $y; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-4" role="alert">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Thông tin:</strong> File CSV sẽ bao gồm tất cả giao dịch, tổng hợp thu chi và số dư của tháng bạn chọn.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-download"></i> Tải File CSV
                    </button>
                </form>

                <hr class="my-4">

                <h6 class="mb-3"><i class="fas fa-lightbulb"></i> Hướng dẫn sử dụng:</h6>
                <ul class="small text-muted">
                    <li>Chọn tháng và năm muốn xuất dữ liệu</li>
                    <li>Nhấn nút "Tải File CSV" để tải về máy tính</li>
                    <li>Mở file bằng Excel, Google Sheets hoặc bất kỳ ứng dụng bảng tính nào</li>
                    <li>File sẽ chứa danh sách giao dịch và tóm tắt tài chính của tháng</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'views/footer.php'; ?>
