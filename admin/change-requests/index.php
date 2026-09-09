<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';

$where  = "WHERE 1=1";
$params = [];

if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where .= " AND cr.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT cr.*, 
           m.full_name AS member_name, 
           a.full_name AS reviewed_by_name
    FROM change_requests cr
    JOIN members m ON cr.member_id = m.member_id
    LEFT JOIN admins a ON cr.reviewed_by = a.admin_id
    $where
    ORDER BY cr.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$typeLabels = [
    'plan_change'    => 'Plan Change',
    'info_change'    => 'Info Change',
    'trainer_change' => 'Trainer Change',
    'other'          => 'Other',
];

$quickLinks = [
    'plan_change'    => ['label' => 'Go to Memberships', 'url' => '/admin/memberships/add.php'],
    'trainer_change' => ['label' => 'Go to Bookings',    'url' => '/admin/bookings/'],
];

$messages = [
    'added'    => 'Change request submitted.',
    'approved' => 'Request approved.',
    'rejected' => 'Request rejected.',
    'invalid'  => 'That request has already been reviewed.',
];
$flash     = $messages[$_GET['msg'] ?? ''] ?? '';
$flashType = ($_GET['msg'] ?? '') === 'invalid' ? 'warning' : 'success';

$badgeColors = [
    'pending'  => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
];

$pageTitle = 'Change Requests';
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
    <a href="<?= BASE_URL ?>/admin/change-requests/add.php" class="btn btn-dark">+ New Request</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Member</th>
                <th>Type</th>
                <th>Details</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Reviewed By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$requests): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">No change requests yet.</td></tr>
        <?php else: foreach ($requests as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['member_name']) ?></td>
                <td><?= htmlspecialchars($typeLabels[$r['request_type']] ?? $r['request_type']) ?></td>
                <td style="max-width: 260px;"><?= nl2br(htmlspecialchars($r['details'])) ?></td>
                <td><?= formatDate($r['created_at']) ?></td>
                <td>
                    <span class="badge bg-<?= $badgeColors[$r['status']] ?? 'secondary' ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($r['reviewed_by_name'] ?? '-') ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                        <div class="d-flex gap-1 mb-1">
                            <form method="POST" action="<?= BASE_URL ?>/admin/change-requests/review.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                <input type="hidden" name="decision" value="approved">
                                <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>/admin/change-requests/review.php" data-confirm="Reject this request?">
                                <?= csrfField() ?>
                                <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                <input type="hidden" name="decision" value="rejected">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($r['status'] === 'approved' && isset($quickLinks[$r['request_type']])): ?>
                        <a href="<?= BASE_URL . $quickLinks[$r['request_type']]['url'] ?>" class="small">
                            <?= htmlspecialchars($quickLinks[$r['request_type']]['label']) ?> &raquo;
                        </a>
                    <?php elseif ($r['status'] === 'approved' && $r['request_type'] === 'info_change'): ?>
                        <a href="<?= BASE_URL ?>/admin/members/edit.php?id=<?= $r['member_id'] ?>" class="small">
                            Edit Member &raquo;
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>