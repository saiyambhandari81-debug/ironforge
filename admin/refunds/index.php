<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';

$where  = "WHERE 1=1";
$params = [];

if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT r.*, 
           m.full_name AS member_name, 
           p.amount AS payment_amount, 
           a.full_name AS approved_by_name
    FROM refunds r
    JOIN members m ON r.member_id = m.member_id
    JOIN payments p ON r.payment_id = p.payment_id
    LEFT JOIN admins a ON r.approved_by = a.admin_id
    $where
    ORDER BY r.request_date DESC
");
$stmt->execute($params);
$refunds = $stmt->fetchAll();

$messages = [
    'added'    => 'Refund request submitted.',
    'approved' => 'Refund approved.',
    'rejected' => 'Refund rejected.',
    'invalid'  => 'That refund has already been reviewed.',
    'conflict' => 'Cannot approve — this would exceed the eligible refund amount.',
];
$flash     = $messages[$_GET['msg'] ?? ''] ?? '';
$flashType = in_array($_GET['msg'] ?? '', ['invalid', 'conflict'], true) ? 'warning' : 'success';

$badgeColors = [
    'pending'  => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
];

$pageTitle = 'Refund Requests';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending"  <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
    </form>
    <a href="<?= BASE_URL ?>/admin/refunds/add.php" class="btn btn-dark">+ Request Refund</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Member</th>
                <th>Original Payment</th>
                <th>Refund Amount</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Reviewed By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$refunds): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">No refund requests yet.</td></tr>
        <?php else: foreach ($refunds as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['member_name']) ?></td>
                <td><?= formatMoney($r['payment_amount']) ?></td>
                <td><?= formatMoney($r['refund_amount']) ?></td>
                <td><?= formatDate($r['request_date']) ?></td>
                <td>
                    <span class="badge bg-<?= $badgeColors[$r['status']] ?? 'secondary' ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($r['approved_by_name'] ?? '-') ?></td>
                <td class="d-flex gap-1">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/refunds/review.php" data-confirm="Approve this refund?">
                            <?= csrfField() ?>
                            <input type="hidden" name="refund_id" value="<?= $r['refund_id'] ?>">
                            <input type="hidden" name="decision" value="approved">
                            <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/refunds/review.php" data-confirm="Reject this refund request?">
                            <?= csrfField() ?>
                            <input type="hidden" name="refund_id" value="<?= $r['refund_id'] ?>">
                            <input type="hidden" name="decision" value="rejected">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>