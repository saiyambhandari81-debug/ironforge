<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/trainer-slots/');
    exit;
}

verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);

try {
    $delete = $pdo->prepare("DELETE FROM trainer_slots WHERE slot_id = ? AND status = 'available'");
    $delete->execute([$id]);

    if ($delete->rowCount() === 0) {
        header('Location: ' . BASE_URL . '/admin/trainer-slots/?msg=inuse');
    } else {
        header('Location: ' . BASE_URL . '/admin/trainer-slots/?msg=deleted');
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        header('Location: ' . BASE_URL . '/admin/trainer-slots/?msg=inuse');
    } else {
        throw $e;
    }
}
exit;