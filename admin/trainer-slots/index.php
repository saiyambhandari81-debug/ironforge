<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$trainerFilter = trim($_GET['trainer_id'] ?? '');
$startDate     = trim($_GET['start_date'] ?? date('Y-m-d'));
$endDate       = trim($_GET['end_date'] ?? date('Y-m-d', strtotime('+7 days')));

if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
    $startDate = date('Y-m-d');
}
if (!DateTime::createFromFormat('Y-m-d', $endDate)) {
    $endDate = date('Y-m-d', strtotime('+7 days'));
}

$trainers = $pdo->query("SELECT trainer_id, full_name FROM trainers ORDER BY full_name")->fetchAll();

$where  = "WHERE ts.slot_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($trainerFilter !== '') {
    $where .= " AND ts.trainer_id = ?";
    $params[] = (int) $trainerFilter;
}

$stmt = $pdo->prepare("
    SELECT ts.*, t.full_name
    FROM trainer_slots ts
    JOIN trainers t ON ts.trainer_id = t.trainer_id
    $where
    ORDER BY ts.slot_date ASC, ts.start_time ASC
");
$stmt->execute($params);
$slots = $stmt->fetchAll();

$messages = [
    'added'   => 'Slot added successfully.',
    'deleted' => 'Slot deleted.',
    'inuse'   => 'That slot has a booking and cannot be deleted.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$pageTitle = 'Trainer Slots';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Trainer Slots</h5>
    <a href="<?= BASE_URL ?>/admin/trainer-slots/add.php" class="btn btn-dark">+ Add Slot</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="trainer_id" class="form-select">
            <option value="">All Trainers</option>
            <?php foreach ($trainers as $t): ?>
                <option value="<?= $t['trainer_id'] ?>" <?= (string)$trainerFilter === (string)$t['trainer_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['full_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="col-auto">
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-dark">Filter</button>
    </div>
</form>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Trainer</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$slots): ?>
            <tr><td colspan="5" class="text-muted text-center py-4">No slots in this range.</td></tr>
        <?php else: foreach ($slots as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['full_name']) ?></td>
                <td><?= formatDate($s['slot_date']) ?></td>
                <td>
                    <?= date('g:i A', strtotime($s['start_time'])) ?> - 
                    <?= date('g:i A', strtotime($s['end_time'])) ?>
                </td>
                <td>
                    <span class="badge bg-<?= $s['status'] === 'available' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($s['status']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($s['status'] === 'available'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/trainer-slots/delete.php" data-confirm="Delete this slot?">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $s['slot_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>