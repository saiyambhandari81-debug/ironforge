<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$eligiblePayments = $pdo->query("
    SELECT * FROM (
        SELECT 
            p.payment_id, 
            p.amount, 
            p.payment_date, 
            m.full_name AS member_name, 
            m.member_id,
            p.amount - COALESCE(
                (SELECT SUM(refund_amount) FROM refunds r 
                 WHERE r.payment_id = p.payment_id AND r.status = 'approved'),
                0
            ) AS eligible_amount
        FROM payments p
        JOIN members m ON p.member_id = m.member_id
        WHERE p.payment_status = 'completed'
    ) AS eligible
    WHERE eligible_amount > 0
    ORDER BY payment_date DESC
")->fetchAll();

$errors = [];
$old = [
    'payment_id'    => '',
    'refund_amount' => '',
    'reason'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['payment_id']    = trim($_POST['payment_id'] ?? '');
    $old['refund_amount'] = trim($_POST['refund_amount'] ?? '');
    $old['reason']        = trim($_POST['reason'] ?? '');

    $paymentId = (int) $old['payment_id'];
    $payment = null;

    // ===== VALIDATION =====

    if ($old['payment_id'] === '' || !ctype_digit($old['payment_id'])) {
        $errors[] = 'Please select a valid payment to refund.';
    } else {
        $pStmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? AND payment_status = 'completed'");
        $pStmt->execute([$paymentId]);
        $payment = $pStmt->fetch();
        if (!$payment) {
            $errors[] = 'That payment could not be found or is not eligible.';
        }
    }

    if ($old['refund_amount'] === '' || !is_numeric($old['refund_amount'])) {
        $errors[] = 'Refund amount must be a valid number.';
    } elseif ((float) $old['refund_amount'] <= 0) {
        $errors[] = 'Refund amount must be greater than zero.';
    } elseif ((float) $old['refund_amount'] > 1000000) {
        $errors[] = 'Refund amount cannot be more than Rs. 1,000,000.';
    }

    if ($old['reason'] !== '' && mb_strlen($old['reason']) > 1000) {
        $errors[] = 'Reason cannot be longer than 1000 characters.';
    }

    // Check eligible amount
    if (!$errors && $payment) {
        $refundedStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(refund_amount), 0) FROM refunds 
             WHERE payment_id = ? AND status = 'approved'"
        );
        $refundedStmt->execute([$paymentId]);
        $alreadyRefunded = $refundedStmt->fetchColumn();
        $eligible = round((float) $payment['amount'] - (float) $alreadyRefunded, 2);

        if (round((float) $old['refund_amount'], 2) > $eligible) {
            $errors[] = 'That exceeds the eligible refund amount of ' . formatMoney($eligible) . '.';
        }
    }

    if (!$errors) {
        $insert = $pdo->prepare(
            "INSERT INTO refunds (payment_id, member_id, refund_amount, reason, status)
             VALUES (?, ?, ?, ?, 'pending')"
        );
        $insert->execute([
            $paymentId,
            $payment['member_id'],
            $old['refund_amount'],
            $old['reason'] !== '' ? $old['reason'] : null,
        ]);

        header('Location: ' . BASE_URL . '/admin/refunds/?msg=added');
        exit;
    }
}

$pageTitle = 'Request Refund';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$eligiblePayments): ?>
            <div class="alert alert-warning">No completed payments are currently eligible for a refund.</div>
        <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Payment *</label>
                <select name="payment_id" id="paymentSelect" class="form-select" required>
                    <option value="">-- Select Payment --</option>
                    <?php foreach ($eligiblePayments as $p): ?>
                        <option value="<?= $p['payment_id'] ?>" 
                                data-eligible="<?= htmlspecialchars($p['eligible_amount']) ?>"
                                <?= (string)$old['payment_id'] === (string)$p['payment_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['member_name']) ?> — 
                            <?= formatMoney($p['amount']) ?> on <?= formatDate($p['payment_date']) ?> 
                            (Eligible: <?= formatMoney($p['eligible_amount']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Refund Amount (Rs.) *</label>
                <input type="number" step="0.01" min="0.01" name="refund_amount" id="refundAmountInput" 
                       class="form-control" value="<?= htmlspecialchars($old['refund_amount']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Optional"><?= htmlspecialchars($old['reason']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-dark">Submit Refund Request</button>
            <a href="<?= BASE_URL ?>/admin/refunds/" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>