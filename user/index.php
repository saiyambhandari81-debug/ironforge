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
?>

<h1 class="h4 mb-4">Namaste, <?= htmlspecialchars($_SESSION['member_name']) ?>!</h1>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="plan-card h-100">
            <div class="text-white-50 small">Membership</div>
            <?php if ($membership): ?>
                <div class="fs-4 fw-bold"><?= htmlspecialchars($membership['plan_name']) ?></div>
                <div class="small text-white-50 mt-1">
                    Status: <?= htmlspecialchars($membership['status']) ?><br>
                    Expires: <?= htmlspecialchars($membership['expiry_date']) ?>
                </div>
            <?php else: ?>
                <div class="fs-4 fw-bold">No plan</div>
                <div class="small text-white-50 mt-1">Contact the front desk to get started.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="stat-label">Today</div>
            <?php if ($today): ?>
                <div class="stat-value text-success">Checked in</div>
                <div class="small text-muted mt-1">
                    In: <?= htmlspecialchars($today['check_in_time'] ?? '—') ?><br>
                    Out: <?= htmlspecialchars($today['check_out_time'] ?? '—') ?>
                </div>
            <?php else: ?>
                <div class="stat-value">Not checked in</div>
                <div class="small text-muted mt-1">Check in at the gym desk.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card p-3 h-100">
            <div class="stat-label">Quick links</div>
            <div class="mt-2 d-grid gap-1">
                <a href="<?= BASE_URL ?>/user/bookings.php">Request a booking</a>
                <a href="<?= BASE_URL ?>/user/membership.php">View membership</a>
                <a href="<?= BASE_URL ?>/user/payments.php">View payments</a>
                <a href="<?= BASE_URL ?>/user/profile.php">Edit profile</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white border-0 fw-semibold pt-3">Upcoming sessions</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Trainer</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$upcoming): ?>
                        <tr><td colspan="3" class="text-muted">No upcoming bookings.</td></tr>
                    <?php else: foreach ($upcoming as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['trainer_name']) ?></td>
                            <td><?= htmlspecialchars($b['slot_date']) ?> <?= htmlspecialchars(substr($b['start_time'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars(ucfirst($b['booking_status'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white border-0 fw-semibold pt-3">Recent payments</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Date</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentPayments): ?>
                        <tr><td colspan="3" class="text-muted">No payments yet.</td></tr>
                    <?php else: foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['payment_date']) ?></td>
                            <td><?= formatMoney($p['amount']) ?></td>
                            <td><?= htmlspecialchars($p['payment_status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>