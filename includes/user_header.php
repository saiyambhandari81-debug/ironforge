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
        body { background: #f4f5f7; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-radius: 12px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= BASE_URL ?>/user/index.php">IronForge</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#memberNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="memberNav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/index.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/membership.php">Membership</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/payments.php">Payments</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/attendance.php">Attendance</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/bookings.php">Bookings</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/profile.php">Profile</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/user/change-password.php">Password</a></li>
        </ul>
        <span class="navbar-text text-white-50 me-3 d-none d-lg-inline">
            <?= htmlspecialchars($_SESSION['member_name'] ?? '') ?>
        </span>
        <a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>/logout.php">Log out</a>
    </div>
</nav>
<main class="container py-4">