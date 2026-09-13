<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireLogin();

// ---- Member Reports ----
$totalMembers  = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();

$startOfMonth = date('Y-m-01');
$newMembersStmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE DATE(created_at) >= ?");
$newMembersStmt->execute([$startOfMonth]);
$newMembers = $newMembersStmt->fetchColumn();

$lapsedMembers = $pdo->query("
    SELECT COUNT(DISTINCT m.member_id)
    FROM members m
    WHERE m.status = 'active'
      AND EXISTS (
          SELECT 1 FROM memberships ms WHERE ms.member_id = m.member_id
      )
      AND NOT EXISTS (
          SELECT 1 FROM memberships ms2
          WHERE ms2.member_id = m.member_id
            AND ms2.status = 'active'
            AND ms2.expiry_date >= CURDATE()
      )
")->fetchColumn();

// ---- Membership Reports ----
$activeMemberships   = $pdo->query("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn();
$expiredMemberships  = $pdo->query("SELECT COUNT(*) FROM memberships WHERE expiry_date < CURDATE()")->fetchColumn();
$upcomingExpirations = $pdo->query("SELECT COUNT(*) FROM memberships WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$popularPlans = $pdo->query("
    SELECT p.plan_name, COUNT(ms.membership_id) AS purchase_count
    FROM plans p
    LEFT JOIN memberships ms ON ms.plan_id = p.plan_id
    GROUP BY p.plan_id, p.plan_name
    ORDER BY purchase_count DESC
    LIMIT 5
")->fetchAll();

// ---- Outstanding Payments ----
$outstanding = $pdo->query("
    SELECT * FROM (
        SELECT m.full_name, pl.plan_name, ms.total_price,
               COALESCE((SELECT SUM(amount) FROM payments pp 
                         WHERE pp.membership_id = ms.membership_id 
                         AND pp.payment_status = 'completed'), 0) AS paid
        FROM memberships ms
        JOIN members m ON ms.member_id = m.member_id
        JOIN plans pl ON ms.plan_id = pl.plan_id
    ) AS d
    WHERE total_price - paid > 0
    ORDER BY (total_price - paid) DESC
    LIMIT 10
")->fetchAll();

// ---- Attendance ----
$todayAttendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();

// ---- Trainer ----
$totalBookings     = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$completedSessions = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'completed'")->fetchColumn();
$availableSlots    = $pdo->query("SELECT COUNT(*) FROM trainer_slots WHERE status = 'available' AND slot_date >= CURDATE()")->fetchColumn();

// ---- Financial (this month) ----
$monthRevenue  = calculateRevenue($pdo, $startOfMonth, date('Y-m-d'));
$monthExpenses = calculateExpenses($pdo, $startOfMonth, date('Y-m-d'));

$pageTitle = 'Reports Overview';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">Reports &amp; Analytics</h1>
        <p class="text-muted small mb-0">Overview of operational metrics, member statistics, and financial performance</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/reports/revenue.php" class="btn btn-sm btn-outline-dark"><i class="bi bi-graph-up me-1"></i> Revenue Report</a>
        <a href="<?= BASE_URL ?>/admin/reports/profit-loss.php" class="btn btn-sm btn-accent"><i class="bi bi-pie-chart me-1"></i> Profit &amp; Loss</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Member Reports -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-people me-2 text-primary"></i>Member Statistics</div>
                <a href="<?= BASE_URL ?>/admin/members/" class="btn btn-sm btn-link text-decoration-none p-0">View members &rarr;</a>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-3">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">Total</div>
                            <div class="fs-4 fw-bold tnum text-dark"><?= (int)$totalMembers ?></div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">Active</div>
                            <div class="fs-4 fw-bold tnum text-success"><?= (int)$activeMembers ?></div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">New (Mo)</div>
                            <div class="fs-4 fw-bold tnum text-primary"><?= (int)$newMembers ?></div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">Lapsed</div>
                            <div class="fs-4 fw-bold tnum text-danger"><?= (int)$lapsedMembers ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Membership Reports -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-card-checklist me-2 text-success"></i>Membership Tier Insights</div>
                <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-sm btn-link text-decoration-none p-0">Manage subscriptions &rarr;</a>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">Active</div>
                            <div class="fs-4 fw-bold tnum text-success"><?= (int)$activeMemberships ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">Expired</div>
                            <div class="fs-4 fw-bold tnum text-danger"><?= (int)$expiredMemberships ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded">
                            <div class="text-muted small">Expiring (7d)</div>
                            <div class="fs-4 fw-bold tnum text-warning"><?= (int)$upcomingExpirations ?></div>
                        </div>
                    </div>
                </div>
                <div class="small fw-semibold text-muted mb-2">Most Popular Plans:</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                        <?php if (!$popularPlans): ?>
                            <tr><td class="text-muted text-center py-2">No plan purchases recorded yet.</td></tr>
                        <?php else: foreach ($popularPlans as $p): ?>
                            <tr>
                                <td class="fw-medium text-dark"><?= htmlspecialchars($p['plan_name']) ?></td>
                                <td class="text-end tnum font-monospace fw-bold"><?= (int)$p['purchase_count'] ?> sales</td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Outstanding Payments -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-exclamation-square me-2 text-danger"></i>Outstanding Balances (Top 10)</div>
                <a href="<?= BASE_URL ?>/admin/payments/" class="btn btn-sm btn-link text-decoration-none p-0">View all payments &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Member</th><th>Plan</th><th class="text-end">Due Balance</th></tr></thead>
                    <tbody>
                    <?php if (!$outstanding): ?>
                        <tr><td colspan="3" class="text-muted text-center py-3">All member balances paid in full.</td></tr>
                    <?php else: foreach ($outstanding as $o): ?>
                        <tr>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($o['full_name']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($o['plan_name']) ?></td>
                            <td class="text-end tnum font-monospace fw-bold text-danger"><?= formatMoney($o['total_price'] - $o['paid']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Attendance & Financial Summary -->
    <div class="col-lg-6">
        <div class="card h-100 mb-3">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Attendance Activity</div>
                <a href="<?= BASE_URL ?>/admin/attendance/history.php" class="btn btn-sm btn-link text-decoration-none p-0">History &rarr;</a>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between bg-light p-3 rounded">
                    <div>
                        <div class="text-muted small">Today's Check-ins</div>
                        <div class="fs-3 fw-bold tnum text-dark"><?= (int)$todayAttendance ?></div>
                    </div>
                    <i class="bi bi-person-bounding-box fs-1 text-secondary opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-bank me-2 text-success"></i>Financial Month-to-Date</div>
                <a href="<?= BASE_URL ?>/admin/reports/profit-loss.php" class="btn btn-sm btn-link text-decoration-none p-0">P&amp;L Breakdown &rarr;</a>
            </div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small">Month Revenue</div>
                            <div class="fs-4 fw-bold tnum text-success"><?= formatMoney($monthRevenue['total']) ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <div class="text-muted small">Month Expenses</div>
                            <div class="fs-4 fw-bold tnum text-danger"><?= formatMoney($monthExpenses) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>