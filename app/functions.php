<?php
require_once __DIR__ . '/Currency.php';

// Utility Functions

/**
 * Format currency - supports multi-currency based on user setting
 */
function formatCurrency($amount, $targetCurrency = null) {
    if ($targetCurrency === null) {
        $targetCurrency = $_SESSION['user_currency'] ?? 'VND';
    }
    
    if ($targetCurrency === 'VND') {
        return number_format($amount, 0, ',', '.') . ' ' . CURRENCY;
    }
    
    // Convert from VND to target currency
    $converted = Currency::convert($amount, 'VND', $targetCurrency);
    return Currency::format($converted, $targetCurrency);
}

/**
 * Format date
 */
function formatDate($date, $format = null) {
    if (!$format) {
        $format = DATE_FORMAT;
    }
    return date($format, strtotime($date));
}

/**
 * Get current user
 */
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth($db = null) {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    
    // Check if user actually exists in database (prevents ghost sessions)
    if ($db) {
        $query = "SELECT id FROM users WHERE id = ? LIMIT 1";
        $result = $db->execute($query, [$_SESSION['user_id']]);
        if (!$result || $result->num_rows === 0) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?error=session_expired');
            exit;
        }
    }
}

/**
 * Get user dashboard data
 */
function getDashboardData($user_id, $db, $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $month = intval($month);
    $year = intval($year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Get total income with parameterized query
    $income_query = "SELECT COALESCE(SUM(t.amount), 0) as total 
                     FROM transactions t
                     JOIN categories c ON t.category_id = c.id
                     WHERE t.user_id = ? 
                     AND LOWER(c.type) = 'thu' 
                     AND t.transaction_date BETWEEN ? AND ?";
    $income_result = $db->execute($income_query, [$user_id, $start_date, $end_date]);
    $income = $income_result->fetch_assoc()['total'] ?? 0;
    
    // Get total expenses
    $expense_query = "SELECT COALESCE(SUM(t.amount), 0) as total 
                      FROM transactions t
                      JOIN categories c ON t.category_id = c.id
                      WHERE t.user_id = ? 
                      AND LOWER(c.type) = 'chi' 
                      AND t.transaction_date BETWEEN ? AND ?";
    $expense_result = $db->execute($expense_query, [$user_id, $start_date, $end_date]);
    $expenses = $expense_result->fetch_assoc()['total'] ?? 0;
    
    // Get budget
    $budget_query = "SELECT limit_amount FROM budgets 
                     WHERE user_id = ? 
                     AND month = ? 
                     AND year = ?";
    $budget_result = $db->execute($budget_query, [$user_id, $month, $year]);
    $budget = $budget_result && $budget_result->num_rows > 0 ? $budget_result->fetch_assoc()['limit_amount'] : 0;
    
    // Calculate income - expenses difference (Chênh lệch Thu Chi)
    $income_expense_diff = $income - $expenses;
    
    // Calculate budget remaining and overflow
    $budget_remaining = $budget - $expenses;
    $budget_exceeded = $expenses > $budget ? true : false;
    $budget_overflow = max(0, $expenses - $budget); // Amount exceeded by
    
    return [
        'income' => floatval($income),
        'expenses' => floatval($expenses),
        'balance' => floatval($income - $expenses),
        'income_expense_diff' => floatval($income_expense_diff),
        'budget' => floatval($budget),
        'budget_remaining' => floatval($budget_remaining),
        'budget_exceeded' => $budget_exceeded,
        'budget_overflow' => floatval($budget_overflow),
        'budget_percentage' => $budget > 0 ? min(100, ($expenses / $budget) * 100) : 0,
        'month' => $month,
        'year' => $year
    ];
}

/**
 * Get default wallet for a user, create one if none exists
 */

/**
 * Get expense comparison between current month and previous month
 */
function getMonthlyExpenseComparison($user_id, $db, $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    $month = intval($month);
    $year = intval($year);

    try {
        $current = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    } catch (Exception $e) {
        $current = new DateTimeImmutable('first day of this month');
    }

    $current_start = $current->format('Y-m-01');
    $current_end = $current->format('Y-m-t');
    $previous = $current->modify('-1 month');
    $previous_start = $previous->format('Y-m-01');
    $previous_end = $previous->format('Y-m-t');

    $query = "SELECT COALESCE(SUM(t.amount), 0) as total
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?
              AND LOWER(c.type) = 'chi'
              AND t.transaction_date BETWEEN ? AND ?";

    $current_result = $db->execute($query, [$user_id, $current_start, $current_end]);
    $previous_result = $db->execute($query, [$user_id, $previous_start, $previous_end]);

    $current_total = floatval($current_result->fetch_assoc()['total'] ?? 0);
    $previous_total = floatval($previous_result->fetch_assoc()['total'] ?? 0);
    $diff = $current_total - $previous_total;
    $percent = 0;
    if ($previous_total != 0) {
        $percent = round(($diff / $previous_total) * 100, 1);
    } elseif ($current_total > 0) {
        $percent = 100.0;
    }

    return [
        'current_total' => $current_total,
        'previous_total' => $previous_total,
        'diff' => $diff,
        'percent' => $percent,
        'current_month_label' => $current->format('m/Y'),
        'previous_month_label' => $previous->format('m/Y')
    ];
}

/**
 * Get recent expense summary for the last N months
 */
function getRecentExpenseSummary($user_id, $db, $months = 6) {
    $months = max(1, intval($months));
    $end = new DateTimeImmutable('last day of this month');
    if ($months === 1) {
        $start = new DateTimeImmutable('first day of this month');
    } else {
        $start = $end->modify(sprintf('first day of -%d month', $months - 1));
    }

    $start_date = $start->format('Y-m-01');
    $end_date = $end->format('Y-m-t');

    $query = "SELECT DATE_FORMAT(t.transaction_date, '%Y-%m') as period,
                     SUM(t.amount) as total
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?
              AND LOWER(c.type) = 'chi'
              AND t.transaction_date BETWEEN ? AND ?
              GROUP BY period
              ORDER BY period ASC";

    $result = $db->execute($query, [$user_id, $start_date, $end_date]);
    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[$row['period']] = floatval($row['total']);
    }

    $labels = [];
    $totals = [];
    $current = $start;
    for ($i = 0; $i < $months; $i++) {
        $period = $current->format('Y-m');
        $labels[] = 'Tháng ' . intval($current->format('m')) . '/' . $current->format('y');
        $totals[] = $values[$period] ?? 0;
        $current = $current->modify('+1 month');
    }

    return [
        'labels' => $labels,
        'totals' => $totals,
    ];
}

