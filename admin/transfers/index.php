<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';

$where  = "WHERE 1=1";
$params = [];

if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where .= " AND tr.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT tr.*, 
           fm.full_name AS from_name, 
           tm.full_name AS to_name, 
           p.plan_name, 
           a.full_name AS approved_by_name
    FROM transfers tr
    JOIN members fm ON tr.from_member_id = fm.member_id
    JOIN members tm ON tr.to_member_id = tm.member_id
    JOIN memberships ms ON tr.membership_id = ms.membership_id
    JOIN plans p ON ms.plan_id = p.plan_id
    LEFT JOIN admins a ON tr.approved_by = a.admin_id
    $where
    ORDER BY tr.request_date DESC
");
$stmt->execute($params);
$transfers = $stmt->fetchAll();

$messages = [
    'added'    => 'Transfer request submitted.',
    'approved' => 'Transfer approved and membership reassigned.',
    'rejected' => 'Transfer rejected.',
    'invalid'  => 'That transfer has already been reviewed.',
    'conflict' => 'Cannot approve — the recipient now has an active membership of their own.',
];
$flash     = $messages[$_GET['msg'] ?? ''] ?? '';
$flashType = in_array($_GET['msg'] ?? '', ['invalid', 'conflict'], true) ? 'warning' : 'success';

$badgeColors = [
    'pending'  => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
];

$pageTitle = 'Membership Transfers';
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
    <a href="<?= BASE_URL ?>/admin/transfers/add.php" class="btn btn-dark">+ Request Transfer</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>From</th>
                <th>To</th>
                <th>Plan</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Reviewed By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$transfers): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">No transfer requests yet.</td></tr>
        <?php else: foreach ($transfers as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['from_name']) ?></td>
                <td><?= htmlspecialchars($t['to_name']) ?></td>
                <td><?= htmlspecialchars($t['plan_name']) ?></td>
                <td><?= formatDate($t['request_date']) ?></td>
                <td>
                    <span class="badge bg-<?= $badgeColors[$t['status']] ?? 'secondary' ?>">
                        <?= ucfirst($t['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($t['approved_by_name'] ?? '-') ?></td>
                <td class="d-flex gap-1">
                    <?php if ($t['status'] === 'pending'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/transfers/review.php" 
                              data-confirm="Approve this transfer? The membership will move immediately.">
                            <?= csrfField() ?>
                            <input type="hidden" name="transfer_id" value="<?= $t['transfer_id'] ?>">
                            <input type="hidden" name="decision" value="approved">
                            <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/transfers/review.php" 
                              data-confirm="Reject this transfer request?">
                            <?= csrfField() ?>
                            <input type="hidden" name="transfer_id" value="<?= $t['transfer_id'] ?>">
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