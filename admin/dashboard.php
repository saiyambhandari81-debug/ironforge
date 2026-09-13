<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

// ---- Counts ----
$totalMembers       = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers      = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$expiredMemberships = $pdo->query("SELECT COUNT(*) FROM memberships WHERE expiry_date < CURDATE()")->fetchColumn();
$totalTrainers      = $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn();
$todayAttendance    = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();

$pendingTransfers = $pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'pending'")->fetchColumn();
$pendingRefunds   = $pdo->query("SELECT COUNT(*) FROM refunds WHERE status = 'pending'")->fetchColumn();
$pendingChanges   = $pdo->query("SELECT COUNT(*) FROM change_requests WHERE status = 'pending'")->fetchColumn();
$pendingRequests  = $pendingTransfers + $pendingRefunds + $pendingChanges;

// ---- Finance ----
$totalRevenue  = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'completed'")->fetchColumn();
$totalExpenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
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

$upcomingExpirations = $pdo->query("
    SELECT ms.expiry_date, m.full_name
    FROM memberships ms
    JOIN members m ON ms.member_id = m.member_id
    WHERE ms.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ms.expiry_date ASC
    LIMIT 5
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
            <div class="kpi-context">Transfers, refunds, info</div>
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
                <div class="fw-bold"><i class="bi bi-exclamation-circle me-2 text-warning"></i>Upcoming Expirations (Next 7 Days)</div>
                <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-sm btn-outline-dark">Manage Memberships</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Expiration Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$upcomingExpirations): ?>
                        <tr><td colspan="3" class="text-muted text-center">No memberships expiring in the next 7 days.</td></tr>
                    <?php else: foreach ($upcomingExpirations as $u): ?>
                        <tr>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><span class="badge bg-warning"><i class="bi bi-clock me-1"></i><?= formatDate($u['expiry_date']) ?></span></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-sm btn-outline-dark">Renew</a>
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