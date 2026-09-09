<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/plans/');
    exit;
}

verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status FROM plans WHERE plan_id = ?");
$stmt->execute([$id]);
$plan = $stmt->fetch();

if ($plan) {
    $newStatus = $plan['status'] === 'active' ? 'inactive' : 'active';
    $update = $pdo->prepare("UPDATE plans SET status = ? WHERE plan_id = ?");
    $update->execute([$newStatus, $id]);
    $msg = $newStatus === 'active' ? 'activated' : 'deactivated';
} else {
    $msg = 'notfound';
}

header('Location: ' . BASE_URL . '/admin/plans/?msg=' . $msg);
exit;