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
    
    // Tổng tài sản và ví
    $total_assets = getTotalAssets($user_id, $db);
    $wallets = getUserWallets($user_id, $db);
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
        'total_assets' => floatval($total_assets),
        'wallets' => $wallets,
        'month' => $month,
        'year' => $year
    ];
}

/**
 * Get all wallets for a user, with calculated balance
 */
function getUserWallets($user_id, $db) {
    $query = "SELECT 
                w.id,
                w.name,
                w.type,
                w.icon,
                w.color,
                w.initial_balance,
                w.is_default,
                w.created_at,
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END), 0) as total_expense
              FROM wallets w
              LEFT JOIN transactions t ON t.wallet_id = w.id
              LEFT JOIN categories c ON t.category_id = c.id
              WHERE w.user_id = ?
              GROUP BY w.id, w.name, w.type, w.icon, w.color, w.initial_balance, w.is_default, w.created_at
              ORDER BY w.is_default DESC, w.created_at ASC";
    
    $result = $db->execute($query, [$user_id]);
    $wallets = [];
    while ($row = $result->fetch_assoc()) {
        $row['balance'] = floatval($row['initial_balance']) + floatval($row['total_income']) - floatval($row['total_expense']);
        $row['total_income'] = floatval($row['total_income']);
        $row['total_expense'] = floatval($row['total_expense']);
        $row['initial_balance'] = floatval($row['initial_balance']);
        $wallets[] = $row;
    }
    return $wallets;
}

function getAllWalletsWithBalance($user_id, $db) {
    return getUserWallets($user_id, $db);
}

/**
 * Get single wallet by ID (must belong to user)
 */
function getWalletById($wallet_id, $user_id, $db) {
    $query = "SELECT 
                w.*,
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END), 0) as total_expense
              FROM wallets w
              LEFT JOIN transactions t ON t.wallet_id = w.id
              LEFT JOIN categories c ON t.category_id = c.id
              WHERE w.id = ? AND w.user_id = ?
              GROUP BY w.id";
    $result = $db->execute($query, [$wallet_id, $user_id]);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['balance'] = floatval($row['initial_balance']) + floatval($row['total_income']) - floatval($row['total_expense']);
        return $row;
    }
    return null;
}

/**
 * Get or create default wallet for user
 */
function getOrCreateDefaultWallet($user_id, $db) {
    $query = "SELECT id FROM wallets WHERE user_id = ? AND is_default = 1 LIMIT 1";
    $result = $db->execute($query, [$user_id]);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    // No wallet yet — create default
    $insert = "INSERT INTO wallets (user_id, name, icon, color, initial_balance, is_default) VALUES (?, 'Tiền mặt', 'money-bill-wave', '#45c49f', 0.00, 1)";
    $db->execute($insert, [$user_id]);
    return $db->getConnection()->insert_id ?? null;
}

/**
 * Get total assets = sum of all wallet balances
 */
function getTotalAssets($user_id, $db) {
    $query = "SELECT 
                COALESCE(SUM(w.initial_balance), 0) +
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END), 0) as total_assets
              FROM wallets w
              LEFT JOIN transactions t ON t.wallet_id = w.id
              LEFT JOIN categories c ON t.category_id = c.id
              WHERE w.user_id = ?";
    $result = $db->execute($query, [$user_id]);
    if ($result && $result->num_rows > 0) {
        return floatval($result->fetch_assoc()['total_assets'] ?? 0);
    }
    return 0;
}

/**
 * Create a new wallet
 */
function createWallet($user_id, $name, $icon, $color, $initial_balance, $db) {
    $query = "INSERT INTO wallets (user_id, name, icon, color, initial_balance, is_default) VALUES (?, ?, ?, ?, ?, 0)";
    return $db->execute($query, [$user_id, $name, $icon, $color, floatval($initial_balance)]);
}

/**
 * Update wallet
 */
function updateWallet($wallet_id, $user_id, $name, $icon, $color, $initial_balance, $db) {
    $query = "UPDATE wallets SET name = ?, icon = ?, color = ?, initial_balance = ? WHERE id = ? AND user_id = ?";
    return $db->execute($query, [$name, $icon, $color, floatval($initial_balance), $wallet_id, $user_id]);
}

/**
 * Delete wallet (only if no transactions)
 */
