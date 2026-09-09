<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';
$perPage      = 15;
$page         = max(1, (int) ($_GET['page'] ?? 1));

$where  = "WHERE 1=1";
$params = [];

if (in_array($statusFilter, ['pending', 'confirmed', 'rejected', 'completed', 'cancelled'], true)) {
    $where .= " AND b.booking_status = ?";
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b $where");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "
    SELECT b.*, 
           m.full_name AS member_name, 
           t.full_name AS trainer_name, 
           ts.slot_date, ts.start_time, ts.end_time
    FROM bookings b
    JOIN members m ON b.member_id = m.member_id
    JOIN trainers t ON b.trainer_id = t.trainer_id
    JOIN trainer_slots ts ON b.slot_id = ts.slot_id
    $where
    ORDER BY ts.slot_date DESC, ts.start_time DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$messages = [
    'added'     => 'Booking requested successfully.',
    'confirmed' => 'Booking confirmed.',
    'rejected'  => 'Booking rejected.',
    'completed' => 'Booking marked completed.',
    'cancelled' => 'Booking cancelled.',
    'invalid'   => 'That action no longer applies to this booking.',
];
$flash     = $messages[$_GET['msg'] ?? ''] ?? '';
$flashType = ($_GET['msg'] ?? '') === 'invalid' ? 'warning' : 'success';

$badgeColors = [
    'pending'   => 'warning',
    'confirmed' => 'success',
    'rejected'  => 'danger',
    'completed' => 'secondary',
    'cancelled' => 'secondary',
];

$pageTitle = 'Bookings';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending"   <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="rejected"  <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </form>
    <a href="<?= BASE_URL ?>/admin/bookings/add.php" class="btn btn-dark">+ New Booking</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Member</th>
                <th>Trainer</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$bookings): ?>
            <tr><td colspan="6" class="text-muted text-center py-4">No bookings found.</td></tr>
        <?php else: foreach ($bookings as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['member_name']) ?></td>
                <td><?= htmlspecialchars($b['trainer_name']) ?></td>
                <td><?= formatDate($b['slot_date']) ?></td>
                <td>
                    <?= date('g:i A', strtotime($b['start_time'])) ?> - 
                    <?= date('g:i A', strtotime($b['end_time'])) ?>
                </td>
                <td>
                    <span class="badge bg-<?= $badgeColors[$b['booking_status']] ?? 'secondary' ?>">
                        <?= ucfirst($b['booking_status']) ?>
                    </span>
                </td>
                <td class="d-flex gap-1">
                    <?php if ($b['booking_status'] === 'pending'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                            <input type="hidden" name="new_status" value="confirmed">
                            <button type="submit" class="btn btn-sm btn-outline-success">Confirm</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php" data-confirm="Reject this booking request?">
                            <?= csrfField() ?>
                            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                            <input type="hidden" name="new_status" value="rejected">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    <?php elseif ($b['booking_status'] === 'confirmed'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php">
                            <?= csrfField() ?>
                            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                            <input type="hidden" name="new_status" value="completed">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Mark Completed</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php" data-confirm="Cancel this confirmed booking?">
                            <?= csrfField() ?>
                            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                            <input type="hidden" name="new_status" value="cancelled">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                        </form>
                    <?php endif; ?>
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
                <a class="page-link" href="?page=<?= $pg ?>&status=<?= urlencode($statusFilter) ?>"><?= $pg ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>