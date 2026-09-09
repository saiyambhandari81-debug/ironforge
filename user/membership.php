<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
$pageTitle = 'Dashboard'; // change per page
require ROOT_PATH . '/includes/user_header.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];

$stmt = $pdo->prepare("
    SELECT ms.start_date, ms.expiry_date, ms.status, p.plan_name, p.price
    FROM memberships ms
    JOIN plans p ON p.plan_id = ms.plan_id
    WHERE ms.member_id = ?
    ORDER BY ms.start_date DESC
");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Membership - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= BASE_URL ?>/user/index.php">IronForge</a>
    <div class="d-flex gap-3">
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/index.php">Dashboard</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/membership.php">Membership</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/logout.php">Log out</a>
    </div>
</nav>

<div class="container py-4">
    <h1 class="h4 mb-3">My Membership</h1>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Price</th>
                        <th>Start</th>
                        <th>Expiry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="5" class="text-muted">
                            No membership yet. Please contact the gym front desk.
                        </td>
                    </tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['plan_name']) ?></td>
                        <td><?= formatMoney($r['price']) ?></td>
                        <td><?= htmlspecialchars($r['start_date']) ?></td>
                        <td><?= htmlspecialchars($r['expiry_date']) ?></td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/user_footer.php'; ?>