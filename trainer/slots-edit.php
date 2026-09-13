<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireTrainer();

$trainerId = (int) $_SESSION['trainer_id'];
$slotId    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// 1. Fetch slot with ownership check (IDOR protection)
$stmt = $pdo->prepare("
    SELECT * 
    FROM trainer_slots 
    WHERE slot_id = ? AND trainer_id = ?
");
$stmt->execute([$slotId, $trainerId]);
$slot = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slot) {
    header('Location: ' . BASE_URL . '/trainer/slots.php?msg=notfound');
    exit;
}

// 2. Only allow editing if status is available
if ($slot['status'] !== 'available') {
    header('Location: ' . BASE_URL . '/trainer/slots.php?msg=not_editable');
    exit;
}

// 3. Check if any active booking exists on this slot
$bkStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM bookings 
    WHERE slot_id = ? AND booking_status IN ('pending', 'confirmed')
");
$bkStmt->execute([$slotId]);
if ($bkStmt->fetchColumn() > 0) {
    header('Location: ' . BASE_URL . '/trainer/slots.php?msg=not_editable');
    exit;
}

$errors = [];
$slotDate  = $slot['slot_date'];
$startTime = substr($slot['start_time'], 0, 5);
$endTime   = substr($slot['end_time'], 0, 5);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $slotDate  = trim($_POST['slot_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime   = trim($_POST['end_time'] ?? '');

    // Date validation
    if ($slotDate === '') {
        $errors[] = 'Slot date is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $slotDate)) {
        $errors[] = 'Please provide a valid date in YYYY-MM-DD format.';
    } elseif ($slotDate < date('Y-m-d')) {
        $errors[] = 'Slot date cannot be in the past.';
    }

    // Time validation
    if ($startTime === '' || $endTime === '') {
        $errors[] = 'Both start time and end time are required.';
    } else {
        $startTs = strtotime("2000-01-01 " . $startTime);
        $endTs   = strtotime("2000-01-01 " . $endTime);

        if ($startTs === false || $endTs === false) {
            $errors[] = 'Please provide valid start and end times.';
        } elseif ($endTs <= $startTs) {
            $errors[] = 'End time must be after start time.';
        } else {
            $durationMinutes = ($endTs - $startTs) / 60;
            if ($durationMinutes > 240) {
                $errors[] = 'Slot duration cannot exceed 4 hours.';
            } elseif ($durationMinutes < 15) {
                $errors[] = 'Slot duration must be at least 15 minutes.';
            }
        }
    }

    // Leave check
    if (empty($errors)) {
        $leaveStmt = $pdo->prepare("
            SELECT start_date, end_date 
            FROM trainer_leave_requests 
            WHERE trainer_id = ? 
              AND status = 'approved' 
              AND ? BETWEEN start_date AND end_date
            LIMIT 1
        ");
        $leaveStmt->execute([$trainerId, $slotDate]);
        $approvedLeave = $leaveStmt->fetch(PDO::FETCH_ASSOC);

        $tStmt = $pdo->prepare("
            SELECT leave_start, leave_end, status 
            FROM trainers 
            WHERE trainer_id = ?
        ");
        $tStmt->execute([$trainerId]);
        $tProfile = $tStmt->fetch(PDO::FETCH_ASSOC);

        if ($approvedLeave) {
            $errors[] = 'Cannot reschedule slot: you have approved leave from ' 
                . formatDate($approvedLeave['start_date']) . ' to ' 
                . formatDate($approvedLeave['end_date']) . '.';
        } elseif (
            !empty($tProfile['leave_start']) 
            && !empty($tProfile['leave_end']) 
            && $slotDate >= $tProfile['leave_start'] 
            && $slotDate <= $tProfile['leave_end']
        ) {
            $errors[] = 'Cannot reschedule slot: you are on scheduled leave from ' 
                . formatDate($tProfile['leave_start']) . ' to ' 
                . formatDate($tProfile['leave_end']) . '.';
        }
    }

    // Overlap check (exclude current slot itself)
    if (empty($errors)) {
        $overlapStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM trainer_slots 
            WHERE trainer_id = ? 
              AND slot_date = ? 
              AND slot_id != ?
              AND status != 'cancelled' 
              AND start_time < ? 
              AND end_time > ?
        ");
        $overlapStmt->execute([$trainerId, $slotDate, $slotId, $endTime, $startTime]);
        if ($overlapStmt->fetchColumn() > 0) {
            $errors[] = 'This updated time overlaps with another slot already scheduled on that date.';
        }
    }

    // Update slot
    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE trainer_slots 
            SET slot_date = ?, start_time = ?, end_time = ? 
            WHERE slot_id = ? AND trainer_id = ? AND status = 'available'
        ");
        $update->execute([$slotDate, $startTime, $endTime, $slotId, $trainerId]);

        header('Location: ' . BASE_URL . '/trainer/slots.php?msg=updated');
        exit;
    }
}

$pageTitle = 'Edit Training Slot';
require_once ROOT_PATH . '/includes/trainer_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h4 fw-bold mb-0">Edit Slot</h2>
            <a href="<?= BASE_URL ?>/trainer/slots.php" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-arrow-left me-1"></i> Back to Slots
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mb-4">
                <div class="fw-semibold mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>Please fix the following errors:</span>
                </div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $slotId ?>">

                    <div class="mb-3">
                        <label for="slot_date" class="form-label">Slot Date <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-calendar-event"></i>
                            <input type="date" name="slot_date" id="slot_date" class="form-control" 
                                   value="<?= htmlspecialchars($slotDate) ?>" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <div class="input-icon">
                                <i class="bi bi-clock"></i>
                                <input type="time" name="start_time" id="start_time" class="form-control" 
                                       value="<?= htmlspecialchars($startTime) ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                            <div class="input-icon">
                                <i class="bi bi-clock-history"></i>
                                <input type="time" name="end_time" id="end_time" class="form-control" 
                                       value="<?= htmlspecialchars($endTime) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>/trainer/slots.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dark fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Slot
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/trainer_footer.php'; ?>
