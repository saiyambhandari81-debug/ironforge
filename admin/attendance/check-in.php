<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/attendance/');
    exit;
}

verifyCsrf();

$memberId = (int) ($_POST['member_id'] ?? 0);

if ($memberId <= 0) {
    header('Location: ' . BASE_URL . '/admin/attendance/?msg=notmember');
    exit;
}

// Re-check eligibility on the server
$eligible = $pdo->prepare("
    SELECT 1 FROM members m
    WHERE m.member_id = ?
      AND m.status = 'active'
      AND EXISTS (
          SELECT 1 FROM memberships ms
          WHERE ms.member_id = m.member_id 
            AND ms.status = 'active' 
            AND ms.expiry_date >= CURDATE()
      )
");
$eligible->execute([$memberId]);

if (!$eligible->fetch()) {
    header('Location: ' . BASE_URL . '/admin/attendance/?msg=notmember');
    exit;
}

try {
    $insert = $pdo->prepare(
        "INSERT INTO attendance (member_id, attendance_date, check_in_time, status) 
         VALUES (?, CURDATE(), CURTIME(), 'present')"
    );
    $insert->execute([$memberId]);
    header('Location: ' . BASE_URL . '/admin/attendance/?msg=checkedin');
} catch (PDOException $e) {
    // Unique constraint violation = already checked in today
    if ($e->getCode() === '23000') {
        header('Location: ' . BASE_URL . '/admin/attendance/?msg=already');
    } else {
        throw $e;
    }
}
exit;