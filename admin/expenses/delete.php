<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/expenses/');
    exit;
}

verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);

$delete = $pdo->prepare("DELETE FROM expenses WHERE expense_id = ?");
$delete->execute([$id]);

header('Location: ' . BASE_URL . '/admin/expenses/?msg=deleted');
exit;