/**
 * Get category statistics
 */
function getCategoryStats($user_id, $db, $type = 'Chi', $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $month = intval($month);
    $year = intval($year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $type_filter = strtolower($type) === 'thu' ? 'thu' : 'chi';
    
    $query = "SELECT c.id, c.name, SUM(t.amount) as total, COUNT(t.id) as count
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ? 
              AND LOWER(c.type) = ?
              AND t.transaction_date BETWEEN ? AND ?
              GROUP BY c.id, c.name
              ORDER BY total DESC";
    
    return $db->execute($query, [$user_id, $type_filter, $start_date, $end_date]);
}

/**
 * Get monthly summary
 */
function getMonthlySummary($user_id, $db, $year = null) {
    if (!$year) $year = date('Y');
    
    $year = intval($year);
    
    $query = "SELECT DATE_FORMAT(t.transaction_date, '%m') as month,
                     SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END) as income,
                     SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END) as expenses
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ? 
              AND YEAR(t.transaction_date) = ?
              GROUP BY MONTH(t.transaction_date)
              ORDER BY month ASC";
    
    return $db->execute($query, [$user_id, $year]);
}

/**
 * Check if budget exceeded
 */
function isBudgetExceeded($user_id, $db, $month = null, $year = null) {
    $data = getDashboardData($user_id, $db, $month, $year);
    return $data['budget'] > 0 && $data['expenses'] > $data['budget'];
}

/**
 * Get recent transactions
 */
function getRecentTransactions($user_id, $db, $limit = 10) {
    $limit = intval($limit);
    
    $query = "SELECT t.id, t.amount, t.transaction_date, t.note, c.id as category_id, c.name as category_name, c.type as category_type
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?
              ORDER BY t.transaction_date DESC, t.created_at DESC
              LIMIT ?";
    
    return $db->execute($query, [$user_id, $limit], 'ii');
}

