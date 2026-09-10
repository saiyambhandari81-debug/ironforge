<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f5f6f8; margin: 0; }
        .admin-wrap { display: flex; min-height: 100vh; }
        .admin-main { flex: 1; min-width: 0; }
        .admin-top {
            background: #fff;
            border-bottom: 1px solid #e6e8ec;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-content { padding: 24px; }
        .stat-card, .card {
            border: 0;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            background: #fff;
        }
        .stat-value { font-size: 1.6rem; font-weight: 700; }
        .stat-label { color: #6b7280; font-size: .85rem; }
        #sidebar a:hover { background: rgba(255,255,255,.08); color: #fff !important; }
        .table th { font-size: .8rem; color: #6b7280; font-weight: 600; }
    </style>
</head>
<body>
<div class="admin-wrap">
    <?php require_once ROOT_PATH . '/includes/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-top">
            <div class="fw-semibold"><?= htmlspecialchars($pageTitle) ?></div>
            <div class="text-muted small">
                <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
            </div>
        </div>
        <div class="admin-content">