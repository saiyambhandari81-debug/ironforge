<?php

require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
$pageTitle = 'Dashboard'; // change per page
require ROOT_PATH . '/includes/user_header.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];

$stmt = $pdo->prepare("
    SELECT attendance_date, check_in_time, check_out_time, status
    FROM attendance
    WHERE member_id = ?
    ORDER BY attendance_date DESC
    LIMIT 50
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= BASE_URL ?>/user/index.php">IronForge</a>
    <div class="d-flex gap-3">
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/index.php">Dashboard</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/membership.php">Membership</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/payments.php">Payments</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/attendance.php">Attendance</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/logout.php">Log out</a>
    </div>
</nav>

<div class="container py-4">
    <h1 class="h4 mb-3">My Attendance</h1>
    <p class="text-muted">Check-in is done at the gym (admin attendance). Your history shows here.</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Check in</th>
                        <th>Check out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4" class="text-muted">No attendance records yet.</td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['attendance_date']) ?></td>
                        <td><?= htmlspecialchars($r['check_in_time'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['check_out_time'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['status'] ?? 'present') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>
<?php require ROOT_PATH . '/includes/user_footer.php'; ?>