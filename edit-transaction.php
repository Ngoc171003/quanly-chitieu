<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth($db);

$user_id = $_SESSION['user_id'];
$trans_id = intval($_GET['id'] ?? 0);

$check = "SELECT id FROM transactions WHERE id = ? AND user_id = ?";
$result = $db->execute($check, [$trans_id, $user_id]);

if (!$result || $result->num_rows == 0) {
    header('Location: ' . BASE_URL . 'transactions.php');
    exit;
}

$transaction = $result->fetch_assoc();
$_GET['id'] = $trans_id;

include 'add-transaction.php';