function deleteWallet($wallet_id, $user_id, $db) {
    // Check it's not the only wallet
    $count_query = "SELECT COUNT(*) as cnt FROM wallets WHERE user_id = ?";
    $count_result = $db->execute($count_query, [$user_id]);
    if ($count_result && $count_result->fetch_assoc()['cnt'] <= 1) {
        return ['success' => false, 'error' => 'Không thể xóa ví duy nhất!'];
    }
    // Check not default
    $check = $db->execute("SELECT is_default FROM wallets WHERE id = ? AND user_id = ?", [$wallet_id, $user_id]);
    if ($check && $check->num_rows > 0) {
        $w = $check->fetch_assoc();
        if ($w['is_default']) {
            return ['success' => false, 'error' => 'Không thể xóa ví mặc định!'];
        }
    }
    // Check no transactions
    $tx_check = $db->execute("SELECT COUNT(*) as cnt FROM transactions WHERE wallet_id = ?", [$wallet_id]);
    if ($tx_check) {
        $cnt = $tx_check->fetch_assoc()['cnt'];
        if ($cnt > 0) {
            return ['success' => false, 'error' => "Ví này còn {$cnt} giao dịch. Hãy chuyển giao dịch sang ví khác trước!"];
        }
    }
    $result = $db->execute("DELETE FROM wallets WHERE id = ? AND user_id = ?", [$wallet_id, $user_id]);
    if ($result !== false) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Lỗi khi xóa ví!'];
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
 * Generate a prompt for the AI advisor
 */
function buildAiAdvisorPrompt($dashboard, $category_stats, $expense_trend, $comparison, $month, $year) {
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('n');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $isPastMonth = false;
    if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
        $isPastMonth = true;
    }

    if ($isPastMonth) {
        $today = $daysInMonth;
        $daysLeft = 0;
        $projected = $dashboard['expenses'];
        $dailyRate = $today > 0 ? $dashboard['expenses'] / $today : 0;
    } else {
        $today       = (int)date('j');
        $daysLeft    = max(0, $daysInMonth - $today);

        // Smart projection: identify one-time fixed costs
        // A category is "one-time" if it has exactly 1 transaction this month
        // OR has 2 transactions but represents >25% of total expenses (e.g. rent)
        $oneTimeCosts = 0;
        $fixedCategoryNames = [];
        if (!empty($category_stats)) {
            foreach ($category_stats as $cat) {
                $pctOfTotal = $dashboard['expenses'] > 0 ? ($cat['total'] / $dashboard['expenses']) * 100 : 0;
                $isOneTime = ($cat['count'] == 1 && $cat['total'] > 200000)
                          || ($cat['count'] <= 2 && $pctOfTotal > 25 && $cat['total'] > 500000);
                if ($isOneTime) {
                    $oneTimeCosts += $cat['total'];
                    $fixedCategoryNames[] = $cat['name'];
                }
            }
        }
        $variableExpenses = max(0, $dashboard['expenses'] - $oneTimeCosts);
        // Daily rate only from variable/recurring expenses
        $dailyRate = $today > 0 ? $variableExpenses / $today : 0;

        // Blended rate for early month (day 1-4): blend with previous month daily rate
        if ($today < 5 && !empty($comparison) && $comparison['previous_total'] > 0) {
            $prevDailyRate = $comparison['previous_total'] / 30;
            $weight = $today / 5;
            $dailyRate = ($dailyRate * $weight) + ($prevDailyRate * (1 - $weight));
        }
        // Projected = fixed costs (counted once) + variable daily rate * full month
        $projected = round($oneTimeCosts + ($dailyRate * $daysInMonth));
    }

    $prompt  = "Bạn là chuyên gia tư vấn tài chính cá nhân. Phân tích dữ liệu tháng {$month}/{$year} dưới đây và trả về JSON hợp lệ (KHÔNG có text nào ngoài JSON).\n\n";

    $prompt .= "=== DỮ LIỆU THÁNG {$month}/{$year} ===\n";
    if ($isPastMonth) {
        $prompt .= "- TRẠNG THÁI THÁNG: Đã kết thúc/hoàn thành.\n";
    }
    $prompt .= "- Tổng thu: "     . number_format($dashboard['income'], 0, ',', '.') . " VNĐ\n";
    $prompt .= "- Tổng chi: "     . number_format($dashboard['expenses'], 0, ',', '.') . " VNĐ\n";
    $prompt .= "- Số dư: "        . number_format($dashboard['income_expense_diff'], 0, ',', '.') . " VNĐ\n";
    $prompt .= "- Ngân sách: "    . number_format($dashboard['budget'], 0, ',', '.') . " VNĐ\n";
    $prompt .= "- Đã dùng: "      . round($dashboard['budget_percentage'] ?? 0) . "% ngân sách\n";
    $prompt .= "- Trạng thái: "   . ($dashboard['budget_exceeded'] ? 'Vượt ' . number_format($dashboard['budget_overflow'] ?? 0, 0, ',', '.') . ' VNĐ' : 'Còn ' . number_format($dashboard['budget_remaining'], 0, ',', '.') . ' VNĐ') . "\n";
    $prompt .= "- Ngày trong tháng: {$today}/{$daysInMonth} (còn {$daysLeft} ngày)\n";
    $prompt .= "- Chi mỗi ngày TB: " . number_format($dailyRate, 0, ',', '.') . " VNĐ\n";
    $prompt .= "- Dự kiến chi cuối tháng (nếu tháng cũ thì dự kiến bằng thực tế): " . number_format($projected, 0, ',', '.') . " VNĐ\n\n";

    if (!empty($comparison)) {
        $prevExpense     = floatval($comparison['previous_total'] ?? 0);
        $prevIncome      = floatval($comparison['prev_income'] ?? 0);
        $expChangePct    = round(floatval($comparison['percent'] ?? 0), 1);
        $incomeChangePct = ($prevIncome > 0 && $dashboard['income'] > 0)
            ? round((($dashboard['income'] - $prevIncome) / $prevIncome) * 100, 1)
            : null;

        $prompt .= "=== SO SÁNH VỚI THÁNG TRƯỚC ===\n";
        if ($incomeChangePct !== null) {
            $prompt .= "- Thu nhập: " . ($incomeChangePct >= 0 ? "+{$incomeChangePct}" : "{$incomeChangePct}") . "% so với tháng trước\n";
        }
        $prompt .= "- Chi tiêu: " . ($expChangePct >= 0 ? "+{$expChangePct}" : "{$expChangePct}") . "% so với tháng trước\n";
        $prompt .= "- Chi tháng trước: " . number_format($prevExpense, 0, ',', '.') . " VNĐ\n\n";
    }

    if (!empty($category_stats)) {
        $prompt .= "=== CHI TIÊU THEO DANH MỤC ===\n";
        $totalExp = $dashboard['expenses'];
        foreach ($category_stats as $cat) {
            $pct = $totalExp > 0 ? round(($cat['total'] / $totalExp) * 100, 1) : 0;
            $prompt .= "- {$cat['name']}: " . number_format($cat['total'], 0, ',', '.') . " VNĐ ({$pct}%, {$cat['count']} GD)\n";
        }
        $prompt .= "\n";
    }

    if (!empty($expense_trend['labels'])) {
        $prompt .= "=== XU HƯỚNG CHI 6 THÁNG ===\n";
        foreach ($expense_trend['labels'] as $idx => $label) {
            $prompt .= "- {$label}: " . number_format($expense_trend['totals'][$idx] ?? 0, 0, ',', '.') . " VNĐ\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "=== YÊU CẦU ===\n";
    $prompt .= "Trả về JSON (không có text khác ngoài JSON):\n\n";
    $prompt .= "{\n";
    $prompt .= '  "score": <số nguyên 0-100>,' . "\n";
    $prompt .= '  "status": "Tài chính tốt" | "Tài chính ổn định" | "Cần cải thiện" | "Rất cần chú ý",' . "\n";
    $prompt .= '  "summary": {' . "\n";
    $prompt .= '    "income_amount": "<tổng số tiền thu nhập của tháng hiện tại, VD: 7.600.000 VNĐ>",' . "\n";
    $prompt .= '    "expense_amount": "<tổng số tiền chi tiêu của tháng hiện tại, VD: 2.340.000 VNĐ>",' . "\n";
    $prompt .= '    "income_change": "<thu nhập tăng/giảm % so với tháng trước, hoặc N/A>",' . "\n";
    $prompt .= '    "expense_change": "<chi tiêu tăng/giảm % so với tháng trước>",' . "\n";
    $prompt .= '    "balance": "<số dư hiện tại bằng VNĐ>",' . "\n";
    $prompt .= '    "note": "<1-2 câu tóm tắt tổng quan>"' . "\n";
    $prompt .= '  },' . "\n";
    $prompt .= '  "spending_trend": {' . "\n";
    $prompt .= '    "top_category": "<danh mục chiếm tỷ trọng lớn nhất>",' . "\n";
    $prompt .= '    "top_category_percent": "<tỷ lệ %>",' . "\n";
    $prompt .= '    "rising_categories": ["<danh mục đang tăng, VD: Giải trí tăng 30%>"],' . "\n";
    $prompt .= '    "note": "<1-2 câu nhận xét xu hướng>"' . "\n";
    $prompt .= '  },' . "\n";
    $prompt .= '  "anomalies": {' . "\n";
    $prompt .= '    "found": <true/false>,' . "\n";
    $prompt .= '    "items": ["<mô tả bất thường cụ thể nếu có>"]' . "\n";
    $prompt .= '  },' . "\n";
    $prompt .= '  "prediction": {' . "\n";
    $prompt .= '    "projected_expense": "<tổng chi dự kiến cuối tháng, chỉ số không có dấu phẩy>",' . "\n";
    $prompt .= '    "risk": "Thấp" | "Trung bình" | "Cao",' . "\n";
    $prompt .= '    "note": "<1-2 câu về nguy cơ vượt ngân sách>"' . "\n";
    $prompt .= '  },' . "\n";
    $prompt .= '  "recommendations": ["<gợi ý 1 cụ thể>", "<gợi ý 2>", "<gợi ý 3>"],' . "\n";
    $prompt .= '  "warning": "<cảnh báo quan trọng nhất nếu có, để trống nếu không>"' . "\n";
    $prompt .= "}\n";

    return $prompt;
}

/**
 * Generate local fallback advice when AI key is not configured or remote API fails.
 */
function getLocalAiFinancialAdvice($dashboard, $category_stats, $expense_trend, $month, $year, $comparison = []) {
    $income   = floatval($dashboard['income']);
    $expenses = floatval($dashboard['expenses']);
    $budget   = floatval($dashboard['budget']);
    $diff     = $income - $expenses;

    // --- Score calculation ---
    $score = 75;
    if ($income > 0) {
        $savings_rate = ($diff / $income) * 100;
        $score += $savings_rate < 0 ? -min(30, abs($savings_rate) * 0.5) : min(15, $savings_rate * 0.3);
    } elseif ($expenses > 0) {
        $score -= 20;
    }
    if ($budget > 0) {
        $bu = ($expenses / $budget) * 100;
        if ($bu > 100)      { $score -= min(30, ($bu - 100) * 0.6 + 10); }
        elseif ($bu > 80)   { $score -= 10; }
        elseif ($bu < 50 && $expenses > 0) { $score += 5; }
    }
    $score = max(10, min(100, round($score)));

    // Status
    if ($score >= 85)      { $status = 'Tài chính tốt'; }
    elseif ($score >= 65)  { $status = 'Tài chính ổn định'; }
    elseif ($score >= 45)  { $status = 'Cần cải thiện'; }
    else                   { $status = 'Rất cần chú ý'; }

    // --- 1. SUMMARY ---
    $prevExpense     = floatval($comparison['previous_total'] ?? 0);
    $prevIncome      = floatval($comparison['prev_income'] ?? 0);
    $expChangePct    = floatval($comparison['percent'] ?? 0);
    $incomeChangeStr = 'N/A';
    if ($prevIncome > 0 && $income > 0) {
        $icp = round((($income - $prevIncome) / $prevIncome) * 100, 1);
        $incomeChangeStr = ($icp >= 0 ? '+' : '') . $icp . '%';
    }
    $expChangeStr = ($expChangePct >= 0 ? '+' : '') . round($expChangePct, 1) . '%';
    $summaryNote = 'Chi tiêu tháng ' . $month . ' là ' . number_format($expenses, 0, ',', '.') . ' VNĐ'
        . ($income > 0 ? ', chiếm ' . round(($expenses / $income) * 100) . '% thu nhập.' : '.');
    $summary = [
        'income_amount'  => number_format($income, 0, ',', '.') . ' VNĐ',
        'expense_amount' => number_format($expenses, 0, ',', '.') . ' VNĐ',
        'income_change'  => $incomeChangeStr,
        'expense_change' => $expChangeStr,
        'balance'        => number_format($diff, 0, ',', '.') . ' VNĐ',
        'note'           => $summaryNote,
    ];

    // --- 2. SPENDING TREND ---
    $topCatName = 'Chưa có dữ liệu';
    $topCatPct  = '—';
    if (!empty($category_stats)) {
        $top = $category_stats[0];
        $topCatName = $top['name'];
        $topCatPct  = $expenses > 0 ? round(($top['total'] / $expenses) * 100) . '%' : '—';
    }
    $trendNote = $topCatName !== 'Chưa có dữ liệu'
        ? "Danh mục {$topCatName} chiếm {$topCatPct} tổng chi tiêu tháng {$month}. Theo dõi sát để kiểm soát hiệu quả."
        : 'Chưa có đủ dữ liệu để phân tích xu hướng.';
    $spending_trend = [
        'top_category'         => $topCatName,
        'top_category_percent' => $topCatPct,
        'rising_categories'    => [],
        'note'                 => $trendNote,
    ];

    // --- 3. ANOMALIES ---
    $anomalyFound  = false;
    $anomalyItems  = [];
    if ($dashboard['budget_exceeded']) {
        $anomalyFound = true;
        $anomalyItems[] = 'Chi tiêu vượt ngân sách ' . number_format($dashboard['budget_overflow'] ?? 0, 0, ',', '.') . ' VNĐ.';
    }
    if (abs($expChangePct) > 50) {
        $anomalyFound = true;
        $dir = $expChangePct > 0 ? 'tăng' : 'giảm';
        $anomalyItems[] = 'Chi tiêu ' . $dir . ' bất thường ' . abs(round($expChangePct)) . '% so với tháng trước.';
    }
    if (!$anomalyFound && !empty($category_stats) && $expenses > 0) {
        $topPct = ($category_stats[0]['total'] / $expenses) * 100;
        if ($topPct > 60) {
            $anomalyFound = true;
            $anomalyItems[] = 'Danh mục "' . $category_stats[0]['name'] . '" chiếm tới ' . round($topPct) . '% tổng chi — cao bất thường.';
        }
    }
    $anomalies = ['found' => $anomalyFound, 'items' => $anomalyItems];

    // --- 4. PREDICTION ---
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('n');
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $isPastMonth = false;
    if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
        $isPastMonth = true;
    }

    if ($isPastMonth) {
        $projected = $expenses;
        $risk = $budget > 0 && $expenses > $budget ? 'Cao' : 'Thấp';
        if ($budget > 0) {
            $exceeded = $expenses > $budget;
            if ($exceeded) {
                $predNote = 'Tháng đã kết thúc. Tổng chi tiêu thực tế đã vượt ngân sách ' . number_format($expenses - $budget, 0, ',', '.') . ' VNĐ.';
            } else {
                $predNote = 'Tháng đã kết thúc. Tổng chi tiêu thực tế đã được kiểm soát an toàn trong ngân sách.';
            }
        } else {
            $predNote = 'Tháng đã kết thúc. Tổng chi tiêu thực tế là ' . number_format($expenses, 0, ',', '.') . ' VNĐ (chưa thiết lập ngân sách).';
        }
    } else {
        $today = (int)date('j');
        
        // Smart prediction: separate fixed one-time costs from variable daily spending
        // One-time: single transaction in month, or <=2 transactions that represent >25% of expenses
        $oneTimeCosts = 0;
        $fixedNames = [];
        if (!empty($category_stats)) {
            foreach ($category_stats as $cat) {
                $pctOfTotal = $expenses > 0 ? ($cat['total'] / $expenses) * 100 : 0;
                $isOneTime = ($cat['count'] == 1 && $cat['total'] > 200000)
                          || ($cat['count'] <= 2 && $pctOfTotal > 25 && $cat['total'] > 500000);
                if ($isOneTime) {
                    $oneTimeCosts += $cat['total'];
                    $fixedNames[] = $cat['name'];
                }
            }
        }
        $variableExpenses = max(0, $expenses - $oneTimeCosts);
        // Daily rate = only variable/recurring spending
        $dailyRate = $today > 0 ? $variableExpenses / $today : 0;

        // Blended rate for early month
        if ($today < 5 && !empty($comparison) && $comparison['previous_total'] > 0) {
            $prevDailyRate = $comparison['previous_total'] / 30;
            $weight = $today / 5;
            $dailyRate = ($dailyRate * $weight) + ($prevDailyRate * (1 - $weight));
        }
        // Projected = fixed (once) + variable rate extrapolated for whole month
        $projected = round($oneTimeCosts + ($dailyRate * $daysInMonth));

        $risk = 'Thấp';
        $predNote = '';
        if ($budget > 0) {
            $pp = ($projected / $budget) * 100;
            if ($pp >= 100) {
                $risk = 'Cao';
                $predNote = 'Nếu tiếp tục tốc độ hiện tại, cuối tháng ước chi ' . number_format($projected, 0, ',', '.') . ' VNĐ, vượt ngân sách ' . number_format($projected - $budget, 0, ',', '.') . ' VNĐ.';
            } elseif ($pp >= 80) {
                $risk = 'Trung bình';
                $predNote = 'Ước chi cuối tháng ' . number_format($projected, 0, ',', '.') . ' VNĐ (' . round($pp) . '% ngân sách). Cần theo dõi sát.';
            } else {
                $predNote = 'Ước chi cuối tháng ' . number_format($projected, 0, ',', '.') . ' VNĐ (' . round($pp) . '% ngân sách). Tình hình tốt.';
            }
        } else {
            $predNote = 'Chưa thiết lập ngân sách. Ước chi cuối tháng khoảng ' . number_format($projected, 0, ',', '.') . ' VNĐ.';
        }
    }

    $prediction = [
        'projected_expense' => (string)$projected,
        'risk'              => $risk,
        'note'              => $predNote,
    ];

    // --- 5. RECOMMENDATIONS ---
    $recommendations = [];
    if (!empty($category_stats)) {
        $top = $category_stats[0];
        $recommendations[] = 'Giảm chi danh mục "' . $top['name'] . '" (hiện ' . number_format($top['total'], 0, ',', '.') . ' VNĐ).';
    }
    if ($income > 0 && ($diff / $income) < 0.2) {
        $recommendations[] = 'Tăng tỷ lệ tiết kiệm lên ít nhất 20% thu nhập mỗi tháng.';
    }
    if ($budget == 0) {
        $recommendations[] = 'Thiết lập ngân sách tháng để kiểm soát chi tiêu hiệu quả.';
    } elseif ($dashboard['budget_exceeded']) {
        $recommendations[] = 'Cắt giảm chi tiêu ngay để không vượt ngân sách thêm.';
    }
    $recommendations[] = 'Lập kế hoạch chi tiêu hàng tuần và ghi chép đầy đủ giao dịch.';
    if (count($recommendations) < 3) {
        $recommendations[] = 'Theo dõi xu hướng chi tiêu hàng tháng để phát hiện sớm bất thường.';
    }

    // Warning
    $warning = '';
    if ($dashboard['budget_exceeded']) {
        $warning = 'Đã vượt ngân sách ' . number_format($dashboard['budget_overflow'] ?? 0, 0, ',', '.') . ' VNĐ. Cần cắt giảm chi tiêu ngay.';
    } elseif ($budget > 0 && $dashboard['budget_remaining'] < ($budget * 0.1)) {
        $warning = 'Ngân sách còn lại rất ít (' . number_format($dashboard['budget_remaining'], 0, ',', '.') . ' VNĐ). Cần cẩn trọng.';
    } elseif ($risk === 'Cao') {
        $warning = 'Dự kiến vượt ngân sách cuối tháng nếu tiếp tục tốc độ chi hiện tại.';
    }

    return [
        'score'          => $score,
        'status'         => $status,
        'summary'        => $summary,
        'spending_trend' => $spending_trend,
        'anomalies'      => $anomalies,
        'prediction'     => $prediction,
        'recommendations'=> $recommendations,
        'warning'        => $warning,
        'version'        => 'v1.3',
        // Legacy compat
        'analysis'       => $summaryNote,
        'suggestions'    => $recommendations,
    ];
}

/**
 * Parse AI response - try JSON first, then fallback to text
 */
function parseAiResponse($response) {
    if (empty($response)) {
        return null;
    }

    // Strip markdown code fences if present
    $response = preg_replace('/```(?:json)?\s*/i', '', $response);
    $response = str_replace('```', '', $response);

    // Extract JSON object
    $jsonStart = strpos($response, '{');
    $jsonEnd   = strrpos($response, '}');
    if ($jsonStart === false || $jsonEnd === false) {
        return null;
    }

    $jsonStr = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
    $parsed  = json_decode($jsonStr, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($parsed)) {
        return null;
    }

    // Must have at least score + status
    if (!isset($parsed['score']) || !isset($parsed['status'])) {
        return null;
    }

    // Ensure all 5 sections exist with safe defaults
    if (!isset($parsed['summary']) || !is_array($parsed['summary'])) {
        $parsed['summary'] = ['income_change' => 'N/A', 'expense_change' => 'N/A', 'balance' => 'N/A', 'note' => ''];
    }
    if (!isset($parsed['spending_trend']) || !is_array($parsed['spending_trend'])) {
        $parsed['spending_trend'] = ['top_category' => '', 'top_category_percent' => '', 'rising_categories' => [], 'note' => ''];
    }
    if (!isset($parsed['anomalies']) || !is_array($parsed['anomalies'])) {
        $parsed['anomalies'] = ['found' => false, 'items' => []];
    }
    if (!isset($parsed['prediction']) || !is_array($parsed['prediction'])) {
        $parsed['prediction'] = ['projected_expense' => '', 'risk' => 'Thấp', 'note' => ''];
    }
    if (!isset($parsed['recommendations']) || !is_array($parsed['recommendations'])) {
        $parsed['recommendations'] = [];
    }
    if (!isset($parsed['warning'])) { $parsed['warning'] = ''; }

    // Legacy compat fields
    if (!isset($parsed['analysis'])) {
        $parsed['analysis'] = $parsed['summary']['note'] ?? '';
    }
    if (!isset($parsed['suggestions'])) {
        $parsed['suggestions'] = $parsed['recommendations'] ?? [];
    }

    $parsed['version'] = 'v1.2';
    return $parsed;
}

/**
 * Call remote AI API if configuration is present.
 */
function fetchAiAdvisorFromApi($prompt) {
    $apiKey = trim(AI_API_KEY);
    if (empty($apiKey) || !function_exists('curl_init')) {
        return false;
    }

    // Check for dummy/placeholder keys
    $apiKeyLower = strtolower($apiKey);
    $placeholders = ['placeholder', 'your_api_key', 'your-api-key', 'your_key', 'gemini_api_key', 'api_key_here', 'sk-yourkey', 'key_here', 'xxxx'];
    foreach ($placeholders as $placeholder) {
        if (strpos($apiKeyLower, $placeholder) !== false) {
            return false;
        }
    }
    if (strlen($apiKey) < 15 || preg_match('/^[xX]+$/', $apiKey)) {
        return false;
    }

    $payload = json_encode([
        'model' => AI_API_MODEL,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];

    $ch = curl_init(AI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Shorten timeouts for faster fallback if API is slow or unreachable
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return false;
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        return false;
    }

    $data = json_decode($response, true);
    $content = '';
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = trim($data['choices'][0]['message']['content']);
    } elseif (isset($data['choices'][0]['text'])) {
        $content = trim($data['choices'][0]['text']);
    }

    if (!empty($content)) {
        $parsed = parseAiResponse($content);
        return $parsed ? $parsed : false;
    }

    return false;
}

/**
 * Generate a financial state hash for a user to determine if cache needs invalidation.
 */
function getUserFinancialStateHash($user_id, $db, $month, $year) {
    // Get count and sum of transactions for this month/year
    $query = "SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total, COALESCE(MAX(id), 0) as max_id 
              FROM transactions 
              WHERE user_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
    $res = $db->execute($query, [$user_id, $month, $year]);
    $row = $res ? $res->fetch_assoc() : ['cnt' => 0, 'total' => 0, 'max_id' => 0];
    
    // Get budget
    $budget_query = "SELECT budget_amount FROM budgets WHERE user_id = ? AND month = ? AND year = ? LIMIT 1";
    $b_res = $db->execute($budget_query, [$user_id, $month, $year]);
    $budget = 0;
    if ($b_res && $b_res->num_rows > 0) {
        $budget = floatval($b_res->fetch_assoc()['budget_amount'] ?? 0);
    }
    
    // Get wallets information
    $wallet_query = "SELECT COUNT(*) as cnt, COALESCE(SUM(initial_balance), 0) as bal FROM wallets WHERE user_id = ?";
    $w_res = $db->execute($wallet_query, [$user_id]);
    $w_row = $w_res ? $w_res->fetch_assoc() : ['cnt' => 0, 'bal' => 0];
    
    return md5(($row['cnt'] ?? 0) . '_' . ($row['total'] ?? 0) . '_' . ($row['max_id'] ?? 0) . '_' . $budget . '_' . ($w_row['cnt'] ?? 0) . '_' . ($w_row['bal'] ?? 0));
}

/**
 * Get AI financial advice for the user.
 */
function getAiFinancialAdvice($user_id, $db, $month = null, $year = null) {
    if (!AI_ADVISOR_ENABLED) {
        return 'AI advisor is disabled.';
    }

    $month = $month ?: date('m');
    $year  = $year  ?: date('Y');

    $dashboard      = getDashboardData($user_id, $db, $month, $year);
    $comparison     = getMonthlyExpenseComparison($user_id, $db, $month, $year);
    $category_stats = [];
    $category_result = getCategoryStats($user_id, $db, 'Chi', $month, $year);
    while ($row = $category_result->fetch_assoc()) {
        $category_stats[] = $row;
    }

    $expense_trend = getRecentExpenseSummary($user_id, $db, 6);
    $prompt = buildAiAdvisorPrompt($dashboard, $category_stats, $expense_trend, $comparison, $month, $year);

    $remote = fetchAiAdvisorFromApi($prompt);
    if ($remote) {
        return $remote;
    }

    return getLocalAiFinancialAdvice($dashboard, $category_stats, $expense_trend, $month, $year, $comparison);
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
 * Get recent income and expense summary for the last N months
 */
function getRecentIncomeExpenseSummary($user_id, $db, $months = 6) {
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
                     SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END) as income,
                     SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END) as expense
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ?
              AND t.transaction_date BETWEEN ? AND ?
              GROUP BY period
              ORDER BY period ASC";

    $result = $db->execute($query, [$user_id, $start_date, $end_date]);
    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[$row['period']] = [
            'income' => floatval($row['income']),
            'expense' => floatval($row['expense'])
        ];
    }

    $labels = [];
    $income_totals = [];
    $expense_totals = [];
    $current = $start;
    for ($i = 0; $i < $months; $i++) {
        $period = $current->format('Y-m');
        $labels[] = 'Tháng ' . intval($current->format('m')) . '/' . $current->format('y');
        $income_totals[] = $values[$period]['income'] ?? 0;
        $expense_totals[] = $values[$period]['expense'] ?? 0;
        $current = $current->modify('+1 month');
    }

    return [
        'labels' => $labels,
        'income' => $income_totals,
        'expense' => $expense_totals,
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
                     w.id as wallet_id, w.name as wallet_name, w.type as wallet_type
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
                     w.id as wallet_id, w.name as wallet_name, w.type as wallet_type, w.icon as wallet_icon
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
 * Compare metrics with previous month
 */
function getMetricsComparison($user_id, $db, $month = null, $year = null) {
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

    // Get current and previous month data
    $query = "SELECT 
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END), 0) as expense
              FROM transactions t
              JOIN categories c ON t.category_id = c.id
              WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?";

    $current_result = $db->execute($query, [$user_id, $current_start, $current_end]);
    $previous_result = $db->execute($query, [$user_id, $previous_start, $previous_end]);

    $current_data = $current_result->fetch_assoc();
    $previous_data = $previous_result->fetch_assoc();

    $current_income = floatval($current_data['income'] ?? 0);
    $current_expense = floatval($current_data['expense'] ?? 0);
    $previous_income = floatval($previous_data['income'] ?? 0);
    $previous_expense = floatval($previous_data['expense'] ?? 0);

    // Calculate percentages
    $income_percent = 0;
    if ($previous_income != 0) {
        $income_percent = round((($current_income - $previous_income) / $previous_income) * 100, 1);
    } elseif ($current_income > 0) {
        $income_percent = 100.0;
    }

    $expense_percent = 0;
    if ($previous_expense != 0) {
        $expense_percent = round((($current_expense - $previous_expense) / $previous_expense) * 100, 1);
    } elseif ($current_expense > 0) {
        $expense_percent = 100.0;
    }

    // Get total assets for current and previous month (cumulative from beginning)
    $assets_query = "SELECT 
                        COALESCE(SUM(w.initial_balance), 0) +
                        COALESCE(SUM(CASE WHEN LOWER(c.type) = 'thu' THEN t.amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN LOWER(c.type) = 'chi' THEN t.amount ELSE 0 END), 0) as total_assets
                     FROM wallets w
                     LEFT JOIN transactions t ON t.wallet_id = w.id AND t.transaction_date <= ?
                     LEFT JOIN categories c ON t.category_id = c.id
                     WHERE w.user_id = ?";

    $current_assets_result = $db->execute($assets_query, [$current_end, $user_id]);
    $previous_assets_result = $db->execute($assets_query, [$previous_end, $user_id]);

    $current_assets = floatval($current_assets_result->fetch_assoc()['total_assets'] ?? 0);
    $previous_assets = floatval($previous_assets_result->fetch_assoc()['total_assets'] ?? 0);

    $assets_percent = 0;
    if ($previous_assets != 0) {
        $assets_percent = round((($current_assets - $previous_assets) / abs($previous_assets)) * 100, 1);
    } elseif ($current_assets > 0) {
        $assets_percent = 100.0;
    }

    return [
        'income_percent' => $income_percent,
        'expense_percent' => $expense_percent,
        'assets_percent' => $assets_percent
    ];
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

