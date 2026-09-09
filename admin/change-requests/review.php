<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/change-requests/');
    exit;
}

verifyCsrf();

$requestId = (int) ($_POST['request_id'] ?? 0);
$decision  = trim($_POST['decision'] ?? '');

if (!in_array($decision, ['approved', 'rejected'], true)) {
    header('Location: ' . BASE_URL . '/admin/change-requests/?msg=invalid');
    exit;
}

$update = $pdo->prepare(
    "UPDATE change_requests 
     SET status = ?, reviewed_by = ? 
     WHERE request_id = ? AND status = 'pending'"
);
$update->execute([$decision, $_SESSION['admin_id'], $requestId]);

if ($update->rowCount() === 0) {
    header('Location: ' . BASE_URL . '/admin/change-requests/?msg=invalid');
    exit;
}

header('Location: ' . BASE_URL . '/admin/change-requests/?msg=' . $decision);
exit;