<?php
require_once '../app/config.php';
require_once '../app/Database.php';
require_once '../app/functions.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

requireAuth($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    die("Yêu cầu không hợp lệ (CSRF Token invalid)!");
}

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
          ORDER BY t.transaction_date ASC";

$transactions = $db->execute($query, [$user_id, $start_date, $end_date]);

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Chi Tiêu');

// Header row
$sheet->setCellValue('A1', 'Ngày');
$sheet->setCellValue('B1', 'Danh Mục');
$sheet->setCellValue('C1', 'Loại');
$sheet->setCellValue('D1', 'Số Tiền');
$sheet->setCellValue('E1', 'Ghi Chú');

// Make headers bold
$sheet->getStyle('A1:E1')->getFont()->setBold(true);

$row = 2;
// Data rows
while ($trans = $transactions->fetch_assoc()) {
    $date_formatted = date('d/m/Y', strtotime($trans['transaction_date']));
    $sheet->setCellValue('A' . $row, $date_formatted);
    $sheet->setCellValue('B' . $row, $trans['category_name']);
    $sheet->setCellValue('C' . $row, strtolower($trans['category_type']) == 'thu' ? 'Thu' : 'Chi');
    $sheet->setCellValue('D' . $row, number_format($trans['amount'], 0, '.', '.') . ' ' . CURRENCY);
    $sheet->setCellValue('E' . $row, $trans['note'] ?? '');
    $row++;
}

// Summary rows
$row++; // Empty row
$dashboard = getDashboardData($user_id, $db, $month, $year);

$sheet->setCellValue('A' . $row, 'TỔNG HỢP');
$sheet->getStyle('A' . $row)->getFont()->setBold(true);
$row++;

$sheet->setCellValue('A' . $row, 'Tổng Thu');
$sheet->setCellValue('D' . $row, number_format($dashboard['income'], 0, '.', '.') . ' ' . CURRENCY);
$row++;

$sheet->setCellValue('A' . $row, 'Tổng Chi');
$sheet->setCellValue('D' . $row, number_format($dashboard['expenses'], 0, '.', '.') . ' ' . CURRENCY);
$row++;

$sheet->setCellValue('A' . $row, 'Số Dư');
$sheet->setCellValue('D' . $row, number_format($dashboard['balance'] ?? ($dashboard['income'] - $dashboard['expenses']), 0, '.', '.') . ' ' . CURRENCY);
$sheet->getStyle('A' . ($row-2) . ':A' . $row)->getFont()->setBold(true);

// Set column auto-size
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set headers for Excel download
$filename = 'chi_tieu_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
