<?php
// Utility Functions

/**
 * Format currency to VND
 */
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' ' . CURRENCY;
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
    
    // Get total wallet balance
    $wallet_query = "SELECT COALESCE(SUM(balance), 0) as wallet_total FROM wallets WHERE user_id = ?";
    $wallet_result = $db->execute($wallet_query, [$user_id]);
    $wallet_total = $wallet_result && $wallet_result->num_rows > 0 ? $wallet_result->fetch_assoc()['wallet_total'] : 0;
    
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
        'wallet_total' => floatval($wallet_total),
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
    
    $query = "SELECT t.id, t.amount, t.transaction_date, t.note, c.id as category_id, c.name as category_name, c.type as category_type,
                     w.id as wallet_id, w.name as wallet_name
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              JOIN wallets w ON t.wallet_id = w.id
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
                     c.id as category_id, c.name as category_name, c.type as category_type,
                     w.id as wallet_id, w.name as wallet_name
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              JOIN wallets w ON t.wallet_id = w.id
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
    
    // Wallet filter
    if (!empty($filters['wallet_id'])) {
        $query .= " AND t.wallet_id = ?";
        $params[] = intval($filters['wallet_id']);
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
    
    $query .= " ORDER BY t.transaction_date DESC, t.updated_at DESC";
    
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
 * Get user wallets
 */
function getUserWallets($user_id, $db) {
    $query = "SELECT id, name, type, balance FROM wallets WHERE user_id = ? ORDER BY name";
    return $db->execute($query, [$user_id]);
}

/**
 * Get wallet by id
 */
function getWalletById($wallet_id, $user_id, $db) {
    $query = "SELECT id, name, type, balance FROM wallets WHERE id = ? AND user_id = ? LIMIT 1";
    $result = $db->execute($query, [$wallet_id, $user_id]);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
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