/**
 * Get savings goals for user — current_amount is calculated live from linked transactions
 */
function getSavingsGoals($user_id, $db, $status = null) {
    $statusFilter = $status ? "AND sg.status = '" . $db->getConnection()->real_escape_string($status) . "'" : '';
    $query = "SELECT sg.*,
                     COALESCE((
                         SELECT SUM(t.amount)
                         FROM transactions t
                         WHERE t.savings_goal_id = sg.id AND t.user_id = sg.user_id
                     ), sg.current_amount) AS current_amount_live
              FROM savings_goals sg
              WHERE sg.user_id = ? $statusFilter
              ORDER BY sg.status ASC, sg.target_date ASC";
    $res = $db->execute($query, [$user_id]);
    return $res;
}

/**
 * Get single savings goal by ID — current_amount_live is SUM of linked transactions
 */
function getSavingsGoalById($goal_id, $user_id, $db) {
    $query = "SELECT sg.*,
                     COALESCE((
                         SELECT SUM(t.amount)
                         FROM transactions t
                         WHERE t.savings_goal_id = sg.id AND t.user_id = sg.user_id
                     ), sg.current_amount) AS current_amount_live
              FROM savings_goals sg
              WHERE sg.id = ? AND sg.user_id = ? LIMIT 1";
    $res = $db->execute($query, [$goal_id, $user_id]);
    return ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
}

