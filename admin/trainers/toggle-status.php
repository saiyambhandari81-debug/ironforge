<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/trainers/');
    exit;
}

verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT status FROM trainers WHERE trainer_id = ?");
$stmt->execute([$id]);
$trainer = $stmt->fetch();

if ($trainer) {
    $newStatus = $trainer['status'] === 'active' ? 'inactive' : 'active';
    $update = $pdo->prepare("UPDATE trainers SET status = ? WHERE trainer_id = ?");
    $update->execute([$newStatus, $id]);
    $msg = $newStatus === 'active' ? 'activated' : 'deactivated';
} else {
    $msg = 'notfound';
}

header('Location: ' . BASE_URL . '/admin/trainers/?msg=' . $msg);
exit;