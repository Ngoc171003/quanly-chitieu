<?php
require_once '../app/config.php';
require_once '../app/Database.php';
require_once '../app/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Phương thức không hợp lệ.']);
    exit;
}

// Support JSON payloads and form data
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    parse_str($requestBody, $data);
}

$user_id = $_SESSION['user_id'];
$month = intval($data['month'] ?? $_POST['month'] ?? $_SESSION['dashboard_month'] ?? date('m'));
$year = intval($data['year'] ?? $_POST['year'] ?? $_SESSION['dashboard_year'] ?? date('Y'));
$force = isset($data['force']) ? (bool)$data['force'] : false;

// Generate current state hash
$state_hash = getUserFinancialStateHash($user_id, $db, $month, $year);

// Check cache (and ensure it contains the current structure format version)
if (!$force && isset($_SESSION['ai_cache'][$month][$year])
    && $_SESSION['ai_cache'][$month][$year]['hash'] === $state_hash
    && isset($_SESSION['ai_cache'][$month][$year]['advice']['version'])
    && $_SESSION['ai_cache'][$month][$year]['advice']['version'] === 'v2.2') {
    $advice = $_SESSION['ai_cache'][$month][$year]['advice'];
    echo json_encode(['success' => true, 'advice' => $advice, 'cached' => true]);
    exit;
}

// Fetch advice from AI
$advice = getAiFinancialAdvice($user_id, $db, $month, $year);

if ($advice && (is_array($advice) || is_string($advice))) {
    $advice_array = is_array($advice) ? $advice : [
        'status' => 'Phân tích',
        'score' => 75,
        'analysis' => $advice,
        'warning' => '',
        'suggestions' => []
    ];
    
    // Save to cache
    $_SESSION['ai_cache'][$month][$year] = [
        'hash' => $state_hash,
        'advice' => $advice_array
    ];
    
    echo json_encode(['success' => true, 'advice' => $advice_array, 'cached' => false]);
    exit;
}

http_response_code(500);
echo json_encode(['success' => false, 'error' => 'Không thể tạo tư vấn tài chính.']);
exit;
