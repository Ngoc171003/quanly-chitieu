<?php
require_once '../app/config.php';
require_once '../app/Database.php';
require_once '../app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];

$month = intval($_POST['month'] ?? date('m'));
$year = intval($_POST['year'] ?? date('Y'));

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

// Get transactions using parameterized query
$query = "SELECT t.id, t.transaction_date, c.name as category_name, c.type as category_type, t.amount, t.note
          FROM transactions t
          JOIN categories c ON t.category_id = c.id
          WHERE t.user_id = ?
          AND t.transaction_date BETWEEN ? AND ?
          ORDER BY t.transaction_date DESC";

$transactions = $db->execute($query, [$user_id, $start_date, $end_date]);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="chi_tieu_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv"');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($output, ['Ngày', 'Danh Mục', 'Loại', 'Số Tiền', 'Ghi Chú'], ',');

// Data rows
while ($trans = $transactions->fetch_assoc()) {
    fputcsv($output, [
        $trans['transaction_date'],
        $trans['category_name'],
        strtolower($trans['category_type']) == 'thu' ? 'Thu' : 'Chi',
        $trans['amount'],
        $trans['note'] ?? ''
    ], ',');
}

// Summary rows
fputcsv($output, [], ',');
$dashboard = getDashboardData($user_id, $db, $month, $year);
fputcsv($output, ['TỔNG HỢP', '', '', '', ''], ',');
fputcsv($output, ['Tổng Thu', '', '', $dashboard['income'], ''], ',');
fputcsv($output, ['Tổng Chi', '', '', $dashboard['expenses'], ''], ',');
fputcsv($output, ['Số Dư', '', '', $dashboard['balance'], ''], ',');

fclose($output);
exit;
