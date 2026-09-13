<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireTrainer();

$trainerId = (int) $_SESSION['trainer_id'];

// Flash messages
$messages = [
    'added'        => ['type' => 'success', 'text' => 'Slot saved successfully.'],
    'updated'      => ['type' => 'success', 'text' => 'Slot updated successfully.'],
    'cancelled'    => ['type' => 'warning', 'text' => 'Slot cancelled successfully.'],
    'deleted'      => ['type' => 'success', 'text' => 'Slot deleted successfully.'],
    'notfound'     => ['type' => 'danger',  'text' => 'Slot not found or access denied.'],
    'not_editable' => ['type' => 'danger',  'text' => 'This slot cannot be edited because it is already booked or cancelled.'],
];
$msgKey = $_GET['msg'] ?? '';
$flash  = $messages[$msgKey] ?? null;

// Filter selection
$filter = $_GET['filter'] ?? 'upcoming';
if (!in_array($filter, ['upcoming', 'today', 'all'])) {
    $filter = 'upcoming';
}

$where  = "ts.trainer_id = ?";
$params = [$trainerId];

if ($filter === 'upcoming') {
    $where .= " AND ts.slot_date >= CURDATE()";
    $order  = "ts.slot_date ASC, ts.start_time ASC";
} elseif ($filter === 'today') {
    $where .= " AND ts.slot_date = CURDATE()";
    $order  = "ts.start_time ASC";
} else {
    $order  = "ts.slot_date DESC, ts.start_time DESC";
}

$stmt = $pdo->prepare("
    SELECT ts.*,
           b.booking_id,
           b.booking_status,
           m.full_name AS member_name,
           m.phone AS member_phone
    FROM trainer_slots ts
    LEFT JOIN bookings b ON b.slot_id = ts.slot_id AND b.booking_status IN ('pending', 'confirmed')
    LEFT JOIN members m ON b.member_id = m.member_id
    WHERE $where
    ORDER BY $order
");
$stmt->execute($params);
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts for quick badges
$countStmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN slot_date >= CURDATE() AND status = 'available' THEN 1 END) AS upcoming_avail,
        COUNT(CASE WHEN slot_date = CURDATE() THEN 1 END) AS today_total,
        COUNT(*) AS all_total
    FROM trainer_slots
    WHERE trainer_id = ?
");
$countStmt->execute([$trainerId]);
$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'My Slots';
require_once ROOT_PATH . '/includes/trainer_header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">My Training Slots</h2>
        <p class="text-muted mb-0">Manage your working availability and scheduled training sessions.</p>
    </div>
    <a href="<?= BASE_URL ?>/trainer/slots-add.php" class="btn btn-dark d-inline-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i>
        <span>Add Slot</span>
    </a>
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
    <div class="btn-group" role="group" aria-label="Slot filters">
        <a href="<?= BASE_URL ?>/trainer/slots.php?filter=upcoming" 
           class="btn btn-sm <?= $filter === 'upcoming' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Upcoming
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['upcoming_avail'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/slots.php?filter=today" 
           class="btn btn-sm <?= $filter === 'today' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Today
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['today_total'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/slots.php?filter=all" 
           class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">
            All Slots
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['all_total'] ?? 0) ?></span>
        </a>
    </div>

    <span class="text-muted small">
        Showing <?= count($slots) ?> <?= $filter ?> slot<?= count($slots) === 1 ? '' : 's' ?>
    </span>
</div>

<!-- Slots Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time Window</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Booking Info</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slots)): ?>
                    <tr>
                        <td colspan="6" class="text-muted text-center py-5">
                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-faint"></i>
                            <div class="fw-semibold">No slots yet. Create one.</div>
                            <div class="small mt-1 text-muted">
                                <?php if ($filter === 'upcoming'): ?>
                                    You have no upcoming training slots scheduled.
                                <?php elseif ($filter === 'today'): ?>
                                    You have no training slots scheduled for today.
                                <?php else: ?>
                                    No slots on record.
                                <?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <a href="<?= BASE_URL ?>/trainer/slots-add.php" class="btn btn-sm btn-dark">
                                    <i class="bi bi-plus-lg me-1"></i> Save Slot
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($slots as $s): 
                        $startTime = strtotime($s['start_time']);
                        $endTime   = strtotime($s['end_time']);
                        $durationMin = max(0, round(($endTime - $startTime) / 60));
                        $hours = floor($durationMin / 60);
                        $mins  = $durationMin % 60;
                        $durationLabel = ($hours > 0 ? $hours . 'h ' : '') . ($mins > 0 ? $mins . 'm' : '');
                        if ($durationLabel === '') $durationLabel = $durationMin . 'm';

                        $isToday = ($s['slot_date'] === date('Y-m-d'));
                        $hasActiveBooking = !empty($s['booking_id']);
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
                                <span class="text-muted small">
                                    <?= htmlspecialchars($durationLabel) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($s['status'] === 'available'): ?>
                                    <span class="badge bg-success">Available</span>
                                <?php elseif ($s['status'] === 'booked'): ?>
                                    <span class="badge bg-danger">Booked</span>
                                <?php elseif ($s['status'] === 'completed'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Completed</span>
                                <?php elseif ($s['status'] === 'no_show'): ?>
                                    <span class="badge bg-warning text-dark">No-show</span>
                                <?php elseif ($s['status'] === 'cancelled'): ?>
                                    <span class="badge bg-secondary">Cancelled</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars(ucfirst($s['status'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasActiveBooking && !empty($s['member_name'])): ?>
                                    <div>
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-person me-1 text-primary"></i><?= htmlspecialchars($s['member_name']) ?>
                                        </div>
                                        <?php if (!empty($s['member_phone'])): ?>
                                            <div class="text-muted small">
                                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['member_phone']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($s['status'] === 'cancelled'): ?>
                                    <span class="text-muted small fst-italic">Slot cancelled</span>
                                <?php else: ?>
                                    <span class="text-muted small">No booking</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php if ($s['status'] === 'available' && !$hasActiveBooking): ?>
                                        <a href="<?= BASE_URL ?>/trainer/slots-edit.php?id=<?= (int) $s['slot_id'] ?>" 
                                           class="btn btn-sm btn-outline-dark" title="Edit slot">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>/trainer/slots-cancel.php" 
                                              data-confirm="Are you sure you want to cancel this available slot?" 
                                              class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="slot_id" value="<?= (int) $s['slot_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel slot">
                                                <i class="bi bi-x-circle me-1"></i> Cancel Slot
                                            </button>
                                        </form>
                                    <?php elseif ($s['status'] === 'booked'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>/trainer/slots-cancel.php" 
                                              data-confirm="This slot is booked by <?= htmlspecialchars($s['member_name'] ?? 'a member') ?>. Cancelling will cancel the session. Continue?" 
                                              class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="slot_id" value="<?= (int) $s['slot_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel booked slot">
                                                <i class="bi bi-x-circle me-1"></i> Cancel Slot
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">No action</span>
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