/**
 * Recalculate and sync current_amount for a savings goal from its linked transactions.
 * Call this after any transaction edit or delete.
 */
function recalculateSavingsGoalAmount($goal_id, $user_id, $db) {
    if (!$goal_id) return;
    $sum_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE savings_goal_id = ? AND user_id = ?";
    $sum_res = $db->execute($sum_query, [$goal_id, $user_id]);
    if (!$sum_res) return;
    $total = floatval($sum_res->fetch_assoc()['total']);

    $goal_res = $db->execute("SELECT target_amount FROM savings_goals WHERE id = ? AND user_id = ?", [$goal_id, $user_id]);
    if (!$goal_res || $goal_res->num_rows == 0) return;
    $target = floatval($goal_res->fetch_assoc()['target_amount']);
    $status = ($total >= $target && $total > 0) ? 'completed' : 'active';

    $db->execute("UPDATE savings_goals SET current_amount = ?, status = ? WHERE id = ? AND user_id = ?",
                 [$total, $status, $goal_id, $user_id]);
}

/**
 * Create a new savings goal
 */
function createSavingsGoal($user_id, $name, $target_amount, $target_date, $db) {
    $query = "INSERT INTO savings_goals (user_id, name, target_amount, target_date, status) VALUES (?, ?, ?, ?, 'active')";
    return $db->execute($query, [$user_id, $name, floatval($target_amount), $target_date]);
}

