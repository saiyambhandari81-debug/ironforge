<?php
require_once ROOT_PATH . '/includes/auth.php';
requireTrainer();

if (!isset($pageTitle)) {
    $pageTitle = 'Trainer Portal';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - IronForge Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="admin-wrap">
    <?php require_once ROOT_PATH . '/includes/trainer_sidebar.php'; ?>
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-title">
                <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <div class="topbar-breadcrumb">Trainer / Portal</div>
                    <h1 class="h6 mb-0 fw-bold"><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="admin-profile ms-2 ps-2 border-start border-light-subtle">
                    <div class="avatar-chip">
                        <?= strtoupper(substr($_SESSION['trainer_name'] ?? 'T', 0, 1)) ?>
                    </div>
                    <div class="d-none d-sm-block">
                        <div class="fw-semibold lh-1" style="font-size: 0.85rem;"><?= htmlspecialchars($_SESSION['trainer_name'] ?? 'Trainer') ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;">Trainer Account</div>
                    </div>
                </div>
            </div>
        </header>
        <main class="admin-content">
