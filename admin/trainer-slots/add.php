<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$trainers = $pdo->query("SELECT trainer_id, full_name FROM trainers WHERE status = 'active' ORDER BY full_name")->fetchAll();

$errors = [];
$old = [
    'trainer_id' => '',
    'slot_date'  => date('Y-m-d'),
    'start_time' => '',
    'end_time'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['trainer_id'] = trim($_POST['trainer_id'] ?? '');
    $old['slot_date']  = trim($_POST['slot_date'] ?? '');
    $old['start_time'] = trim($_POST['start_time'] ?? '');
    $old['end_time']   = trim($_POST['end_time'] ?? '');

    $trainerId = (int) $old['trainer_id'];

    // ===== VALIDATION =====

    // Trainer
    if ($old['trainer_id'] === '' || !ctype_digit($old['trainer_id'])) {
        $errors[] = 'Please select a valid trainer.';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM trainers WHERE trainer_id = ? AND status = 'active'");
        $check->execute([$trainerId]);
        if ($check->fetchColumn() == 0) {
            $errors[] = 'That trainer could not be found or is inactive.';
        }
    }

    // Date
    if ($old['slot_date'] === '') {
        $errors[] = 'Date is required.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $old['slot_date']);
        $today = new DateTime('today');

        if (!$date || $date->format('Y-m-d') !== $old['slot_date']) {
            $errors[] = 'Please enter a valid date.';
        } elseif ($date < $today) {
            $errors[] = 'Slot date cannot be in the past.';
        }
    }

    // Times
    if ($old['start_time'] === '' || $old['end_time'] === '') {
        $errors[] = 'Both start time and end time are required.';
    } elseif ($old['start_time'] >= $old['end_time']) {
        $errors[] = 'End time must be after start time.';
    }

    // Overlap check (only if no other errors)
    if (!$errors) {
        $overlapCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM trainer_slots
             WHERE trainer_id = ? 
               AND slot_date = ?
               AND start_time < ? 
               AND end_time > ?"
        );
        $overlapCheck->execute([
            $trainerId,
            $old['slot_date'],
            $old['end_time'],
            $old['start_time']
        ]);

        if ($overlapCheck->fetchColumn() > 0) {
            $errors[] = 'This overlaps with a slot this trainer already has on that date.';
        }
    }

    // Save
    if (!$errors) {
        $insert = $pdo->prepare(
            "INSERT INTO trainer_slots (trainer_id, slot_date, start_time, end_time, status)
             VALUES (?, ?, ?, ?, 'available')"
        );
        $insert->execute([
            $trainerId,
            $old['slot_date'],
            $old['start_time'],
            $old['end_time']
        ]);

        header('Location: ' . BASE_URL . '/admin/trainer-slots/?msg=added');
        exit;
    }
}

// Upcoming slots for reference
$upcomingSlots = $pdo->query("
    SELECT ts.slot_date, ts.start_time, ts.end_time, ts.status, t.full_name
    FROM trainer_slots ts
    JOIN trainers t ON ts.trainer_id = t.trainer_id
    WHERE ts.slot_date >= CURDATE()
    ORDER BY ts.slot_date ASC, t.full_name ASC, ts.start_time ASC
    LIMIT 15
")->fetchAll();

$pageTitle = 'Add Trainer Slot';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!$trainers): ?>
                    <div class="alert alert-warning">
                        You need at least one active trainer first. 
                        <a href="<?= BASE_URL ?>/admin/trainers/add.php">Add one here</a>.
                    </div>
                <?php else: ?>
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Trainer *</label>
                        <select name="trainer_id" class="form-select" required>
                            <option value="">-- Select Trainer --</option>
                            <?php foreach ($trainers as $t): ?>
                                <option value="<?= $t['trainer_id'] ?>" <?= (string)$old['trainer_id'] === (string)$t['trainer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" name="slot_date" class="form-control" value="<?= htmlspecialchars($old['slot_date']) ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" class="form-control" value="<?= htmlspecialchars($old['start_time']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" class="form-control" value="<?= htmlspecialchars($old['end_time']) ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark">Save Slot</button>
                    <a href="<?= BASE_URL ?>/admin/trainer-slots/" class="btn btn-outline-secondary">Cancel</a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Upcoming Slots (for reference)</div>
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Trainer</th>
                        <th>Date</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$upcomingSlots): ?>
                    <tr><td colspan="3" class="text-muted text-center py-3">None yet.</td></tr>
                <?php else: foreach ($upcomingSlots as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                        <td><?= formatDate($s['slot_date']) ?></td>
                        <td>
                            <?= date('g:i A', strtotime($s['start_time'])) ?> - 
                            <?= date('g:i A', strtotime($s['end_time'])) ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>