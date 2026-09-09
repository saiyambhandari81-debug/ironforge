<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$errors = [];
$old = [
    'member_id' => '',
    'slot_id'   => '',
];

// Members with active, unexpired membership
$members = $pdo->query("
    SELECT m.member_id, m.full_name
    FROM members m
    WHERE m.status = 'active'
      AND EXISTS (
          SELECT 1 FROM memberships ms
          WHERE ms.member_id = m.member_id
            AND ms.status = 'active'
            AND ms.expiry_date >= CURDATE()
      )
    ORDER BY m.full_name
")->fetchAll();

// Available future slots
$slots = $pdo->query("
    SELECT ts.slot_id, ts.slot_date, ts.start_time, ts.end_time,
           t.full_name AS trainer_name, t.trainer_id
    FROM trainer_slots ts
    JOIN trainers t ON t.trainer_id = ts.trainer_id
    WHERE ts.status = 'available'
      AND t.status = 'active'
      AND ts.slot_date >= CURDATE()
    ORDER BY ts.slot_date, ts.start_time
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['member_id'] = trim($_POST['member_id'] ?? '');
    $old['slot_id']   = trim($_POST['slot_id'] ?? '');

    $memberId = (int) $old['member_id'];
    $slotId   = (int) $old['slot_id'];

    // ===== VALIDATION =====
    if ($memberId <= 0) {
        $errors[] = 'Please select a member.';
    } else {
        $m = $pdo->prepare("SELECT status FROM members WHERE member_id = ?");
        $m->execute([$memberId]);
        $member = $m->fetch();
        if (!$member || $member['status'] !== 'active') {
            $errors[] = 'Selected member is not active.';
        } else {
            $ms = $pdo->prepare("
                SELECT membership_id FROM memberships
                WHERE member_id = ? AND status = 'active' AND expiry_date >= CURDATE()
                LIMIT 1
            ");
            $ms->execute([$memberId]);
            if (!$ms->fetch()) {
                $errors[] = 'Member has no active membership. Cannot book.';
            }
        }
    }

    if ($slotId <= 0) {
        $errors[] = 'Please select a trainer slot.';
    } else {
        $s = $pdo->prepare("
            SELECT ts.*, t.status AS trainer_status
            FROM trainer_slots ts
            JOIN trainers t ON t.trainer_id = ts.trainer_id
            WHERE ts.slot_id = ?
        ");
        $s->execute([$slotId]);
        $slot = $s->fetch();

        if (!$slot) {
            $errors[] = 'Selected slot does not exist.';
        } elseif ($slot['status'] !== 'available') {
            $errors[] = 'That slot was just taken. Choose another.';
        } elseif ($slot['trainer_status'] !== 'active') {
            $errors[] = 'That trainer is not active.';
        } elseif ($slot['slot_date'] < date('Y-m-d')) {
            $errors[] = 'Cannot book a slot in the past.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock slot row
            $lock = $pdo->prepare("SELECT status, trainer_id FROM trainer_slots WHERE slot_id = ? FOR UPDATE");
            $lock->execute([$slotId]);
            $locked = $lock->fetch();

            if (!$locked || $locked['status'] !== 'available') {
                $pdo->rollBack();
                $errors[] = 'That slot was just taken. Choose another.';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO bookings (member_id, trainer_id, slot_id, booking_status)
                    VALUES (?, ?, ?, 'pending')
                ");
                $ins->execute([$memberId, $locked['trainer_id'], $slotId]);

                $upd = $pdo->prepare("UPDATE trainer_slots SET status = 'booked' WHERE slot_id = ?");
                $upd->execute([$slotId]);

                $pdo->commit();
                header('Location: ' . BASE_URL . '/admin/bookings/?msg=added');
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not save booking. Please try again.';
        }
    }
}

$pageTitle = 'New Booking';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card p-4" style="max-width: 640px;">
    <h5 class="mb-3">New Booking</h5>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$members): ?>
        <div class="alert alert-warning">No members with an active membership. Add a membership first.</div>
    <?php elseif (!$slots): ?>
        <div class="alert alert-warning">No available future slots. Add trainer slots first.</div>
    <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Member *</label>
                <select name="member_id" class="form-select" required>
                    <option value="">-- Select member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int)$m['member_id'] ?>" <?= (string)$old['member_id'] === (string)$m['member_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Trainer slot *</label>
                <select name="slot_id" class="form-select" required>
                    <option value="">-- Select slot --</option>
                    <?php foreach ($slots as $s): ?>
                        <option value="<?= (int)$s['slot_id'] ?>" <?= (string)$old['slot_id'] === (string)$s['slot_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['trainer_name']) ?>
                            — <?= htmlspecialchars($s['slot_date']) ?>
                            <?= htmlspecialchars(substr($s['start_time'], 0, 5)) ?>–<?= htmlspecialchars(substr($s['end_time'], 0, 5)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-dark">Create Booking</button>
            <a href="<?= BASE_URL ?>/admin/bookings/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>