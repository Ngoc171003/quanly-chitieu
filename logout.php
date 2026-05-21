<?php
require_once 'app/config.php';
require_once 'app/Database.php';
require_once 'app/functions.php';

requireAuth();

session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit;
