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

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Total Members</div>
            <div class="stat-value"><?= (int)$totalMembers ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Active Members</div>
            <div class="stat-value"><?= (int)$activeMembers ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Expired</div>
            <div class="stat-value"><?= (int)$expiredMemberships ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Trainers</div>
            <div class="stat-value"><?= (int)$totalTrainers ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Today Attendance</div>
            <div class="stat-value"><?= (int)$todayAttendance ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value"><?= (int)$pendingRequests ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card p-4 h-100" style="background:#0f1419;color:#fff;">
            <div class="text-white-50 small">Total Revenue</div>
            <div class="stat-value text-white"><?= formatMoney($totalRevenue) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-4 h-100">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value text-danger"><?= formatMoney($totalExpenses) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-4 h-100">
            <div class="stat-label">Net Profit</div>
            <div class="stat-value <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= formatMoney($netProfit) ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white border-0 fw-semibold pt-3">Recent Payments</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Member</th><th>Amount</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentPayments): ?>
                        <tr><td colspan="3" class="text-muted">No payments yet.</td></tr>
                    <?php else: foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['full_name']) ?></td>
                            <td><?= formatMoney($p['amount']) ?></td>
                            <td><?= formatDate($p['payment_date']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white border-0 fw-semibold pt-3">Recent Members</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Name</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentMembers): ?>
                        <tr><td colspan="2" class="text-muted">No members yet.</td></tr>
                    <?php else: foreach ($recentMembers as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['full_name']) ?></td>
                            <td><?= formatDate($m['join_date']) ?></td>
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
            <div class="card-header bg-white border-0 fw-semibold pt-3">Upcoming Expirations (7 days)</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Member</th><th>Expires</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$upcomingExpirations): ?>
                        <tr><td colspan="2" class="text-muted">Nothing expiring soon.</td></tr>
                    <?php else: foreach ($upcomingExpirations as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= formatDate($u['expiry_date']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>