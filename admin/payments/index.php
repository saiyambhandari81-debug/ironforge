<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$search  = trim($_GET['search'] ?? '');
$perPage = 10;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND m.full_name LIKE ?";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN members m ON p.member_id = m.member_id $where");
$countStmt->execute($params);
$totalPayments = $countStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalPayments / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

$sql = "
    SELECT p.*, m.full_name, pl.plan_name
    FROM payments p
    JOIN members m ON p.member_id = m.member_id
    LEFT JOIN memberships ms ON p.membership_id = ms.membership_id
    LEFT JOIN plans pl ON ms.plan_id = pl.plan_id
    $where
    ORDER BY p.payment_date DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$messages = ['added' => 'Payment recorded successfully.'];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$statusColors = [
    'completed' => 'success',
    'pending'   => 'warning',
    'failed'    => 'danger',
    'refunded'  => 'secondary'
];

$pageTitle = 'Payments';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search by member name" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline-dark">Search</button>
    </form>
    <a href="<?= BASE_URL ?>/admin/payments/add.php" class="btn btn-dark">+ Record Payment</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Member</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Method</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$payments): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">No payments recorded yet.</td></tr>
        <?php else: foreach ($payments as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['full_name']) ?></td>
                <td><?= htmlspecialchars($p['plan_name'] ?? '-') ?></td>
                <td><?= formatMoney($p['amount']) ?></td>
                <td><?= formatDate($p['payment_date']) ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?></td>
                <td><span class="badge bg-<?= $statusColors[$p['payment_status']] ?? 'secondary' ?>"><?= ucfirst($p['payment_status']) ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/payments/receipt.php?id=<?= $p['payment_id'] ?>" class="btn btn-sm btn-outline-secondary">Receipt</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination">
        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>"><?= $pg ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>