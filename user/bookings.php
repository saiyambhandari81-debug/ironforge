<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];
$errors = [];
$success = '';

$bookingAccess = memberCanBookTrainer($pdo, $memberId);
$hasMembership = ($bookingAccess['ok'] || $bookingAccess['code'] !== 'no_membership');
$canBookTrainer = $bookingAccess['ok'];

// Available future slots
$slots = $pdo->query("
    SELECT ts.slot_id, ts.slot_date, ts.start_time, ts.end_time, t.full_name AS trainer_name
    FROM trainer_slots ts
    JOIN trainers t ON t.trainer_id = ts.trainer_id
    WHERE ts.status = 'available'
      AND t.status != 'inactive'
      AND (t.leave_start IS NULL OR t.leave_end IS NULL OR ts.slot_date NOT BETWEEN t.leave_start AND t.leave_end)
      AND NOT EXISTS (
          SELECT 1 FROM trainer_leave_requests tlr
          WHERE tlr.trainer_id = t.trainer_id
            AND tlr.status = 'approved'
            AND ts.slot_date BETWEEN tlr.start_date AND tlr.end_date
      )
      AND ts.slot_date >= CURDATE()
    ORDER BY ts.slot_date, ts.start_time
")->fetchAll();

// Submit booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $slotId = (int) ($_POST['slot_id'] ?? 0);
    $bookingAccess = memberCanBookTrainer($pdo, $memberId);
    $hasMembership = ($bookingAccess['ok'] || $bookingAccess['code'] !== 'no_membership');
    $canBookTrainer = $bookingAccess['ok'];

    if (!$bookingAccess['ok']) {
        $errors[] = $bookingAccess['error'];
    } elseif ($slotId <= 0) {
        $errors[] = 'Please select a slot.';
    } else {
        try {
            $pdo->beginTransaction();

            $lock = $pdo->prepare("
                SELECT ts.slot_id, ts.status, ts.slot_date, ts.trainer_id, t.status AS trainer_status, t.leave_start, t.leave_end
                FROM trainer_slots ts
                JOIN trainers t ON t.trainer_id = ts.trainer_id
                WHERE ts.slot_id = ?
                FOR UPDATE
            ");
            $lock->execute([$slotId]);
            $slot = $lock->fetch();

            $onLeave = false;
            if ($slot && !empty($slot['leave_start']) && !empty($slot['leave_end'])) {
                if ($slot['slot_date'] >= $slot['leave_start'] && $slot['slot_date'] <= $slot['leave_end']) {
                    $onLeave = true;
                }
            }
            if (!$onLeave && $slot) {
                $chkLeave = $pdo->prepare("
                    SELECT 1 FROM trainer_leave_requests
                    WHERE trainer_id = ? AND status = 'approved' AND ? BETWEEN start_date AND end_date
                    LIMIT 1
                ");
                $chkLeave->execute([$slot['trainer_id'], $slot['slot_date']]);
                if ($chkLeave->fetch()) {
                    $onLeave = true;
                }
            }

            if (!$slot) {
                $errors[] = 'That slot does not exist.';
            } elseif ($slot['status'] !== 'available') {
                $errors[] = 'That slot was just taken. Choose another.';
            } elseif ($slot['trainer_status'] === 'inactive' || $onLeave) {
                $errors[] = 'That trainer is not available.';
            } elseif ($slot['slot_date'] < date('Y-m-d')) {
                $errors[] = 'Cannot book a past slot.';
            } else {
                $bookingAccess = memberCanBookTrainer($pdo, $memberId);
                if (!$bookingAccess['ok']) {
                    $errors[] = $bookingAccess['error'];
                } else {
                $pdo->prepare("
                    INSERT INTO bookings (member_id, trainer_id, slot_id, booking_status)
                    VALUES (?, ?, ?, 'pending')
                ")->execute([$memberId, $slot['trainer_id'], $slotId]);

                $pdo->prepare("UPDATE trainer_slots SET status = 'booked' WHERE slot_id = ?")
                    ->execute([$slotId]);

                $pdo->commit();
                $success = 'Booking requested. Waiting for gym confirmation.';

                $slots = $pdo->query("
                    SELECT ts.slot_id, ts.slot_date, ts.start_time, ts.end_time, t.full_name AS trainer_name
                    FROM trainer_slots ts
                    JOIN trainers t ON t.trainer_id = ts.trainer_id
                    WHERE ts.status = 'available'
                      AND t.status != 'inactive'
                      AND (t.leave_start IS NULL OR t.leave_end IS NULL OR ts.slot_date NOT BETWEEN t.leave_start AND t.leave_end)
                      AND NOT EXISTS (
                          SELECT 1 FROM trainer_leave_requests tlr
                          WHERE tlr.trainer_id = t.trainer_id
                            AND tlr.status = 'approved'
                            AND ts.slot_date BETWEEN tlr.start_date AND tlr.end_date
                      )
                      AND ts.slot_date >= CURDATE()
                    ORDER BY ts.slot_date, ts.start_time
                ")->fetchAll();
                }
            }

            if ($errors && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not create booking. Please try again.';
        }
    }
}

// Member bookings list
$list = $pdo->prepare("
    SELECT b.booking_status, t.full_name AS trainer_name,
           ts.slot_date, ts.start_time, ts.end_time
    FROM bookings b
    JOIN trainers t ON t.trainer_id = b.trainer_id
    JOIN trainer_slots ts ON ts.slot_id = b.slot_id
    WHERE b.member_id = ?
    ORDER BY ts.slot_date DESC, ts.start_time DESC
    LIMIT 50
");
$list->execute([$memberId]);
$rows = $list->fetchAll();

$pageTitle = 'My Bookings';
require ROOT_PATH . '/includes/user_header.php';
?>

<h1 class="h4 mb-3">My Bookings</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card p-3 mb-4">
    <h2 class="h6 mb-3">Request a trainer session</h2>

    <?php if (!$hasMembership): ?>
        <div class="alert alert-info mb-0">You need an active membership to book a trainer. Contact the front desk or apply for a plan.</div>
    <?php elseif (!$canBookTrainer): ?>
        <div class="alert alert-info mb-0"><?= htmlspecialchars(trainerBookingUpgradeMessage()) ?></div>
    <?php elseif (!$slots): ?>
        <p class="text-muted mb-0">No available slots right now. Try again later.</p>
    <?php else: ?>
        <form method="POST" class="row g-2 align-items-end">
            <?= csrfField() ?>
            <div class="col-md-8">
                <label class="form-label">Available slot</label>
                <select name="slot_id" class="form-select" required>
                    <option value="">-- Choose --</option>
                    <?php foreach ($slots as $s): ?>
                        <option value="<?= (int)$s['slot_id'] ?>">
                            <?= htmlspecialchars($s['trainer_name']) ?>
                            — <?= htmlspecialchars($s['slot_date']) ?>
                            <?= htmlspecialchars(substr($s['start_time'], 0, 5)) ?>–<?= htmlspecialchars(substr($s['end_time'], 0, 5)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-dark w-100">Request booking</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header bg-white border-0 fw-semibold pt-3">Your bookings</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Trainer</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="text-muted">No bookings yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['trainer_name']) ?></td>
                    <td><?= htmlspecialchars($r['slot_date']) ?></td>
                    <td>
                        <?= htmlspecialchars(substr($r['start_time'], 0, 5)) ?>
                        –
                        <?= htmlspecialchars(substr($r['end_time'], 0, 5)) ?>
                    </td>
                    <td><?= htmlspecialchars(ucfirst($r['booking_status'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>
