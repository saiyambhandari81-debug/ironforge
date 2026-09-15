<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

// Auto-reactivate trainers whose approved leave has ended
autoReactivateTrainers($pdo);

// ---- Counts ----
$totalMembers       = (int) $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers      = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$expiredMemberships = (int) $pdo->query("SELECT COUNT(*) FROM memberships WHERE expiry_date < CURDATE()")->fetchColumn();
$totalTrainers      = (int) $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn();
$todayAttendance    = (int) $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();

// Pending breakdown counts
$pendingTrainerLeave = (int) $pdo->query("SELECT COUNT(*) FROM trainer_leave_requests WHERE status = 'pending'")->fetchColumn();
$pendingRefunds      = (int) $pdo->query("SELECT COUNT(*) FROM refunds WHERE status = 'pending'")->fetchColumn();
$pendingTransfers    = (int) $pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'pending'")->fetchColumn();
$pendingChanges      = (int) $pdo->query("SELECT COUNT(*) FROM change_requests WHERE status = 'pending'")->fetchColumn();
$pendingRequests     = $pendingTrainerLeave + $pendingRefunds + $pendingTransfers + $pendingChanges;

// ---- Finance ----
$totalRevenue  = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'completed'")->fetchColumn();
$totalExpenses = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
$netProfit     = $totalRevenue - $totalExpenses;

