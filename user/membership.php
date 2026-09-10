<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];

$stmt = $pdo->prepare("
    SELECT ms.start_date, ms.expiry_date, ms.status, p.plan_name, p.price
    FROM memberships ms
    JOIN plans p ON p.plan_id = ms.plan_id
    WHERE ms.member_id = ?
    ORDER BY ms.start_date DESC
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

$pageTitle = 'My Membership';
require ROOT_PATH . '/includes/user_header.php';
?>

<h1 class="h4 mb-3">My Membership</h1>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Start</th>
                    <th>Expiry</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-muted">No membership yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['plan_name']) ?></td>
                    <td><?= formatMoney($r['price']) ?></td>
                    <td><?= htmlspecialchars($r['start_date']) ?></td>
                    <td><?= htmlspecialchars($r['expiry_date']) ?></td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>