/**
 * Get all transactions with filtering
 */
function getTransactions($user_id, $db, $filters = []) {
    $query = "SELECT t.id, t.amount, t.transaction_date, t.note, t.updated_at, 
                     c.id as category_id, c.name as category_name, c.type as category_type
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?";
    
    $params = [$user_id];
    $types = 'i';
    
    // Type filter
    if (!empty($filters['type']) && in_array(strtolower($filters['type']), ['thu', 'chi'])) {
        $query .= " AND LOWER(c.type) = ?";
        $params[] = strtolower($filters['type']);
        $types .= 's';
    }
    
    // Category filter
    if (!empty($filters['category_id'])) {
        $query .= " AND t.category_id = ?";
        $params[] = intval($filters['category_id']);
        $types .= 'i';
    }

    // Date range filter
    if (!empty($filters['date_from'])) {
        $query .= " AND t.transaction_date >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND t.transaction_date <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    $sort_order = 'DESC';
    if (!empty($filters['sort']) && strtolower($filters['sort']) == 'asc') {
        $sort_order = 'ASC';
    }
    
    $query .= " ORDER BY t.transaction_date $sort_order, t.updated_at $sort_order";
    
    return $db->execute($query, $params, $types);
}

/**
 * Validate amount
 */
function isValidAmount($amount) {
    return is_numeric($amount) && floatval($amount) > 0;
}

/**
 * Escape output for HTML
 */
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Get user categories
 */
function getUserCategories($user_id, $db, $type = null) {
    if (!empty($type) && in_array(strtolower($type), ['thu', 'chi'])) {
        $query = "SELECT id, name, type FROM categories WHERE user_id = ? AND LOWER(type) = ? ORDER BY name";
        return $db->execute($query, [$user_id, strtolower($type)]);
    } else {
        $query = "SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type, name";
        return $db->execute($query, [$user_id]);
    }
}

/**
 * Get budget info
 */
function getBudgetInfo($user_id, $db, $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $month = intval($month);
    $year = intval($year);
    
    $query = "SELECT id, limit_amount FROM budgets WHERE user_id = ? AND month = ? AND year = ?";
    $result = $db->execute($query, [$user_id, $month, $year]);
    
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

/**
 * Generate CSRF token if it does not exist, and return it
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get daily income/expense statistics for a specific month
 */
function getDailyStats($user_id, $db, $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $month = intval($month);
    $year = intval($year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    $days_in_month = intval(date('t', strtotime($start_date)));
    
    $query = "SELECT 
                DAY(t.transaction_date) as day,
                SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END) as income,
                SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END) as expense
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?
              AND t.transaction_date BETWEEN ? AND ?
              GROUP BY DAY(t.transaction_date)
              ORDER BY day ASC";
    
    $result = $db->execute($query, [$user_id, $start_date, $end_date]);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[intval($row['day'])] = [
            'income' => floatval($row['income']),
            'expense' => floatval($row['expense'])
        ];
    }
    
    $labels = [];
    $incomes = [];
    $expenses = [];
    for ($d = 1; $d <= $days_in_month; $d++) {
        $labels[] = sprintf('%02d/%02d', $d, $month);
        $incomes[] = $data[$d]['income'] ?? 0;
        $expenses[] = $data[$d]['expense'] ?? 0;
    }
    
    return [
        'labels' => $labels,
        'incomes' => $incomes,
        'expenses' => $expenses,
        'total_income' => array_sum($incomes),
        'total_expense' => array_sum($expenses),
        'days_in_month' => $days_in_month
    ];
}

/**
 * Get monthly income/expense statistics for a year (processed arrays)
 */
