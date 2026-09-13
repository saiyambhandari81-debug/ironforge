<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];

$msStmt = $pdo->prepare("
    SELECT ms.start_date, ms.expiry_date, ms.status, p.plan_name
    FROM memberships ms
    JOIN plans p ON p.plan_id = ms.plan_id
    WHERE ms.member_id = ?
    ORDER BY ms.start_date DESC
    LIMIT 1
");
$msStmt->execute([$memberId]);
$membership = $msStmt->fetch();

$attStmt = $pdo->prepare("
    SELECT check_in_time, check_out_time
    FROM attendance
    WHERE member_id = ? AND attendance_date = CURDATE()
    LIMIT 1
");
$attStmt->execute([$memberId]);
$today = $attStmt->fetch();

$payStmt = $pdo->prepare("
    SELECT amount, payment_date, payment_status
    FROM payments
    WHERE member_id = ?
    ORDER BY payment_date DESC
    LIMIT 5
");
$payStmt->execute([$memberId]);
$recentPayments = $payStmt->fetchAll();

$bookStmt = $pdo->prepare("
    SELECT b.booking_status, t.full_name AS trainer_name, ts.slot_date, ts.start_time
    FROM bookings b
    JOIN trainers t ON t.trainer_id = b.trainer_id
    JOIN trainer_slots ts ON ts.slot_id = b.slot_id
    WHERE b.member_id = ? AND ts.slot_date >= CURDATE()
    ORDER BY ts.slot_date, ts.start_time
    LIMIT 5
");
$bookStmt->execute([$memberId]);
$upcoming = $bookStmt->fetchAll();

$pageTitle = 'Dashboard';
require ROOT_PATH . '/includes/user_header.php';

// Calculate membership days remaining percentage for progress track
$daysRemainingPct = 0;
$daysRemainingText = '';
if ($membership && !empty($membership['expiry_date'])) {
    $todayTs = strtotime(date('Y-m-d'));
    $startTs = strtotime($membership['start_date']);
    $expiryTs = strtotime($membership['expiry_date']);
    $totalDays = max(1, ($expiryTs - $startTs) / 86400);
    $passedDays = max(0, ($todayTs - $startTs) / 86400);
    $daysRemaining = max(0, ceil(($expiryTs - $todayTs) / 86400));
    $daysRemainingPct = max(0, min(100, round((($totalDays - $passedDays) / $totalDays) * 100)));
    $daysRemainingText = $daysRemaining . ' days left';
}
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 fw-bold mb-1">Namaste, <?= htmlspecialchars($_SESSION['member_name']) ?>! 👋</h1>
        <p class="text-muted mb-0">Welcome back to your IronForge Gym dashboard.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/user/bookings.php" class="btn btn-accent"><i class="bi bi-calendar-plus me-1"></i> Book Session</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Membership Status Hero Card -->
    <div class="col-md-5 col-lg-4">
        <div class="plan-card d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="eyebrow"><i class="bi bi-shield-check me-1"></i> Active Membership</span>
                    <?php if ($membership): ?>
                        <span class="badge bg-<?= $membership['status'] === 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst(htmlspecialchars($membership['status'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($membership): ?>
                    <h2 class="plan-name"><?= htmlspecialchars($membership['plan_name']) ?></h2>
                    <div class="expiry-track">
                        <div class="expiry-fill <?= $daysRemainingPct < 20 ? 'is-low' : '' ?>" style="width: <?= $daysRemainingPct ?>%;"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small text-white-50 mt-1">
                        <span>Expires: <?= formatDate($membership['expiry_date']) ?></span>
                        <span class="fw-semibold text-white"><?= $daysRemainingText ?></span>
                    </div>
                <?php else: ?>
                    <h2 class="plan-name fs-4">No Active Membership</h2>
                    <p class="small text-white-50 mb-3">You currently do not have an active membership plan assigned.</p>
                    <a href="<?= BASE_URL ?>/user/membership.php" class="btn btn-sm btn-light">View Membership Plans</a>
                <?php endif; ?>
            </div>
            <div class="mt-4 pt-3 border-top border-white-subtle d-flex justify-content-between align-items-center">
                <span class="small text-white-50">Member ID: #<?= sprintf('%05d', $memberId) ?></span>
                <a href="<?= BASE_URL ?>/user/membership.php" class="text-white text-decoration-none small fw-semibold">Details <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Today's Attendance -->
    <div class="col-md-7 col-lg-4">
        <div class="card p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="kpi-label"><i class="bi bi-clock-history me-1"></i> Today's Attendance</span>
                    <span class="badge bg-<?= $today ? 'success' : 'secondary' ?>">
                        <?= $today ? 'Checked In' : 'Not Checked In' ?>
                    </span>
                </div>
                <?php if ($today): ?>
                    <div class="kpi-value text-success mb-2"><i class="bi bi-check-circle-fill me-2 fs-4"></i>Present</div>
                    <div class="p-3 bg-light rounded-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Check-in:</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($today['check_in_time'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Check-out:</span>
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($today['check_out_time'] ?? '—') ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="kpi-value text-muted mb-2"><i class="bi bi-geo-alt me-2 fs-4"></i>Absent</div>
                    <p class="small text-muted mb-0">Visit the gym desk to check in for your workout today.</p>
                <?php endif; ?>
            </div>
            <div class="mt-3 pt-2">
                <a href="<?= BASE_URL ?>/user/attendance.php" class="btn btn-sm btn-outline-dark w-100">View Attendance History</a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-12 col-lg-4">
        <div class="card p-4 h-100 d-flex flex-column justify-content-between">
            <div>
                <div class="kpi-label mb-3"><i class="bi bi-lightning-charge me-1"></i> Quick Actions</div>
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/user/bookings.php" class="btn btn-outline-dark text-start d-flex align-items-center justify-content-between p-3">
                        <div>
                            <div class="fw-semibold"><i class="bi bi-calendar-event me-2 text-primary"></i>Trainer Bookings</div>
                            <div class="text-muted small">Reserve a slot with your trainer</div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/user/payments.php" class="btn btn-outline-dark text-start d-flex align-items-center justify-content-between p-3">
                        <div>
                            <div class="fw-semibold"><i class="bi bi-credit-card me-2 text-success"></i>Payment History</div>
                            <div class="text-muted small">View receipts &amp; pending payments</div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-outline-dark text-start d-flex align-items-center justify-content-between p-3">
                        <div>
                            <div class="fw-semibold"><i class="bi bi-person me-2 text-warning"></i>Edit Profile</div>
                            <div class="text-muted small">Update your personal information</div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sessions & Payments Tables Grid -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-calendar-week me-2 text-primary"></i>Upcoming Trainer Sessions</div>
                <a href="<?= BASE_URL ?>/user/bookings.php" class="btn btn-sm btn-outline-dark">Bookings</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Trainer</th>
                            <th>Date &amp; Time</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$upcoming): ?>
                        <tr><td colspan="3" class="text-muted text-center">No upcoming bookings.</td></tr>
                    <?php else: foreach ($upcoming as $b): ?>
                        <tr>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($b['trainer_name']) ?></td>
                            <td class="small"><?= formatDate($b['slot_date']) ?> <span class="text-muted">@ <?= htmlspecialchars(substr($b['start_time'], 0, 5)) ?></span></td>
                            <td class="text-end">
                                <?php
                                    $st = strtolower($b['booking_status']);
                                    $badgeTone = 'bg-secondary';
                                    if ($st === 'confirmed' || $st === 'approved') $badgeTone = 'bg-success';
                                    elseif ($st === 'pending') $badgeTone = 'bg-warning';
                                    elseif ($st === 'cancelled' || $st === 'rejected') $badgeTone = 'bg-danger';
                                ?>
                                <span class="badge <?= $badgeTone ?>"><?= htmlspecialchars(ucfirst($b['booking_status'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-receipt me-2 text-success"></i>Recent Payments</div>
                <a href="<?= BASE_URL ?>/user/payments.php" class="btn btn-sm btn-outline-dark">All Payments</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentPayments): ?>
                        <tr><td colspan="3" class="text-muted text-center">No payments logged yet.</td></tr>
                    <?php else: foreach ($recentPayments as $p): ?>
                        <tr>
                            <td class="small text-muted"><?= formatDate($p['payment_date']) ?></td>
                            <td class="text-end num font-monospace fw-bold text-dark"><?= formatMoney($p['amount']) ?></td>
                            <td class="text-end">
                                <?php
                                    $pst = strtolower($p['payment_status']);
                                    $pBadge = $pst === 'completed' ? 'bg-success' : ($pst === 'pending' ? 'bg-warning' : 'bg-danger');
                                ?>
                                <span class="badge <?= $pBadge ?>"><?= htmlspecialchars(ucfirst($p['payment_status'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>