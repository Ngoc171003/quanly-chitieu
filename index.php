<?php
// Redirect to dashboard or login based on authentication
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

if (isAuthenticated()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit;
