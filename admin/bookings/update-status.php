<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/bookings/');
    exit;
}

verifyCsrf();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');

$allowed = ['confirmed', 'rejected', 'completed', 'cancelled'];
if ($bookingId <= 0 || !in_array($action, $allowed, true)) {
    header('Location: ' . BASE_URL . '/admin/bookings/?msg=invalid');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT booking_id, slot_id, booking_status FROM bookings WHERE booking_id = ? FOR UPDATE");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/bookings/?msg=notfound');
        exit;
    }

    $current = $booking['booking_status'];
    $ok = false;

    if ($action === 'confirmed' && $current === 'pending') {
        $ok = true;
    } elseif ($action === 'rejected' && $current === 'pending') {
        $ok = true;
    } elseif ($action === 'completed' && $current === 'confirmed') {
        $ok = true;
    } elseif ($action === 'cancelled' && $current === 'confirmed') {
        $ok = true;
    }

    if (!$ok) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/bookings/?msg=notfound');
        exit;
    }

    $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?")
        ->execute([$action, $bookingId]);

    // Free slot only when rejected or cancelled
    if (in_array($action, ['rejected', 'cancelled'], true)) {
        $pdo->prepare("UPDATE trainer_slots SET status = 'available' WHERE slot_id = ?")
            ->execute([$booking['slot_id']]);
    }

    $pdo->commit();
    header('Location: ' . BASE_URL . '/admin/bookings/?msg=' . $action);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . '/admin/bookings/?msg=invalid');
    exit;
}