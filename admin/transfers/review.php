<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/transfers/');
    exit;
}

verifyCsrf();

$transferId = (int) ($_POST['transfer_id'] ?? 0);
$decision   = trim($_POST['decision'] ?? '');

if (!in_array($decision, ['approved', 'rejected'], true)) {
    header('Location: ' . BASE_URL . '/admin/transfers/?msg=invalid');
    exit;
}

$pdo->beginTransaction();
try {
    $lock = $pdo->prepare("SELECT * FROM transfers WHERE transfer_id = ? FOR UPDATE");
    $lock->execute([$transferId]);
    $transfer = $lock->fetch();

    if (!$transfer || $transfer['status'] !== 'pending') {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . '/admin/transfers/?msg=invalid');
        exit;
    }

    if ($decision === 'approved') {
        // Re-check that recipient still has no active membership
        $recipientCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM memberships 
             WHERE member_id = ? AND status = 'active' AND expiry_date >= CURDATE()"
        );
        $recipientCheck->execute([$transfer['to_member_id']]);

        if ($recipientCheck->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: ' . BASE_URL . '/admin/transfers/?msg=conflict');
            exit;
        }

        // Actually move the membership
        $moveMembership = $pdo->prepare(
            "UPDATE memberships SET member_id = ? WHERE membership_id = ?"
        );
        $moveMembership->execute([$transfer['to_member_id'], $transfer['membership_id']]);
    }

    // Update the transfer record
    $updateTransfer = $pdo->prepare(
        "UPDATE transfers SET status = ?, approved_by = ? WHERE transfer_id = ?"
    );
    $updateTransfer->execute([$decision, $_SESSION['admin_id'], $transferId]);

    $pdo->commit();
    header('Location: ' . BASE_URL . '/admin/transfers/?msg=' . $decision);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}