<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];
$errors = [];
$success = '';

// Memberships with remaining balance (pending or active)
$dueStmt = $pdo->prepare("
    SELECT ms.membership_id, ms.total_price, ms.status, p.plan_name,
           ms.total_price - COALESCE((
               SELECT SUM(amount) FROM payments
               WHERE membership_id = ms.membership_id
                 AND payment_status = 'completed'
           ), 0) AS due_amount
    FROM memberships ms
    JOIN plans p ON p.plan_id = ms.plan_id
    WHERE ms.member_id = ?
      AND ms.status IN ('pending', 'active')
    HAVING due_amount > 0.009
    ORDER BY ms.start_date DESC
");
$dueStmt->execute([$memberId]);
$dueList = $dueStmt->fetchAll();

$histStmt = $pdo->prepare("
    SELECT amount, payment_date, payment_method, payment_status, transaction_reference
    FROM payments
    WHERE member_id = ?
    ORDER BY payment_date DESC, payment_id DESC
    LIMIT 50
");
$histStmt->execute([$memberId]);
$history = $histStmt->fetchAll();

$methods = ['cash', 'card', 'bank_transfer', 'esewa', 'khalti', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $membershipId = (int) ($_POST['membership_id'] ?? 0);
    $amount       = trim($_POST['amount'] ?? '');
    $method       = trim($_POST['payment_method'] ?? '');

    if ($membershipId <= 0) {
        $errors[] = 'Please select a membership to pay.';
    }
    if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = 'Enter a valid amount greater than 0.';
    }
    if (!in_array($method, $methods, true)) {
        $errors[] = 'Please select a payment method.';
    }

    $dueAmount = null;
    $planName  = '';
    $msStatus  = '';

    if (!$errors) {
        $check = $pdo->prepare("
            SELECT ms.membership_id, ms.total_price, ms.status, p.plan_name,
                   ms.total_price - COALESCE((
                       SELECT SUM(amount) FROM payments
                       WHERE membership_id = ms.membership_id
                         AND payment_status = 'completed'
                   ), 0) AS due_amount
            FROM memberships ms
            JOIN plans p ON p.plan_id = ms.plan_id
            WHERE ms.membership_id = ? AND ms.member_id = ?
        ");
        $check->execute([$membershipId, $memberId]);
        $row = $check->fetch();

        if (!$row) {
            $errors[] = 'That membership was not found.';
        } else {
            $dueAmount = (float) $row['due_amount'];
            $planName  = $row['plan_name'];
            $msStatus  = $row['status'];
            $payAmount = round((float) $amount, 2);

            if ($dueAmount <= 0) {
                $errors[] = 'This membership is already fully paid.';
            } elseif ($payAmount > $dueAmount + 0.009) {
                $errors[] = 'Amount cannot exceed the balance due (' . formatMoney($dueAmount) . ').';
            }
        }
    }

    if (!$errors) {
        $payAmount = round((float) $amount, 2);

        $ins = $pdo->prepare("
            INSERT INTO payments (
                member_id, membership_id, amount, payment_date,
                payment_method, payment_status, transaction_reference
            )
            VALUES (?, ?, ?, CURDATE(), ?, 'completed', 'member_portal')
        ");
        $ins->execute([$memberId, $membershipId, $payAmount, $method]);

        $success = 'Payment of ' . formatMoney($payAmount) . ' recorded for ' . $planName . '.';

        // Recalculate due; activate if fully paid and was pending
        $paidStmt = $pdo->prepare("
            SELECT ms.status,
                   ms.total_price - COALESCE((
                       SELECT SUM(amount) FROM payments
                       WHERE membership_id = ms.membership_id
                         AND payment_status = 'completed'
                   ), 0) AS due_amount
            FROM memberships ms
            WHERE ms.membership_id = ? AND ms.member_id = ?
        ");
        $paidStmt->execute([$membershipId, $memberId]);
        $msRow = $paidStmt->fetch();

        if (
            $msRow
            && (float) $msRow['due_amount'] <= 0.009
            && $msRow['status'] === 'pending'
        ) {
            $pdo->prepare("UPDATE memberships SET status = 'active' WHERE membership_id = ?")
                ->execute([$membershipId]);
            $success .= ' Membership is now active.';
        }

        $dueStmt->execute([$memberId]);
        $dueList = $dueStmt->fetchAll();
        $histStmt->execute([$memberId]);
        $history = $histStmt->fetchAll();
    }
}

$pageTitle = 'My Payments';
require ROOT_PATH . '/includes/user_header.php';
?>

<h1 class="h4 mb-3">My Payments</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card p-3 mb-4">
    <h2 class="h6 mb-3">Make a payment</h2>

    <?php if (!$dueList): ?>
        <p class="text-muted mb-0">
            Nothing due right now. Apply for a membership first, or your balance is already paid.
        </p>
    <?php else: ?>
        <form method="POST" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-6">
                <label class="form-label">Membership *</label>
                <select name="membership_id" id="membership_id" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($dueList as $d): ?>
                        <option value="<?= (int) $d['membership_id'] ?>"
                                data-due="<?= htmlspecialchars((string) $d['due_amount']) ?>">
                            <?= htmlspecialchars($d['plan_name']) ?>
                            (<?= htmlspecialchars($d['status']) ?>)
                            — Due: <?= formatMoney($d['due_amount']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Amount (Rs) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Method *</label>
                <select name="payment_method" class="form-select" required>
                    <option value="">-- Select --</option>
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="esewa">eSewa</option>
                    <option value="khalti">Khalti</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-dark">Pay now</button>
            </div>
        </form>
        <script>
            document.getElementById('membership_id').addEventListener('change', function () {
                var opt = this.options[this.selectedIndex];
                var due = opt.getAttribute('data-due');
                if (due) document.getElementById('amount').value = parseFloat(due).toFixed(2);
            });
        </script>
        <p class="small text-muted mt-2 mb-0">Pay the full due amount to activate a pending membership.</p>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Payment history</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$history): ?>
                <tr><td colspan="4" class="text-muted">No payments yet.</td></tr>
            <?php else: foreach ($history as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['payment_date']) ?></td>
                    <td><?= formatMoney($r['amount']) ?></td>
                    <td>
                        <?= htmlspecialchars($r['payment_method']) ?>
                        <?php if (($r['transaction_reference'] ?? '') === 'member_portal'): ?>
                            <span class="badge bg-info text-dark">Online</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['payment_status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>