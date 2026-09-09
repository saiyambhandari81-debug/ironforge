<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$statusFilter = $_GET['status'] ?? '';
$where  = 'WHERE 1=1';
$params = [];

if (in_array($statusFilter, ['pending', 'confirmed', 'rejected', 'completed', 'cancelled'], true)) {
    $where .= ' AND b.booking_status = ?';
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT b.*, m.full_name AS member_name, t.full_name AS trainer_name,
           ts.slot_date, ts.start_time, ts.end_time
    FROM bookings b
    JOIN members m ON m.member_id = b.member_id
    JOIN trainers t ON t.trainer_id = b.trainer_id
    JOIN trainer_slots ts ON ts.slot_id = b.slot_id
    $where
    ORDER BY ts.slot_date DESC, ts.start_time DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$messages = [
    'added'     => 'Booking created successfully.',
    'confirmed' => 'Booking confirmed.',
    'rejected'  => 'Booking rejected. Slot is available again.',
    'completed' => 'Booking marked completed.',
    'cancelled' => 'Booking cancelled. Slot is available again.',
    'notfound'  => 'Booking not found or already updated.',
    'invalid'   => 'Invalid action.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$badge = [
    'pending'   => 'warning',
    'confirmed' => 'success',
    'rejected'  => 'danger',
    'completed' => 'primary',
    'cancelled' => 'secondary',
];

$pageTitle = 'Bookings';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Bookings</h5>
    <a href="<?= BASE_URL ?>/admin/bookings/add.php" class="btn btn-dark">+ New Booking</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['pending','confirmed','rejected','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-dark">Filter</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Trainer</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$bookings): ?>
                <tr><td colspan="6" class="text-muted">No bookings yet.</td></tr>
            <?php else: foreach ($bookings as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['member_name']) ?></td>
                    <td><?= htmlspecialchars($b['trainer_name']) ?></td>
                    <td><?= htmlspecialchars($b['slot_date']) ?></td>
                    <td><?= htmlspecialchars(substr($b['start_time'], 0, 5)) ?>–<?= htmlspecialchars(substr($b['end_time'], 0, 5)) ?></td>
                    <td>
                        <span class="badge text-bg-<?= $badge[$b['booking_status']] ?? 'secondary' ?>">
                            <?= htmlspecialchars(ucfirst($b['booking_status'])) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <?php if ($b['booking_status'] === 'pending'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <input type="hidden" name="action" value="confirmed">
                                <button class="btn btn-sm btn-success">Confirm</button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <input type="hidden" name="action" value="rejected">
                                <button class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        <?php elseif ($b['booking_status'] === 'confirmed'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <input type="hidden" name="action" value="completed">
                                <button class="btn btn-sm btn-primary">Complete</button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>/admin/bookings/update-status.php" class="d-inline"
                                  onsubmit="return confirm('Cancel this booking?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <input type="hidden" name="action" value="cancelled">
                                <button class="btn btn-sm btn-outline-secondary">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>