/**
 * Update savings goal
 */
function updateSavingsGoal($goal_id, $user_id, $name, $target_amount, $target_date, $db) {
    $goal = getSavingsGoalById($goal_id, $user_id, $db);
    if (!$goal) return false;
    
    $target_amount = floatval($target_amount);
    $current_amount = floatval($goal['current_amount']);
    $status = ($current_amount >= $target_amount) ? 'completed' : 'active';
    
    $query = "UPDATE savings_goals SET name = ?, target_amount = ?, target_date = ?, status = ? WHERE id = ? AND user_id = ?";
    return $db->execute($query, [$name, $target_amount, $target_date, $status, $goal_id, $user_id]);
}

/**
 * Delete savings goal
 */
function deleteSavingsGoal($goal_id, $user_id, $db) {
    $query = "DELETE FROM savings_goals WHERE id = ? AND user_id = ?";
    return $db->execute($query, [$goal_id, $user_id]);
}

/**
 * Contribute to savings goal
 */
function contributeToSavingsGoal($goal_id, $user_id, $amount, $wallet_id = null, $db) {
    $goal = getSavingsGoalById($goal_id, $user_id, $db);
    if (!$goal) return false;

    $amount = floatval($amount);
    if ($amount <= 0) return false;

    // Always create a transaction so the amount is tracked via linked transactions
    // Find or create 'Tiết kiệm' category (type 'Chi')
    $cat_query = "SELECT id FROM categories WHERE user_id = ? AND name = 'Tiết kiệm' AND type = 'Chi' LIMIT 1";
    $cat_res = $db->execute($cat_query, [$user_id]);
    if ($cat_res && $cat_res->num_rows > 0) {
        $category_id = $cat_res->fetch_assoc()['id'];
    } else {
        $cat_insert = "INSERT INTO categories (user_id, name, type) VALUES (?, 'Tiết kiệm', 'Chi')";
        $db->execute($cat_insert, [$user_id]);
        $category_id = $db->getConnection()->insert_id;
    }

    // Determine wallet: use provided wallet_id or a dummy placeholder (0 means no wallet deduction)
    $use_wallet_id = ($wallet_id && $wallet_id > 0) ? $wallet_id : null;

    if ($use_wallet_id) {
        // Create transaction linked to the savings goal AND the wallet (deducts balance)
        $trans_insert = "INSERT INTO transactions (user_id, category_id, wallet_id, savings_goal_id, amount, transaction_date, note)
                         VALUES (?, ?, ?, ?, ?, CURDATE(), ?)";
        $note = "Đóng góp vào mục tiêu: " . $goal['name'];
        $db->execute($trans_insert, [$user_id, $category_id, $use_wallet_id, $goal_id, $amount, $note]);
    } else {
        // No wallet deduction — still record the contribution as a savings-linked record
        // Use a special category-only transaction with no wallet (wallet_id required NOT NULL, use default)
        $def_wallet_res = $db->execute("SELECT id FROM wallets WHERE user_id = ? AND is_default = 1 LIMIT 1", [$user_id]);
        $def_wallet_id = ($def_wallet_res && $def_wallet_res->num_rows > 0)
                          ? intval($def_wallet_res->fetch_assoc()['id'])
                          : null;

        if ($def_wallet_id) {
            // Insert with savings_goal_id but mark as savings-only (amount goes to goal tracking only)
            // We create a separate "Tiết kiệm" income on same wallet to keep balance neutral
            $note = "Đóng góp vào mục tiêu: " . $goal['name'] . " (không khấu trừ ví)";

            // Find/create a Tiết kiệm Thu category for the credit side
            $cat_thu_res = $db->execute("SELECT id FROM categories WHERE user_id = ? AND name = 'Tiết kiệm' AND type = 'Chi' LIMIT 1", [$user_id]);
            $cat_thu_id = ($cat_thu_res && $cat_thu_res->num_rows > 0) ? intval($cat_thu_res->fetch_assoc()['id']) : $category_id;

            $trans_insert = "INSERT INTO transactions (user_id, category_id, wallet_id, savings_goal_id, amount, transaction_date, note)
                             VALUES (?, ?, ?, ?, ?, CURDATE(), ?)";
            $db->execute($trans_insert, [$user_id, $cat_thu_id, $def_wallet_id, $goal_id, $amount, $note]);
        }
    }

    // Recalculate current_amount from all linked transactions
    recalculateSavingsGoalAmount($goal_id, $user_id, $db);

    // Check completion
    $updated_goal = getSavingsGoalById($goal_id, $user_id, $db);
    $live = floatval($updated_goal['current_amount_live'] ?? $updated_goal['current_amount']);
    if ($goal['status'] == 'active' && $live >= floatval($goal['target_amount'])) {
        $_SESSION['savings_completed_goal'] = $goal['name'];
    }

    return true;
}


