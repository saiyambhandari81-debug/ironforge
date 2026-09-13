<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/trainer/slots.php');
    exit;
}

verifyCsrf();

$slotId    = (int) ($_POST['slot_id'] ?? 0);
$trainerId = (int) $_SESSION['trainer_id'];

// 1. Verify ownership (IDOR check)
$stmt = $pdo->prepare("
    SELECT slot_id, status 
    FROM trainer_slots 
    WHERE slot_id = ? AND trainer_id = ?
");
$stmt->execute([$slotId, $trainerId]);
$slot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slot) {
    header('Location: ' . BASE_URL . '/trainer/slots.php?msg=notfound');
    exit;
}

// 2. Check for active bookings
$bkStmt = $pdo->prepare("
    SELECT booking_id 
    FROM bookings 
    WHERE slot_id = ? AND booking_status IN ('pending', 'confirmed')
");
$bkStmt->execute([$slotId]);
$hasActiveBookings = $bkStmt->rowCount() > 0;

if ($hasActiveBookings) {
    // Has bookings -> Do NOT hard delete; mark slot and booking as cancelled
    $updSlot = $pdo->prepare("
        UPDATE trainer_slots 
        SET status = 'cancelled' 
        WHERE slot_id = ? AND trainer_id = ?
    ");
    $updSlot->execute([$slotId, $trainerId]);

    $updBk = $pdo->prepare("
        UPDATE bookings 
        SET booking_status = 'cancelled' 
        WHERE slot_id = ? AND booking_status IN ('pending', 'confirmed')
    ");
    $updBk->execute([$slotId]);

    header('Location: ' . BASE_URL . '/trainer/slots.php?msg=cancelled');
    exit;
} else {
    // No active bookings -> Delete or set cancelled
    try {
        $del = $pdo->prepare("
            DELETE FROM trainer_slots 
            WHERE slot_id = ? AND trainer_id = ?
        ");
        $del->execute([$slotId, $trainerId]);

        if ($del->rowCount() > 0) {
            header('Location: ' . BASE_URL . '/trainer/slots.php?msg=deleted');
            exit;
        } else {
            header('Location: ' . BASE_URL . '/trainer/slots.php?msg=notfound');
            exit;
        }
    } catch (PDOException $e) {
        // Fall back to cancelling if foreign key history exists
        $updSlot = $pdo->prepare("
            UPDATE trainer_slots 
            SET status = 'cancelled' 
            WHERE slot_id = ? AND trainer_id = ?
        ");
        $updSlot->execute([$slotId, $trainerId]);

        header('Location: ' . BASE_URL . '/trainer/slots.php?msg=cancelled');
        exit;
    }
}
