<?php
date_default_timezone_set('Asia/Kathmandu');

define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', '/gym-management');

define('DB_HOST', 'localhost');
define('DB_NAME', 'ironforge_gym');
define('DB_USER', 'root');
define('DB_PASS', '');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Something went wrong connecting to the database. Please try again shortly.');
}

require_once ROOT_PATH . '/includes/functions.php';