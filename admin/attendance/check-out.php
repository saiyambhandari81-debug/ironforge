<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/attendance/');
    exit;
}

verifyCsrf();

$attendanceId = (int) ($_POST['attendance_id'] ?? 0);

$update = $pdo->prepare(
    "UPDATE attendance 
     SET check_out_time = CURTIME()
     WHERE attendance_id = ? 
       AND attendance_date = CURDATE() 
       AND check_out_time IS NULL"
);
$update->execute([$attendanceId]);

header('Location: ' . BASE_URL . '/admin/attendance/?msg=checkedout');
exit;