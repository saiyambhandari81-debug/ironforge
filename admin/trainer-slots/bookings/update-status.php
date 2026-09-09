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
$newStatus = trim($_POST['new_status'] ?? '');

// Allowed status transitions
$allowedTransitions = [
    'pending'   => ['confirmed', 'rejected'],
    'confirmed' => ['completed', 'cancelled'],
];

$pdo->beginTransaction();
try {
    $lock = $pdo->prepare("SELECT booking_status, slot_id FROM bookings WHERE booking_id = ? FOR UPDATE");
    $lock->execute([$bookingId]);
    $booking = $lock->fetch();

    $validTransition = $booking
        && isset($allowedTransitions[$booking['booking_status']])
        && in_array($newStatus, $allowedTransitions[$booking['booking_status']], true);

    if (!$validTransition) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/bookings/?msg=invalid');
        exit;
    }

    // Update booking status
    $update = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
    $update->execute([$newStatus, $bookingId]);

    // Release the slot only if rejected or cancelled
    if (in_array($newStatus, ['rejected', 'cancelled'], true)) {
        $releaseSlot = $pdo->prepare("UPDATE trainer_slots SET status = 'available' WHERE slot_id = ?");
        $releaseSlot->execute([$booking['slot_id']]);
    }

    $pdo->commit();
    header('Location: ' . BASE_URL . '/admin/bookings/?msg=' . $newStatus);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}