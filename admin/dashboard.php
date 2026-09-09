<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

// ---- Headline counts ----
$totalMembers       = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$activeMembers      = $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$expiredMemberships = $pdo->query("SELECT COUNT(*) FROM memberships WHERE expiry_date < CURDATE()")->fetchColumn();
$totalTrainers      = $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn();
$todayAttendance    = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();

// ---- Pending requests ----
$pendingTransfers = $pdo->query("SELECT COUNT(*) FROM transfers WHERE status = 'pending'")->fetchColumn();
$pendingRefunds   = $pdo->query("SELECT COUNT(*) FROM refunds WHERE status = 'pending'")->fetchColumn();
$pendingChanges   = $pdo->query("SELECT COUNT(*) FROM change_requests WHERE status = 'pending'")->fetchColumn();
$pendingRequests  = $pendingTransfers + $pendingRefunds + $pendingChanges;

// ---- Finance ----
$totalRevenue  = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'completed'")->fetchColumn();
$totalExpenses = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn();
$netProfit     = $totalRevenue - $totalExpenses;

// ---- Recent activity ----
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
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total Members</div>
            <div class="fs-3 fw-semibold"><?= $totalMembers ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Active Members</div>
            <div class="fs-3 fw-semibold"><?= $activeMembers ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Expired Memberships</div>
            <div class="fs-3 fw-semibold"><?= $expiredMemberships ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total Trainers</div>
            <div class="fs-3 fw-semibold"><?= $totalTrainers ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Today's Attendance</div>
            <div class="fs-3 fw-semibold"><?= $todayAttendance ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Pending Requests</div>
            <div class="fs-3 fw-semibold"><?= $pendingRequests ?></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total Revenue</div>
            <div class="fs-3 fw-semibold text-success"><?= formatMoney($totalRevenue) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Total Expenses</div>
            <div class="fs-3 fw-semibold text-danger"><?= formatMoney($totalExpenses) ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Net Profit</div>
            <div class="fs-3 fw-semibold <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatMoney($netProfit) ?></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Recent Payments</div>
            <table class="table mb-0">
                <thead><tr><th>Member</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (!$recentPayments): ?>
                    <tr><td colspan="3" class="text-muted">No payments recorded yet.</td></tr>
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
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Recent Members</div>
            <table class="table mb-0">
                <thead><tr><th>Name</th><th>Joined</th></tr></thead>
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

<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Upcoming Membership Expirations (next 7 days)</div>
            <table class="table mb-0">
                <thead><tr><th>Member</th><th>Expires</th></tr></thead>
                <tbody>
                <?php if (!$upcomingExpirations): ?>
                    <tr><td colspan="2" class="text-muted">Nothing expiring in the next 7 days.</td></tr>
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

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>