<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$members  = $pdo->query("SELECT member_id, full_name FROM members WHERE status = 'active' ORDER BY full_name")->fetchAll();
$trainers = $pdo->query("SELECT trainer_id, full_name FROM trainers WHERE status != 'inactive' ORDER BY full_name")->fetchAll();

$trainerFilter = trim($_GET['trainer_id'] ?? '');

$slotWhere  = "WHERE ts.status = 'available' 
  AND t.status != 'inactive' 
  AND (t.leave_start IS NULL OR t.leave_end IS NULL OR ts.slot_date NOT BETWEEN t.leave_start AND t.leave_end)
  AND NOT EXISTS (
      SELECT 1 FROM trainer_leave_requests tlr 
      WHERE tlr.trainer_id = t.trainer_id 
        AND tlr.status = 'approved' 
        AND ts.slot_date BETWEEN tlr.start_date AND tlr.end_date
  )
  AND ts.slot_date >= CURDATE()";
$slotParams = [];

if ($trainerFilter !== '') {
    $slotWhere .= " AND ts.trainer_id = ?";
    $slotParams[] = (int) $trainerFilter;
}

$slotStmt = $pdo->prepare("
    SELECT ts.slot_id, ts.slot_date, ts.start_time, ts.end_time, t.full_name AS trainer_name
    FROM trainer_slots ts
    JOIN trainers t ON ts.trainer_id = t.trainer_id
    $slotWhere
    ORDER BY ts.slot_date ASC, ts.start_time ASC
");
$slotStmt->execute($slotParams);
$availableSlots = $slotStmt->fetchAll();

$errors = [];
$old = [
    'member_id' => '',
    'slot_id'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['member_id'] = trim($_POST['member_id'] ?? '');
    $old['slot_id']   = trim($_POST['slot_id'] ?? '');

    $memberId = (int) $old['member_id'];
    $slotId   = (int) $old['slot_id'];

    // ===== VALIDATION =====
    if ($old['member_id'] === '' || !ctype_digit($old['member_id'])) {
        $errors[] = 'Please select a valid member.';
    } else {
        $mcheck = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id = ? AND status = 'active'");
        $mcheck->execute([$memberId]);
        if ($mcheck->fetchColumn() == 0) {
            $errors[] = 'That member could not be found or is inactive.';
        } else {
            $access = memberCanBookTrainer($pdo, $memberId, true);
            if (!$access['ok']) {
                $errors[] = $access['error'];
            }
        }
    }

    if ($old['slot_id'] === '' || !ctype_digit($old['slot_id'])) {
        $errors[] = 'Please select a valid available slot.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // Lock the slot so two people can't book it at the same time
            $lock = $pdo->prepare("SELECT trainer_id, status FROM trainer_slots WHERE slot_id = ? FOR UPDATE");
            $lock->execute([$slotId]);
            $slot = $lock->fetch();

            if (!$slot || $slot['status'] !== 'available') {
                $pdo->rollBack();
                $errors[] = 'That slot was just taken (or no longer exists). Please pick another.';
            } else {
                $access = memberCanBookTrainer($pdo, $memberId, true);
                if (!$access['ok']) {
                    $pdo->rollBack();
                    $errors[] = $access['error'];
                } else {
                // Create the booking
                $insertBooking = $pdo->prepare(
                    "INSERT INTO bookings (member_id, trainer_id, slot_id, booking_status) 
                     VALUES (?, ?, ?, 'pending')"
                );
                $insertBooking->execute([$memberId, $slot['trainer_id'], $slotId]);

                // Mark the slot as booked
                $updateSlot = $pdo->prepare("UPDATE trainer_slots SET status = 'booked' WHERE slot_id = ?");
                $updateSlot->execute([$slotId]);

                $pdo->commit();

                header('Location: ' . BASE_URL . '/admin/bookings/?msg=added');
                exit;
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

$pageTitle = 'New Booking';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 700px;">
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

        <div class="alert alert-info">
            Only members with an <strong>active, unexpired</strong> membership on a plan that
            <strong>includes trainer booking</strong> (Premium: Gym + Cardio + Personal trainer) can be booked.
            Basic and Standard plans cannot book trainers.
        </div>

        <?php if (!$members): ?>
            <div class="alert alert-warning">
                You need at least one active member first.
                <a href="<?= BASE_URL ?>/admin/members/add.php">Add one here</a>.
            </div>
        <?php else: ?>

        <form method="GET" class="mb-3">
            <label class="form-label">Filter available slots by trainer</label>
            <select name="trainer_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Trainers</option>
                <?php foreach ($trainers as $t): ?>
                    <option value="<?= $t['trainer_id'] ?>" <?= (string)$trainerFilter === (string)$t['trainer_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if (!$availableSlots): ?>
            <div class="alert alert-warning">
                No available slots<?= $trainerFilter !== '' ? ' for this trainer' : '' ?> right now. 
                <a href="<?= BASE_URL ?>/admin/trainer-slots/add.php">Add a slot</a> first.
            </div>
        <?php else: ?>
        <form method="POST" action="?trainer_id=<?= urlencode($trainerFilter) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Member *</label>
                <select name="member_id" class="form-select" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['member_id'] ?>" <?= (string)$old['member_id'] === (string)$m['member_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Available Slot *</label>
                <select name="slot_id" class="form-select" required>
                    <option value="">-- Select Slot --</option>
                    <?php foreach ($availableSlots as $s): ?>
                        <option value="<?= $s['slot_id'] ?>" <?= (string)$old['slot_id'] === (string)$s['slot_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['trainer_name']) ?> — 
                            <?= formatDate($s['slot_date']) ?> — 
                            <?= date('g:i A', strtotime($s['start_time'])) ?>–<?= date('g:i A', strtotime($s['end_time'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-dark">Request Booking</button>
            <a href="<?= BASE_URL ?>/admin/bookings/" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>