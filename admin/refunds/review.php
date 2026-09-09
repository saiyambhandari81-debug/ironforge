<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/refunds/');
    exit;
}

verifyCsrf();

$refundId = (int) ($_POST['refund_id'] ?? 0);
$decision = trim($_POST['decision'] ?? '');

if (!in_array($decision, ['approved', 'rejected'], true)) {
    header('Location: ' . BASE_URL . '/admin/refunds/?msg=invalid');
    exit;
}

$pdo->beginTransaction();
try {
    // Lock the refund row
    $lock = $pdo->prepare("SELECT * FROM refunds WHERE refund_id = ? FOR UPDATE");
    $lock->execute([$refundId]);
    $refund = $lock->fetch();

    if (!$refund || $refund['status'] !== 'pending') {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/refunds/?msg=invalid');
        exit;
    }

    if ($decision === 'approved') {
        // Lock the original payment
        $paymentLock = $pdo->prepare("SELECT amount FROM payments WHERE payment_id = ? FOR UPDATE");
        $paymentLock->execute([$refund['payment_id']]);
        $payment = $paymentLock->fetch();

        // Recalculate already refunded amount (inside the lock)
        $refundedStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(refund_amount), 0) FROM refunds 
             WHERE payment_id = ? AND status = 'approved'"
        );
        $refundedStmt->execute([$refund['payment_id']]);
        $alreadyRefunded = $refundedStmt->fetchColumn();

        $eligible = round((float) $payment['amount'] - (float) $alreadyRefunded, 2);

        // Block if this refund would exceed remaining eligible amount
        if (round((float) $refund['refund_amount'], 2) > $eligible) {
            $pdo->rollBack();
            header('Location: ' . BASE_URL . '/admin/refunds/?msg=conflict');
            exit;
        }

        // If this approval fully refunds the payment, mark it as refunded
        $newTotalRefunded = round($alreadyRefunded + (float) $refund['refund_amount'], 2);
        if ($newTotalRefunded >= round((float) $payment['amount'], 2)) {
            $updatePayment = $pdo->prepare(
                "UPDATE payments SET payment_status = 'refunded' WHERE payment_id = ?"
            );
            $updatePayment->execute([$refund['payment_id']]);
        }
    }

    // Update the refund status
    $updateRefund = $pdo->prepare(
        "UPDATE refunds SET status = ?, approved_by = ? WHERE refund_id = ?"
    );
    $updateRefund->execute([$decision, $_SESSION['admin_id'], $refundId]);

    $pdo->commit();
    header('Location: ' . BASE_URL . '/admin/refunds/?msg=' . $decision);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}