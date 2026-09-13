<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Member Portal';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IronForge Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="member-nav">
    <div class="d-flex align-items-center gap-3">
        <a class="brand" href="<?= BASE_URL ?>/user/index.php">
            <span class="mark">IF</span>
            <span>IronForge</span>
        </a>
        <div class="member-links">
            <a href="<?= BASE_URL ?>/user/index.php"><i class="bi bi-grid-fill"></i> Dashboard</a>
            <a href="<?= BASE_URL ?>/user/membership.php"><i class="bi bi-card-checklist"></i> Membership</a>
            <a href="<?= BASE_URL ?>/user/payments.php"><i class="bi bi-credit-card-fill"></i> Payments</a>
            <a href="<?= BASE_URL ?>/user/attendance.php"><i class="bi bi-clock-history"></i> Attendance</a>
            <a href="<?= BASE_URL ?>/user/bookings.php"><i class="bi bi-calendar-event"></i> Bookings</a>
            <a href="<?= BASE_URL ?>/user/profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <div class="d-none d-md-flex align-items-center gap-2">
            <div class="avatar-chip" style="width:32px;height:32px;font-size:0.75rem;">
                <?= strtoupper(substr($_SESSION['member_name'] ?? 'M', 0, 1)) ?>
            </div>
            <span class="fw-semibold small text-dark">
                <?= htmlspecialchars($_SESSION['member_name'] ?? 'Member') ?>
            </span>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-dark d-none d-md-inline-flex align-items-center gap-1">
            <i class="bi bi-box-arrow-right"></i> Log out
        </a>

        <!-- Mobile Drawer Toggle -->
        <button class="icon-btn member-offcanvas-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#memberMobileMenu" aria-controls="memberMobileMenu">
            <i class="bi bi-list fs-5"></i>
        </button>
    </div>
</nav>

<!-- Mobile Offcanvas Menu -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="memberMobileMenu" aria-labelledby="memberMobileMenuLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="memberMobileMenuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column justify-content-between">
        <div class="d-flex flex-column gap-2">
            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded mb-3">
                <div class="avatar-chip">
                    <?= strtoupper(substr($_SESSION['member_name'] ?? 'M', 0, 1)) ?>
                </div>
                <div>
                    <div class="fw-bold"><?= htmlspecialchars($_SESSION['member_name'] ?? 'Member') ?></div>
                    <div class="text-muted small">Gym Member</div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-light text-start p-3 fw-medium"><i class="bi bi-grid-fill me-2"></i> Dashboard</a>
            <a href="<?= BASE_URL ?>/user/membership.php" class="btn btn-light text-start p-3 fw-medium"><i class="bi bi-card-checklist me-2"></i> Membership</a>
            <a href="<?= BASE_URL ?>/user/payments.php" class="btn btn-light text-start p-3 fw-medium"><i class="bi bi-credit-card-fill me-2"></i> Payments</a>
            <a href="<?= BASE_URL ?>/user/attendance.php" class="btn btn-light text-start p-3 fw-medium"><i class="bi bi-clock-history me-2"></i> Attendance</a>
            <a href="<?= BASE_URL ?>/user/bookings.php" class="btn btn-light text-start p-3 fw-medium"><i class="bi bi-calendar-event me-2"></i> Bookings</a>
            <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-light text-start p-3 fw-medium"><i class="bi bi-person-circle me-2"></i> Profile</a>
        </div>
        <div class="pt-3 border-top">
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger w-100 p-3"><i class="bi bi-box-arrow-right me-2"></i> Log out</a>
        </div>
    </div>
</div>

<main class="member-content">