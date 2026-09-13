<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireTrainer();

$trainerId   = (int) ($_SESSION['trainer_id'] ?? 0);
$trainerName = $_SESSION['trainer_name'] ?? 'Trainer';

// 1. Count of today's slots for this trainer
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM trainer_slots 
    WHERE trainer_id = ? AND slot_date = CURDATE()
");
$stmt->execute([$trainerId]);
$todaySlotsCount = (int) $stmt->fetchColumn();

// 2. Count of upcoming available slots (next 7 days: today through today + 7 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM trainer_slots 
    WHERE trainer_id = ? 
      AND status = 'available' 
      AND slot_date >= CURDATE() 
      AND slot_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$trainerId]);
$upcomingAvailableCount = (int) $stmt->fetchColumn();

// 3. Count of booked slots today
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM trainer_slots 
    WHERE trainer_id = ? 
      AND slot_date = CURDATE() 
      AND status = 'booked'
");
$stmt->execute([$trainerId]);
$todayBookedCount = (int) $stmt->fetchColumn();

// 4. Leave status: check if any approved leave covers today from trainer_leave_requests OR trainers.leave_start/leave_end
$stmt = $pdo->prepare("
    SELECT leave_id, start_date, end_date, reason 
    FROM trainer_leave_requests 
    WHERE trainer_id = ? 
      AND status = 'approved' 
      AND CURDATE() BETWEEN start_date AND end_date 
    LIMIT 1
");
$stmt->execute([$trainerId]);
$approvedLeave = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT status, leave_start, leave_end 
    FROM trainers 
    WHERE trainer_id = ?
");
$stmt->execute([$trainerId]);
$trainerProfile = $stmt->fetch(PDO::FETCH_ASSOC);

$isOnLeave = false;
$leaveStatusText = 'Active';
$leaveBadgeClass = 'bg-success';
$leaveContext = 'No active leave for today';

if ($approvedLeave) {
    $isOnLeave = true;
    $leaveStatusText = 'On leave';
    $leaveBadgeClass = 'bg-warning text-dark';
    $leaveContext = date('M d', strtotime($approvedLeave['start_date'])) . ' - ' . date('M d, Y', strtotime($approvedLeave['end_date']));
} elseif (
    !empty($trainerProfile['leave_start']) 
    && !empty($trainerProfile['leave_end']) 
    && date('Y-m-d') >= $trainerProfile['leave_start'] 
    && date('Y-m-d') <= $trainerProfile['leave_end']
) {
    $isOnLeave = true;
    $leaveStatusText = 'On leave';
    $leaveBadgeClass = 'bg-warning text-dark';
    $leaveContext = date('M d', strtotime($trainerProfile['leave_start'])) . ' - ' . date('M d, Y', strtotime($trainerProfile['leave_end']));
} elseif (($trainerProfile['status'] ?? '') === 'on_leave') {
    $isOnLeave = true;
    $leaveStatusText = 'On leave';
    $leaveBadgeClass = 'bg-warning text-dark';
    $leaveContext = 'Trainer account marked on leave';
}

// 5. Today's slot schedule preview (read-only)
$stmt = $pdo->prepare("
    SELECT ts.slot_id, ts.start_time, ts.end_time, ts.status,
           b.booking_id, m.full_name AS member_name, m.phone AS member_phone
    FROM trainer_slots ts
    LEFT JOIN bookings b ON ts.slot_id = b.slot_id AND b.booking_status = 'confirmed'
    LEFT JOIN members m ON b.member_id = m.member_id
    WHERE ts.trainer_id = ? AND ts.slot_date = CURDATE()
    ORDER BY ts.start_time ASC
");
$stmt->execute([$trainerId]);
$todaySchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Trainer Dashboard';
require_once ROOT_PATH . '/includes/trainer_header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">Welcome back, <?= htmlspecialchars($trainerName) ?>!</h2>
        <p class="text-muted mb-0">Here is your daily training schedule and availability overview.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-dark border px-3 py-2 shadow-sm">
            <i class="bi bi-calendar-event me-1 text-primary"></i> Today: <?= date('F j, Y') ?>
        </span>
    </div>
</div>

<?php if ($isOnLeave): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0"></i>
        <div>
            <div class="fw-bold">You are marked as On Leave today</div>
            <div class="small"><?= htmlspecialchars($leaveContext) ?></div>
        </div>
    </div>
<?php endif; ?>

<!-- 4 Dashboard Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-center">
                <span class="kpi-label">Today's Slots</span>
                <span class="kpi-icon"><i class="bi bi-clock-history"></i></span>
            </div>
            <div class="kpi-value"><?= number_format($todaySlotsCount) ?></div>
            <div class="kpi-context">Total slots set for today</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-center">
                <span class="kpi-label">Booked Today</span>
                <span class="kpi-icon tone-success"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="kpi-value"><?= number_format($todayBookedCount) ?></div>
            <div class="kpi-context">Client sessions booked today</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-center">
                <span class="kpi-label">Upcoming Available</span>
                <span class="kpi-icon tone-info"><i class="bi bi-calendar3"></i></span>
            </div>
            <div class="kpi-value"><?= number_format($upcomingAvailableCount) ?></div>
            <div class="kpi-context">Open slots in next 7 days</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-center">
                <span class="kpi-label">Leave Status</span>
                <span class="kpi-icon tone-warning"><i class="bi bi-calendar2-x"></i></span>
            </div>
            <div class="kpi-value fs-5">
                <span class="badge <?= $leaveBadgeClass ?>"><?= htmlspecialchars($leaveStatusText) ?></span>
            </div>
            <div class="kpi-context"><?= htmlspecialchars($leaveContext) ?></div>
        </div>
    </div>
</div>

<!-- Today's Schedule Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-calendar-check me-2"></i>Today's Schedule</span>
        <a href="<?= BASE_URL ?>/trainer/slots.php" class="btn btn-sm btn-outline-dark">
            View All Slots <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($todaySchedule)): ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Time Window</th>
                            <th>Status</th>
                            <th>Booked Member</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todaySchedule as $slot): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    <?= date('g:i A', strtotime($slot['start_time'])) ?> - <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                </td>
                                <td>
                                    <?php if ($slot['status'] === 'completed'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Completed</span>
                                    <?php elseif ($slot['status'] === 'no_show'): ?>
                                        <span class="badge bg-warning text-dark">No-show</span>
                                    <?php elseif ($slot['status'] === 'booked'): ?>
                                        <span class="badge bg-danger">Booked</span>
                                    <?php elseif ($slot['status'] === 'cancelled'): ?>
                                        <span class="badge bg-secondary">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($slot['member_name']) ? htmlspecialchars($slot['member_name']) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td>
                                    <?= !empty($slot['member_phone']) ? htmlspecialchars($slot['member_phone']) : '<span class="text-muted">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <div class="fw-semibold">No slots yet. Create one.</div>
                <div class="text-muted small mt-1">You have no training slots scheduled for today.</div>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/trainer/slots-add.php" class="btn btn-sm btn-dark">
                        <i class="bi bi-plus-lg me-1"></i> Save Slot
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/trainer_footer.php'; ?>
