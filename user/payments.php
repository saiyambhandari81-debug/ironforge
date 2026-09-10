<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];

$stmt = $pdo->prepare("
    SELECT amount, payment_date, payment_method, payment_status
    FROM payments
    WHERE member_id = ?
    ORDER BY payment_date DESC
    LIMIT 50
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

$pageTitle = 'My Payments';
require ROOT_PATH . '/includes/user_header.php';
?>

<h1 class="h4 mb-3">My Payments</h1>

<div class="card">
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
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="text-muted">No payments yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['payment_date']) ?></td>
                    <td><?= formatMoney($r['amount']) ?></td>
                    <td><?= htmlspecialchars($r['payment_method']) ?></td>
                    <td><?= htmlspecialchars($r['payment_status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>