// ---- Recent ----
$recentPayments = $pdo->query("
    SELECT p.amount, p.payment_date, m.full_name
    FROM payments p
    JOIN members m ON p.member_id = m.member_id
    ORDER BY p.payment_date DESC
    LIMIT 5
")->fetchAll();

$recentMembers = $pdo->query("
    SELECT full_name, join_date
    FROM members
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Upcoming Expirations (Next 7 days)
$upcomingExpirations = $pdo->query("
    SELECT ms.membership_id, ms.expiry_date, m.member_id, m.full_name, m.phone, p.plan_name
    FROM memberships ms
    JOIN members m ON ms.member_id = m.member_id
    JOIN plans p ON ms.plan_id = p.plan_id
    WHERE ms.status = 'active'
      AND ms.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ms.expiry_date ASC
    LIMIT 10
")->fetchAll();

$pageTitle = 'Dashboard';
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- KPI Overview Grid -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="kpi-label">Total Members</span>
                <div class="kpi-icon tone-info"><i class="bi bi-people"></i></div>
            </div>
            <div class="kpi-value tnum"><?= number_format((int)$totalMembers) ?></div>
            <div class="kpi-context">Registered gym members</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="kpi-label">Active Members</span>
                <div class="kpi-icon tone-success"><i class="bi bi-person-check"></i></div>
            </div>
            <div class="kpi-value tnum text-success"><?= number_format((int)$activeMembers) ?></div>
            <div class="kpi-context">Currently active plans</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="kpi-label">Expired</span>
                <div class="kpi-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
            <div class="kpi-value tnum text-danger"><?= number_format((int)$expiredMemberships) ?></div>
            <div class="kpi-context">Plan renewal needed</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="kpi-label">Trainers</span>
                <div class="kpi-icon tone-info"><i class="bi bi-person-badge"></i></div>
            </div>
            <div class="kpi-value tnum"><?= number_format((int)$totalTrainers) ?></div>
            <div class="kpi-context">Available coaches</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="kpi-label">Today Attendance</span>
                <div class="kpi-icon tone-success"><i class="bi bi-clock-history"></i></div>
            </div>
            <div class="kpi-value tnum"><?= number_format((int)$todayAttendance) ?></div>
            <div class="kpi-context">Check-ins today</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="kpi-label">Pending Requests</span>
                <div class="kpi-icon tone-warning"><i class="bi bi-inbox"></i></div>
            </div>
            <div class="kpi-value tnum text-warning"><?= number_format((int)$pendingRequests) ?></div>
            <div class="kpi-context">Transfers, refunds, info, leave</div>
        </div>
    </div>
</div>

<!-- Pending Approvals & Requests Queue -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <div class="fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-warning fs-5"></i>
            <span>Pending Approvals &amp; Requests</span>
        </div>
        <span class="badge <?= $pendingRequests > 0 ? 'bg-danger' : 'bg-secondary' ?>">
            <?= $pendingRequests ?> total pending
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>/admin/trainer-leave.php?filter=pending" class="text-decoration-none">
                    <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-between hover-shadow">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-semibold text-muted">Trainer Leave</span>
                            <i class="bi bi-calendar2-x text-primary fs-5"></i>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between">
                            <span class="h4 mb-0 fw-bold text-dark"><?= $pendingTrainerLeave ?></span>
                            <span class="badge <?= $pendingTrainerLeave > 0 ? 'bg-danger' : 'bg-secondary' ?>">
                                <?= $pendingTrainerLeave ?> pending
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>/admin/refunds/?status=pending" class="text-decoration-none">
                    <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-between hover-shadow">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-semibold text-muted">Refund Requests</span>
                            <i class="bi bi-arrow-counterclockwise text-danger fs-5"></i>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between">
                            <span class="h4 mb-0 fw-bold text-dark"><?= $pendingRefunds ?></span>
                            <span class="badge <?= $pendingRefunds > 0 ? 'bg-danger' : 'bg-secondary' ?>">
                                <?= $pendingRefunds ?> pending
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>/admin/transfers/?status=pending" class="text-decoration-none">
                    <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-between hover-shadow">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-semibold text-muted">Transfers</span>
                            <i class="bi bi-arrow-left-right text-info fs-5"></i>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between">
                            <span class="h4 mb-0 fw-bold text-dark"><?= $pendingTransfers ?></span>
                            <span class="badge <?= $pendingTransfers > 0 ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                <?= $pendingTransfers ?> pending
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= BASE_URL ?>/admin/change-requests/?status=pending" class="text-decoration-none">
                    <div class="p-3 rounded border bg-light h-100 d-flex flex-column justify-content-between hover-shadow">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-semibold text-muted">Change Requests</span>
                            <i class="bi bi-pencil-square text-success fs-5"></i>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between">
                            <span class="h4 mb-0 fw-bold text-dark"><?= $pendingChanges ?></span>
                            <span class="badge <?= $pendingChanges > 0 ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                <?= $pendingChanges ?> pending
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="fin-card">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-wallet2 text-success"></i>
                <div class="fin-label mb-0">Total Revenue</div>
            </div>
            <div class="fin-value tnum text-success"><?= formatMoney($totalRevenue) ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-check-circle me-1"></i> Completed payments</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="fin-card">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-receipt text-danger"></i>
                <div class="fin-label mb-0">Total Expenses</div>
            </div>
            <div class="fin-value tnum text-danger"><?= formatMoney($totalExpenses) ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-arrow-down-circle me-1"></i> Gym operational costs</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="fin-card is-primary">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-graph-up-arrow text-warning"></i>
                <div class="fin-label mb-0">Net Profit</div>
            </div>
            <div class="fin-value tnum <?= $netProfit < 0 ? 'is-negative' : '' ?>">
                <?= formatMoney($netProfit) ?>
            </div>
            <div class="small text-white-50 mt-1"><i class="bi bi-shield-check me-1"></i> Net earnings overview</div>
        </div>
    </div>
</div>

<!-- Activity & Tables Grid -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Recent Payments</div>
                <a href="<?= BASE_URL ?>/admin/payments/" class="btn btn-sm btn-outline-dark">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentPayments): ?>
                        <tr><td colspan="3" class="text-muted text-center">No payments yet.</td></tr>
                    <?php else: foreach ($recentPayments as $p): ?>
                        <tr>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($p['full_name']) ?></td>
                            <td class="text-end num font-monospace fw-bold text-success"><?= formatMoney($p['amount']) ?></td>
                            <td class="text-end text-muted small"><?= formatDate($p['payment_date']) ?></td>
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
                <div class="fw-bold"><i class="bi bi-person-plus me-2 text-success"></i>Recent Members</div>
                <a href="<?= BASE_URL ?>/admin/members/" class="btn btn-sm btn-outline-dark">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="text-end">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentMembers): ?>
                        <tr><td colspan="2" class="text-muted text-center">No members registered yet.</td></tr>
                    <?php else: foreach ($recentMembers as $m): ?>
                        <tr>
                            <td class="fw-medium text-dark">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-chip" style="width:28px;height:28px;font-size:0.7rem;">
                                        <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($m['full_name']) ?>
                                </div>
                            </td>
                            <td class="text-end text-muted small"><?= formatDate($m['join_date']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    <span>Memberships Expiring in Next 7 Days</span>
                    <span class="badge bg-warning text-dark"><?= count($upcomingExpirations) ?></span>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/admin/memberships/expiring.php" class="btn btn-sm btn-outline-warning text-dark">View Expiring List</a>
                    <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-sm btn-outline-dark">All Memberships</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Plan</th>
                            <th>Phone</th>
                            <th>End Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$upcomingExpirations): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No memberships expiring in the next 7 days.</td></tr>
                    <?php else: foreach ($upcomingExpirations as $u):
                        $todayObj  = new DateTime(date('Y-m-d'));
                        $expireObj = new DateTime($u['expiry_date']);
                        $diffDays  = (int) $todayObj->diff($expireObj)->format('%r%a');
                        if ($diffDays <= 0) {
                            $countdownBadge = '<span class="badge bg-danger">Expires Today</span>';
                        } elseif ($diffDays === 1) {
                            $countdownBadge = '<span class="badge bg-danger">Tomorrow</span>';
                        } else {
                            $countdownBadge = '<span class="badge bg-warning text-dark">In ' . $diffDays . ' days</span>';
                        }
                    ?>
                        <tr>
                            <td class="fw-medium text-dark">
                                <a href="<?= BASE_URL ?>/admin/members/view.php?id=<?= $u['member_id'] ?>" class="text-decoration-none text-dark fw-semibold">
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($u['plan_name'] ?? 'Custom Plan') ?></span></td>
                            <td class="text-muted small"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                            <td>
                                <div><?= formatDate($u['expiry_date']) ?></div>
                                <div><?= $countdownBadge ?></div>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/admin/memberships/add.php?member_id=<?= $u['member_id'] ?>" class="btn btn-sm btn-dark">
                                    <i class="bi bi-arrow-repeat me-1"></i> Renew Plan
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>