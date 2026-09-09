<?php
require_once ROOT_PATH . '/includes/functions.php';
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <div class="flex-grow-1">
        <nav class="navbar navbar-light bg-white border-bottom px-4">
            <span class="navbar-brand mb-0 h5"><?= htmlspecialchars($pageTitle) ?></span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-dark">Log Out</a>
            </div>
        </nav>
        <main class="p-4">