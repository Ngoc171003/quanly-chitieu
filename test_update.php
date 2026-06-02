<?php
require_once 'app/config.php';
require_once 'app/Database.php';

try {
    $db = new Database();
    $db->connect();
    echo "Wallet feature has been removed from the application.\n";
} catch (Exception $e) {
    echo "Error connecting to database: " . $e->getMessage();
}
