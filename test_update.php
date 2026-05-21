<?php
require_once 'app/config.php';
require_once 'app/Database.php';

$user_id = 1;
$wallet_id = 1;
$name = 'Ví Tiền Mặt Edit Test';
$balance = 50000.50;

$update = "UPDATE wallets SET name = ?, balance = ? WHERE id = ? AND user_id = ?";
$result = $db->execute($update, [$name, $balance, $wallet_id, $user_id]);

if ($result !== false) {
    echo "Success! Affected rows: " . $db->affectedRows();
} else {
    echo "Error!";
}
