<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$membershipsWithDue = $pdo->query("
    SELECT * FROM (
        SELECT
            ms.membership_id,
            ms.total_price,
            m.member_id,
            m.full_name,
            p.plan_name,
            ms.total_price - COALESCE(
                (SELECT SUM(amount) FROM payments pp 
                 WHERE pp.membership_id = ms.membership_id 
                 AND pp.payment_status = 'completed'),
                0
            ) AS due_amount
        FROM memberships ms
        JOIN members m ON ms.member_id = m.member_id
        JOIN plans p ON ms.plan_id = p.plan_id
    ) AS dues
    WHERE due_amount > 0
    ORDER BY full_name
")->fetchAll();

$errors = [];
$old = [
    'membership_id'         => '',
    'amount'                => '',
    'payment_date'          => date('Y-m-d'),
    'payment_method'        => 'cash',
    'transaction_reference' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['membership_id']         = trim($_POST['membership_id'] ?? '');
    $old['amount']                = trim($_POST['amount'] ?? '');
    $old['payment_date']          = trim($_POST['payment_date'] ?? '');
    $old['payment_method']        = trim($_POST['payment_method'] ?? '');
    $old['transaction_reference'] = trim($_POST['transaction_reference'] ?? '');

    $membershipId = (int) $old['membership_id'];

    // ===== VALIDATION =====

    if ($old['membership_id'] === '' || !ctype_digit($old['membership_id'])) {
        $errors[] = 'Please select a valid membership.';
    }

    if ($old['amount'] === '' || !is_numeric($old['amount'])) {
        $errors[] = 'Amount must be a valid number.';
    } elseif ((float) $old['amount'] <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    } elseif ((float) $old['amount'] > 1000000) {
        $errors[] = 'Amount cannot be more than Rs. 1,000,000.';
    }

    if ($old['payment_date'] === '') {
        $errors[] = 'Payment date is required.';
    } else {
        $payDate = DateTime::createFromFormat('Y-m-d', $old['payment_date']);
        $today = new DateTime();
        if (!$payDate || $payDate->format('Y-m-d') !== $old['payment_date']) {
            $errors[] = 'Please enter a valid payment date.';
        } elseif ($payDate > $today) {
            $errors[] = 'Payment date cannot be in the future.';
        }
    }

    if (!in_array($old['payment_method'], ['cash', 'card', 'bank_transfer', 'esewa', 'khalti', 'other'], true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    if ($old['transaction_reference'] !== '' && mb_strlen($old['transaction_reference']) > 100) {
        $errors[] = 'Transaction reference cannot be longer than 100 characters.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare("SELECT total_price, member_id FROM memberships WHERE membership_id = ? FOR UPDATE");
            $lock->execute([$membershipId]);
            $membership = $lock->fetch();

            if (!$membership) {
                $pdo->rollBack();
                $errors[] = 'That membership could not be found.';
            } else {
                $paidStmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM payments 
                     WHERE membership_id = ? AND payment_status = 'completed'"
                );
                $paidStmt->execute([$membershipId]);
                $totalPaid = $paidStmt->fetchColumn();

                $due             = round((float) $membership['total_price'] - (float) $totalPaid, 2);
                $requestedAmount = round((float) $old['amount'], 2);

                if ($requestedAmount > $due) {
                    $pdo->rollBack();
                    $errors[] = 'That payment would exceed the outstanding balance of ' . formatMoney($due) . '.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO payments 
                        (member_id, membership_id, amount, payment_date, payment_method, transaction_reference, payment_status)
                         VALUES (?, ?, ?, ?, ?, ?, 'completed')"
                    );
                    $insert->execute([
                        $membership['member_id'],
                        $membershipId,
                        $old['amount'],
                        $old['payment_date'],
                        $old['payment_method'],
                        $old['transaction_reference'] !== '' ? $old['transaction_reference'] : null,
                    ]);
                    $pdo->commit();

                    header('Location: ' . BASE_URL . '/admin/payments/?msg=added');
                    exit;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

$pageTitle = 'Record Payment';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 600px;">
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

        <?php if (!$membershipsWithDue): ?>
            <div class="alert alert-warning">
                No membership currently has a balance due. 
                <a href="<?= BASE_URL ?>/admin/memberships/add.php">Add a membership</a> first.
            </div>
        <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Membership *</label>
                <select name="membership_id" id="membershipSelect" class="form-select" required>
                    <option value="">-- Select Membership --</option>
                    <?php foreach ($membershipsWithDue as $m): ?>
                        <option value="<?= $m['membership_id'] ?>" 
                                data-due="<?= htmlspecialchars($m['due_amount']) ?>"
                                <?= (string)$old['membership_id'] === (string)$m['membership_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['full_name']) ?> - <?= htmlspecialchars($m['plan_name']) ?> 
                            (Due: <?= formatMoney($m['due_amount']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Amount (Rs.) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="amountInput" 
                           class="form-control" value="<?= htmlspecialchars($old['amount']) ?>" required>
                    <div class="form-text">Auto-fills with full balance. You can lower it for partial payment.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Payment Date *</label>
                    <input type="date" name="payment_date" class="form-control" 
                           value="<?= htmlspecialchars($old['payment_date']) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
    <label class="form-label">Payment Method *</label>
    <select name="payment_method" class="form-select" required>
        <option value="cash" <?= $old['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="card" <?= $old['payment_method'] === 'card' ? 'selected' : '' ?>>Card</option>
        <option value="bank_transfer" <?= $old['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
        <option value="esewa" <?= $old['payment_method'] === 'esewa' ? 'selected' : '' ?>>eSewa</option>
        <option value="khalti" <?= $old['payment_method'] === 'khalti' ? 'selected' : '' ?>>Khalti</option>
        <option value="other" <?= $old['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
    </select>
</div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Transaction Reference</label>
                    <input type="text" name="transaction_reference" class="form-control" 
                           value="<?= htmlspecialchars($old['transaction_reference']) ?>" placeholder="Optional">
                </div>
            </div>

            <button type="submit" class="btn btn-dark">Save Payment</button>
            <a href="<?= BASE_URL ?>/admin/payments/" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>