function getMonthlyStatsProcessed($user_id, $db, $year = null) {
    if (!$year) $year = date('Y');
    $year = intval($year);
    
    $result = getMonthlySummary($user_id, $db, $year);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $m = intval($row['month']);
        $data[$m] = [
            'income' => floatval($row['income']),
            'expense' => floatval($row['expenses'])
        ];
    }
    
    $labels = [];
    $incomes = [];
    $expenses = [];
    for ($m = 1; $m <= 12; $m++) {
        $labels[] = 'Tháng ' . $m;
        $incomes[] = $data[$m]['income'] ?? 0;
        $expenses[] = $data[$m]['expense'] ?? 0;
    }
    
    return [
        'labels' => $labels,
        'incomes' => $incomes,
        'expenses' => $expenses,
        'total_income' => array_sum($incomes),
        'total_expense' => array_sum($expenses)
    ];
}

/**
 * Get yearly income/expense statistics across all years
 */
function getYearlyStats($user_id, $db) {
    $query = "SELECT 
                YEAR(t.transaction_date) as year,
                SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END) as income,
                SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END) as expense
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?
              GROUP BY YEAR(t.transaction_date)
              ORDER BY year ASC";
    
    $result = $db->execute($query, [$user_id]);
    
    $labels = [];
    $incomes = [];
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = 'Năm ' . $row['year'];
        $incomes[] = floatval($row['income']);
        $expenses[] = floatval($row['expense']);
    }
    
    if (empty($labels)) {
        $labels[] = 'Năm ' . date('Y');
        $incomes[] = 0;
        $expenses[] = 0;
    }
    
    return [
        'labels' => $labels,
        'incomes' => $incomes,
        'expenses' => $expenses,
        'total_income' => array_sum($incomes),
        'total_expense' => array_sum($expenses)
    ];
}

/**
 * Get transaction count for a date range
 */
function getTransactionCount($user_id, $db, $start_date, $end_date) {
    $query = "SELECT COUNT(*) as count FROM transactions 
              WHERE user_id = ? AND transaction_date BETWEEN ? AND ?";
    $result = $db->execute($query, [$user_id, $start_date, $end_date]);
    return intval($result->fetch_assoc()['count'] ?? 0);
}


/**
 * Get user avatar URL
 */
function getUserAvatar($user_id, $db) {
    // Check session first
    if (isset($_SESSION['user_avatar'])) {
        return $_SESSION['user_avatar'];
    }
    
    $query = "SELECT avatar FROM users WHERE id = ?";
    $result = $db->execute($query, [$user_id]);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['avatar'])) {
            $_SESSION['user_avatar'] = $row['avatar'];
            return $row['avatar'];
        }
    }
    
    return null;
}

/**
 * Get user's selected currency
 */
function getUserCurrencyCode($user_id, $db) {
    return Currency::getUserCurrency($user_id, $db);
}

/**
 * Handle avatar upload
 * Returns: ['success' => bool, 'path' => string, 'error' => string]
 */
function handleAvatarUpload($file, $user_id) {
    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File quá lớn (vượt quá giới hạn server)!',
            UPLOAD_ERR_FORM_SIZE => 'File quá lớn!',
            UPLOAD_ERR_PARTIAL => 'File chỉ được tải lên một phần!',
            UPLOAD_ERR_NO_FILE => 'Không có file nào được chọn!',
        ];
        $error = $errorMessages[$file['error']] ?? 'Lỗi tải file!';
        return ['success' => false, 'error' => $error];
    }
    
    // Check file size
    if ($file['size'] > AVATAR_MAX_SIZE) {
        return ['success' => false, 'error' => 'Ảnh không được vượt quá 2MB!'];
    }
    
    // Check file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, AVATAR_ALLOWED_TYPES)) {
        return ['success' => false, 'error' => 'Chỉ chấp nhận file ảnh JPG, PNG, GIF, WEBP!'];
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $ext = 'jpg';
    }
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    
    // Ensure upload directory exists
    $uploadDir = __DIR__ . '/../' . AVATAR_UPLOAD_DIR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $targetPath = $uploadDir . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true, 
            'path' => AVATAR_UPLOAD_DIR . $filename,
            'error' => ''
        ];
    }
    
    return ['success' => false, 'error' => 'Không thể lưu file!'];
}

/**
 * Delete old avatar file
 */
function deleteAvatar($avatarPath) {
    if (empty($avatarPath)) return;
    
    $fullPath = __DIR__ . '/../' . $avatarPath;
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }
}

