<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;