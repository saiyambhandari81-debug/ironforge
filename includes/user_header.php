<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Member';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; margin: 0; }
        .member-nav {
            background: #fff;
            border-bottom: 1px solid #e6e8ec;
            padding: 12px 20px;
        }
        .member-nav .brand {
            font-weight: 700;
            color: #0f1419;
            text-decoration: none;
            margin-right: 20px;
        }
        .member-nav a {
            color: #4b5563;
            text-decoration: none;
            margin-right: 14px;
            font-size: 0.95rem;
        }
        .member-nav a:hover { color: #0f1419; }
        .member-content { padding: 28px 20px; max-width: 1100px; margin: 0 auto; }
        .card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            background: #fff;
        }
        .plan-card {
            background: #0f1419;
            color: #fff;
            border-radius: 14px;
            padding: 20px;
        }
        .stat-label { color: #6b7280; font-size: .85rem; }
        .stat-value { font-size: 1.35rem; font-weight: 700; }
    </style>
</head>
<body>
<nav class="member-nav d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex flex-wrap align-items-center">
        <a class="brand" href="<?= BASE_URL ?>/user/index.php">IronForge</a>
        <a href="<?= BASE_URL ?>/user/index.php">Dashboard</a>
        <a href="<?= BASE_URL ?>/user/membership.php">Membership</a>
        <a href="<?= BASE_URL ?>/user/payments.php">Payments</a>
        <a href="<?= BASE_URL ?>/user/attendance.php">Attendance</a>
        <a href="<?= BASE_URL ?>/user/bookings.php">Bookings</a>
        <a href="<?= BASE_URL ?>/user/profile.php">Profile</a>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small d-none d-md-inline">
            <?= htmlspecialchars($_SESSION['member_name'] ?? '') ?>
        </span>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-dark">Log out</a>
    </div>
</nav>
<main class="member-content">