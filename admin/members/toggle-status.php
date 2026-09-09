<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/members/');
    exit;
}

verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status FROM members WHERE member_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if ($member) {
    $newStatus = $member['status'] === 'active' ? 'inactive' : 'active';
    $update = $pdo->prepare("UPDATE members SET status = ? WHERE member_id = ?");
    $update->execute([$newStatus, $id]);
    $msg = $newStatus === 'active' ? 'activated' : 'deactivated';
} else {
    $msg = 'notfound';
}

header('Location: ' . BASE_URL . '/admin/members/?msg=' . $msg);
exit;