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

$pageTitle = 'Reports';
require_once ROOT_PATH . '/includes/header.php';
?>

<h5 class="mb-3">Reports</h5>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Member Reports</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col"><div class="text-muted small">Total</div><div class="fs-4 fw-semibold"><?= (int)$totalMembers ?></div></div>
                    <div class="col"><div class="text-muted small">Active</div><div class="fs-4 fw-semibold"><?= (int)$activeMembers ?></div></div>
                    <div class="col"><div class="text-muted small">New This Month</div><div class="fs-4 fw-semibold"><?= (int)$newMembers ?></div></div>
                    <div class="col"><div class="text-muted small">Lapsed</div><div class="fs-4 fw-semibold"><?= (int)$lapsedMembers ?></div></div>
                </div>
                <a href="<?= BASE_URL ?>/admin/members/" class="small">View all members &raquo;</a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Membership Reports</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col"><div class="text-muted small">Active</div><div class="fs-4 fw-semibold"><?= (int)$activeMemberships ?></div></div>
                    <div class="col"><div class="text-muted small">Expired</div><div class="fs-4 fw-semibold"><?= (int)$expiredMemberships ?></div></div>
                    <div class="col"><div class="text-muted small">Expiring in 7 Days</div><div class="fs-4 fw-semibold"><?= (int)$upcomingExpirations ?></div></div>
                </div>
                <div class="text-muted small mb-1">Most Popular Plans</div>
                <table class="table table-sm mb-2">
                    <tbody>
                    <?php if (!$popularPlans): ?>
                        <tr><td class="text-muted">No plans yet.</td></tr>
                    <?php else: foreach ($popularPlans as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['plan_name']) ?></td>
                            <td class="text-end"><?= (int)$p['purchase_count'] ?> purchased</td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <a href="<?= BASE_URL ?>/admin/memberships/" class="small">View all memberships &raquo;</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Payment Reports</div>
            <div class="card-body">
                <div class="text-muted small mb-1">Outstanding Payments (top 10)</div>
                <table class="table table-sm mb-2">
                    <thead><tr><th>Member</th><th>Plan</th><th class="text-end">Due</th></tr></thead>
                    <tbody>
                    <?php if (!$outstanding): ?>
                        <tr><td colspan="3" class="text-muted">Nothing outstanding.</td></tr>
                    <?php else: foreach ($outstanding as $o): ?>
                        <tr>
                            <td><?= htmlspecialchars($o['full_name']) ?></td>
                            <td><?= htmlspecialchars($o['plan_name']) ?></td>
                            <td class="text-end"><?= formatMoney($o['total_price'] - $o['paid']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <a href="<?= BASE_URL ?>/admin/payments/" class="small">View payment history &raquo;</a> ·
                <a href="<?= BASE_URL ?>/admin/reports/revenue.php" class="small">Revenue &raquo;</a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Attendance Reports</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col">
                        <div class="text-muted small">Today</div>
                        <div class="fs-4 fw-semibold"><?= (int)$todayAttendance ?></div>
                    </div>
                </div>
                <p class="text-muted small mb-2">Daily, monthly, and per-member attendance are in one filterable view.</p>
                <a href="<?= BASE_URL ?>/admin/attendance/history.php" class="small">View attendance history &raquo;</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Trainer Reports</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col"><div class="text-muted small">Total Bookings</div><div class="fs-4 fw-semibold"><?= (int)$totalBookings ?></div></div>
                    <div class="col"><div class="text-muted small">Completed</div><div class="fs-4 fw-semibold"><?= (int)$completedSessions ?></div></div>
                    <div class="col"><div class="text-muted small">Open Slots</div><div class="fs-4 fw-semibold"><?= (int)$availableSlots ?></div></div>
                </div>
                <a href="<?= BASE_URL ?>/admin/bookings/" class="small">View bookings &raquo;</a> ·
                <a href="<?= BASE_URL ?>/admin/trainer-slots/" class="small">View trainer slots &raquo;</a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Financial Reports</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col">
                        <div class="text-muted small">Revenue (This Month)</div>
                        <div class="fs-5 fw-semibold text-success"><?= formatMoney($monthRevenue['total']) ?></div>
                    </div>
                    <div class="col">
                        <div class="text-muted small">Expenses (This Month)</div>
                        <div class="fs-5 fw-semibold text-danger"><?= formatMoney($monthExpenses) ?></div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/admin/reports/revenue.php" class="small">Revenue &raquo;</a> ·
                <a href="<?= BASE_URL ?>/admin/expenses/" class="small">Expenses &raquo;</a> ·
                <a href="<?= BASE_URL ?>/admin/reports/profit-loss.php" class="small">Profit &amp; Loss &raquo;</a>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>