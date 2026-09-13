<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireTrainer();

$trainerId = (int) $_SESSION['trainer_id'];

// Filter selection (default: today)
$filter = $_GET['filter'] ?? 'today';
if (!in_array($filter, ['today', 'upcoming', 'past', 'all'])) {
    $filter = 'today';
}

// Flash messages
$messages = [
    'completed'          => ['type' => 'success', 'text' => 'Session marked as completed.'],
    'noshow'             => ['type' => 'warning', 'text' => 'Member marked as no-show.'],
    'notfound'           => ['type' => 'danger',  'text' => 'Session not found or access denied.'],
    'invalid_transition' => ['type' => 'danger',  'text' => 'This session is already finalized and cannot be modified.'],
    'noshow_not_allowed' => ['type' => 'danger',  'text' => 'Only booked member sessions can be marked as no-show.'],
    'invalid_status'     => ['type' => 'danger',  'text' => 'Invalid attendance action requested.'],
];
$msgKey = $_GET['msg'] ?? '';
$flash  = $messages[$msgKey] ?? null;

// Handle Attendance POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $slotId    = (int) ($_POST['slot_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    $note      = trim($_POST['note'] ?? '');

    // 1. IDOR Check: Slot must belong to logged-in trainer
    $stmt = $pdo->prepare("
        SELECT ts.slot_id, ts.status, ts.slot_date, b.booking_id, b.booking_status
        FROM trainer_slots ts
        LEFT JOIN bookings b ON b.slot_id = ts.slot_id AND b.booking_status IN ('pending', 'confirmed')
        WHERE ts.slot_id = ? AND ts.trainer_id = ?
    ");
    $stmt->execute([$slotId, $trainerId]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot) {
        header('Location: ' . BASE_URL . '/trainer/attendance.php?filter=' . urlencode($filter) . '&msg=notfound');
        exit;
    }

    $currentStatus = $slot['status'];

    // 2. Validate Transitions
    if ($newStatus === 'completed') {
        // available or booked can be marked completed
        if (!in_array($currentStatus, ['available', 'booked'])) {
            header('Location: ' . BASE_URL . '/trainer/attendance.php?filter=' . urlencode($filter) . '&msg=invalid_transition');
            exit;
        }

        $updSlot = $pdo->prepare("
            UPDATE trainer_slots 
            SET status = 'completed', attendance_note = ? 
            WHERE slot_id = ? AND trainer_id = ?
        ");
        $updSlot->execute([$note !== '' ? $note : 'Session completed', $slotId, $trainerId]);

        if (!empty($slot['booking_id'])) {
            $updBk = $pdo->prepare("
                UPDATE bookings 
                SET booking_status = 'completed' 
                WHERE slot_id = ? AND booking_status IN ('pending', 'confirmed')
            ");
            $updBk->execute([$slotId]);
        }

        header('Location: ' . BASE_URL . '/trainer/attendance.php?filter=' . urlencode($filter) . '&msg=completed');
        exit;

    } elseif ($newStatus === 'no_show') {
        // Only booked slots can be marked no_show
        if ($currentStatus !== 'booked' || empty($slot['booking_id'])) {
            header('Location: ' . BASE_URL . '/trainer/attendance.php?filter=' . urlencode($filter) . '&msg=noshow_not_allowed');
            exit;
        }

        $updSlot = $pdo->prepare("
            UPDATE trainer_slots 
            SET status = 'no_show', attendance_note = ? 
            WHERE slot_id = ? AND trainer_id = ?
        ");
        $updSlot->execute([$note !== '' ? $note : 'Member did not attend', $slotId, $trainerId]);

        $updBk = $pdo->prepare("
            UPDATE bookings 
            SET booking_status = 'no_show' 
            WHERE slot_id = ? AND booking_status IN ('pending', 'confirmed')
        ");
        $updBk->execute([$slotId]);

        header('Location: ' . BASE_URL . '/trainer/attendance.php?filter=' . urlencode($filter) . '&msg=noshow');
        exit;

    } else {
        header('Location: ' . BASE_URL . '/trainer/attendance.php?filter=' . urlencode($filter) . '&msg=invalid_status');
        exit;
    }
}

// Build Query based on filter
$where  = "ts.trainer_id = ?";
$params = [$trainerId];

if ($filter === 'today') {
    $where .= " AND ts.slot_date = CURDATE()";
    $order  = "ts.start_time ASC";
} elseif ($filter === 'upcoming') {
    $where .= " AND ts.slot_date > CURDATE()";
    $order  = "ts.slot_date ASC, ts.start_time ASC";
} elseif ($filter === 'past') {
    $where .= " AND ts.slot_date < CURDATE()";
    $order  = "ts.slot_date DESC, ts.start_time DESC";
} else {
    $order  = "ts.slot_date DESC, ts.start_time DESC";
}

$stmt = $pdo->prepare("
    SELECT ts.*,
           b.booking_id,
           b.booking_status,
           m.member_id,
           m.full_name AS member_name,
           m.phone AS member_phone,
           m.email AS member_email
    FROM trainer_slots ts
    LEFT JOIN bookings b ON b.slot_id = ts.slot_id
    LEFT JOIN members m ON b.member_id = m.member_id
    WHERE $where
    ORDER BY $order
");
$stmt->execute($params);
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for filter badges
$countStmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN slot_date = CURDATE() THEN 1 END) AS today_count,
        COUNT(CASE WHEN slot_date > CURDATE() THEN 1 END) AS upcoming_count,
        COUNT(CASE WHEN slot_date < CURDATE() THEN 1 END) AS past_count,
        COUNT(*) AS all_count
    FROM trainer_slots
    WHERE trainer_id = ?
");
$countStmt->execute([$trainerId]);
$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Mark Attendance';
require_once ROOT_PATH . '/includes/trainer_header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">Mark Attendance</h2>
        <p class="text-muted mb-0">Record member attendance and mark finished training sessions.</p>
    </div>
    <div class="badge bg-white text-dark border px-3 py-2 shadow-sm">
        <i class="bi bi-clock-history me-1 text-primary"></i> <?= date('l, F j, Y') ?>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
            <span><?= htmlspecialchars($flash['text']) ?></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="btn-group" role="group" aria-label="Attendance filters">
        <a href="<?= BASE_URL ?>/trainer/attendance.php?filter=today" 
           class="btn btn-sm <?= $filter === 'today' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Today's Sessions
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['today_count'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/attendance.php?filter=past" 
           class="btn btn-sm <?= $filter === 'past' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Past Sessions
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['past_count'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/attendance.php?filter=upcoming" 
           class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Upcoming
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['upcoming_count'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/attendance.php?filter=all" 
           class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">
            All Sessions
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['all_count'] ?? 0) ?></span>
        </a>
    </div>

    <span class="text-muted small">
        Showing <?= count($slots) ?> session<?= count($slots) === 1 ? '' : 's' ?>
    </span>
</div>

<!-- Attendance Sessions Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time Window</th>
                    <th>Status</th>
                    <th>Booked Member</th>
                    <th>Attendance Notes</th>
                    <th class="text-end">Record Attendance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slots)): ?>
                    <tr>
                        <td colspan="6" class="text-muted text-center py-5">
                            <i class="bi bi-clock-history fs-2 d-block mb-2 text-faint"></i>
                            <div class="fw-semibold">
                                <?= $filter === 'today' ? 'No sessions to mark today.' : 'No sessions found.' ?>
                            </div>
                            <div class="small mt-1 text-muted">
                                <?php if ($filter === 'today'): ?>
                                    You have no training sessions scheduled for today.
                                <?php elseif ($filter === 'past'): ?>
                                    No past sessions on record.
                                <?php else: ?>
                                    No scheduled sessions match this filter.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($slots as $s): 
                        $startTime = strtotime($s['start_time']);
                        $endTime   = strtotime($s['end_time']);
                        $isToday   = ($s['slot_date'] === date('Y-m-d'));
                        $hasMember = !empty($s['member_name']);
                        $status    = $s['status'];
                    ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?= formatDate($s['slot_date']) ?>
                                </div>
                                <?php if ($isToday): ?>
                                    <span class="badge bg-info text-white" style="font-size: 0.68rem;">Today</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-medium text-dark">
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    <?= date('g:i A', $startTime) ?> – <?= date('g:i A', $endTime) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($status === 'completed'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Completed
                                    </span>
                                <?php elseif ($status === 'no_show'): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-person-x me-1"></i>No-show
                                    </span>
                                <?php elseif ($status === 'booked'): ?>
                                    <span class="badge bg-danger">Booked</span>
                                <?php elseif ($status === 'available'): ?>
                                    <span class="badge bg-light text-dark border">Available</span>
                                <?php elseif ($status === 'cancelled'): ?>
                                    <span class="badge bg-secondary">Cancelled</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars(ucfirst($status)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasMember): ?>
                                    <div>
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-person-fill text-primary me-1"></i><?= htmlspecialchars($s['member_name']) ?>
                                        </div>
                                        <?php if (!empty($s['member_phone'])): ?>
                                            <div class="text-muted small">
                                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['member_phone']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($status === 'available'): ?>
                                    <span class="text-muted small">Open slot (Unbooked)</span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['attendance_note'])): ?>
                                    <span class="text-dark small"><i class="bi bi-card-text me-1 text-muted"></i><?= htmlspecialchars($s['attendance_note']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <?php if ($status === 'booked'): ?>
                                        <!-- Mark Completed Button -->
                                        <form method="POST" data-confirm="Mark this session with <?= htmlspecialchars($s['member_name'] ?? 'member') ?> as Completed?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="slot_id" value="<?= (int) $s['slot_id'] ?>">
                                            <input type="hidden" name="new_status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-success" title="Mark Session Completed">
                                                <i class="bi bi-check2 me-1"></i> Mark Completed
                                            </button>
                                        </form>

                                        <!-- Mark No-Show Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                data-bs-toggle="modal" data-bs-target="#noShowModal<?= (int) $s['slot_id'] ?>" 
                                                title="Mark Member as No-show">
                                            <i class="bi bi-person-x me-1"></i> Mark No-show
                                        </button>

                                        <!-- Modal for No-Show Note -->
                                        <div class="modal fade text-start" id="noShowModal<?= (int) $s['slot_id'] ?>" tabindex="-1" aria-labelledby="noShowLabel<?= (int) $s['slot_id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="slot_id" value="<?= (int) $s['slot_id'] ?>">
                                                        <input type="hidden" name="new_status" value="no_show">

                                                        <div class="modal-header">
                                                            <h5 class="modal-title h6 fw-bold" id="noShowLabel<?= (int) $s['slot_id'] ?>">
                                                                <i class="bi bi-person-x-fill text-warning me-2"></i>Mark Member as No-show
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="small text-muted mb-3">
                                                                Member <strong><?= htmlspecialchars($s['member_name'] ?? 'Client') ?></strong> did not attend the session on 
                                                                <strong><?= formatDate($s['slot_date']) ?></strong> at <strong><?= date('g:i A', $startTime) ?></strong>.
                                                            </p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Optional Note / Reason</label>
                                                                <input type="text" name="note" class="form-control" 
                                                                       placeholder="e.g. Unreachable, cancelled last minute, sick...">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                                                                <i class="bi bi-check-lg me-1"></i> Confirm No-show
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    <?php elseif ($status === 'available'): ?>
                                        <!-- Available slot can be completed (duty completed) -->
                                        <form method="POST" data-confirm="Mark this available slot as completed?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="slot_id" value="<?= (int) $s['slot_id'] ?>">
                                            <input type="hidden" name="new_status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed">
                                                <i class="bi bi-check2 me-1"></i> Mark Completed
                                            </button>
                                        </form>

                                    <?php elseif ($status === 'completed'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check2-all me-1"></i> Completed
                                        </span>

                                    <?php elseif ($status === 'no_show'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-dash-circle me-1"></i> No-show
                                        </span>

                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/trainer_footer.php'; ?>
