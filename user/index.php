<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
$pageTitle = 'Dashboard'; // change per page
require ROOT_PATH . '/includes/user_header.php';
requireMember();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Member Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
   <h1>Welcome, <?= htmlspecialchars($_SESSION['member_name']) ?></h1>
<p>This is your member area.</p>

<p>
    <a href="<?= BASE_URL ?>/user/membership.php" class="btn btn-dark">My Membership</a>
    <a href="<?= BASE_URL ?>/user/payments.php" class="btn btn-dark">My Payments</a>
    <a href="<?= BASE_URL ?>/user/attendance.php" class="btn btn-dark">My Attendance</a>
    <a href="<?= BASE_URL ?>/user/bookings.php" class="btn btn-dark">My Bookings</a>
    <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-dark">My Profile</a>
    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-dark">Log out</a>
</p>
</body>
</html><?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];

// Current membership (latest)
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

// Checked in today?
$attStmt = $pdo->prepare("
    SELECT check_in_time, check_out_time
    FROM attendance
    WHERE member_id = ? AND attendance_date = CURDATE()
    LIMIT 1
");
$attStmt->execute([$memberId]);
$today = $attStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= BASE_URL ?>/user/index.php">IronForge</a>
    <div class="d-flex gap-3 flex-wrap">
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/index.php">Dashboard</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/membership.php">Membership</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/payments.php">Payments</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/attendance.php">Attendance</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/bookings.php">Bookings</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/profile.php">Profile</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/logout.php">Log out</a>
    </div>
</nav>

<div class="container py-4">
    <h1 class="h4 mb-4">Welcome, <?= htmlspecialchars($_SESSION['member_name']) ?></h1>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 h-100">
                <div class="text-muted small">Membership</div>
                <?php if ($membership): ?>
                    <div class="fs-5 fw-semibold"><?= htmlspecialchars($membership['plan_name']) ?></div>
                    <div class="small text-muted">
                        Status: <?= htmlspecialchars($membership['status']) ?><br>
                        Expires: <?= htmlspecialchars($membership['expiry_date']) ?>
                    </div>
                <?php else: ?>
                    <div class="fs-5 fw-semibold">None</div>
                    <div class="small text-muted">Ask the front desk for a plan.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 h-100">
                <div class="text-muted small">Today</div>
                <?php if ($today): ?>
                    <div class="fs-5 fw-semibold text-success">Checked in</div>
                    <div class="small text-muted">
                        In: <?= htmlspecialchars($today['check_in_time'] ?? '—') ?><br>
                        Out: <?= htmlspecialchars($today['check_out_time'] ?? '—') ?>
                    </div>
                <?php else: ?>
                    <div class="fs-5 fw-semibold">Not checked in</div>
                    <div class="small text-muted">Check in at the gym desk.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 h-100">
                <div class="text-muted small">Quick links</div>
                <a href="<?= BASE_URL ?>/user/membership.php">My Membership</a><br>
                <a href="<?= BASE_URL ?>/user/payments.php">My Payments</a><br>
                <a href="<?= BASE_URL ?>/user/bookings.php">My Bookings</a><br>
                <a href="<?= BASE_URL ?>/user/profile.php">My Profile</a>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/user_footer.php'; ?>