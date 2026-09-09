<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$memberships = $pdo->query("
    SELECT ms.*, m.full_name AS member_name, p.plan_name
    FROM memberships ms
    JOIN members m ON ms.member_id = m.member_id
    JOIN plans p ON ms.plan_id = p.plan_id
    ORDER BY ms.start_date DESC
")->fetchAll();

$messages = [
    'added'    => 'Membership added successfully.',
    'updated'  => 'Membership updated successfully.',
    'notfound' => 'That membership could not be found.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$badgeColors = [
    'active'    => 'success',
    'expired'   => 'warning',
    'suspended' => 'secondary',
    'cancelled' => 'danger',
];

$pageTitle = 'Memberships';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Memberships</h5>
    <a href="<?= BASE_URL ?>/admin/memberships/add.php" class="btn btn-dark">+ Add Membership</a>
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
                <th>Start</th>
                <th>Expiry</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$memberships): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">No memberships yet.</td></tr>
        <?php else: foreach ($memberships as $ms):
            $isExpired = $ms['status'] === 'active' && $ms['expiry_date'] < date('Y-m-d');
            $displayStatus = $isExpired ? 'expired' : $ms['status'];
            $badgeColor = $badgeColors[$displayStatus] ?? 'secondary';
        ?>
            <tr>
                <td><?= htmlspecialchars($ms['member_name']) ?></td>
                <td><?= htmlspecialchars($ms['plan_name']) ?></td>
                <td><?= formatDate($ms['start_date']) ?></td>
                <td><?= formatDate($ms['expiry_date']) ?></td>
                <td><?= formatMoney($ms['total_price']) ?></td>
                <td><span class="badge bg-<?= $badgeColor ?>"><?= ucfirst($displayStatus) ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/memberships/edit.php?id=<?= $ms['membership_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>