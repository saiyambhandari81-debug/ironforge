<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

$plans = $pdo->query("
    SELECT plan_name, price, duration_days, features
    FROM plans
    WHERE status = 'active'
    ORDER BY price ASC
")->fetchAll();

$trainers = $pdo->query("
    SELECT full_name, specialization
    FROM trainers
    WHERE status = 'active'
    ORDER BY full_name
    LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand mb-0 h1">IronForge Gym</span>
    <div>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-outline-light me-2">Log in</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-light">Join</a>
    </div>
</nav>

<section class="bg-dark text-white text-center py-5">
    <div class="container">
        <h1 class="display-6 fw-bold">Train hard. Stay consistent.</h1>
        <p class="lead text-white-50">Memberships, trainers, and progress — all in one place.</p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-light btn-lg mt-2">Create free account</a>
    </div>
</section>

<section class="container py-5">
    <h2 class="h4 mb-4 text-center">Membership Plans</h2>
    <div class="row g-3 justify-content-center">
        <?php if (!$plans): ?>
            <p class="text-muted text-center">Plans will appear here once the gym adds them.</p>
        <?php else: foreach ($plans as $p): ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h3 class="h5"><?= htmlspecialchars($p['plan_name']) ?></h3>
                        <p class="fs-4 fw-semibold mb-1"><?= formatMoney($p['price']) ?></p>
                        <p class="text-muted small"><?= (int)$p['duration_days'] ?> days</p>
                        <?php if (!empty($p['features'])): ?>
                            <p class="small mb-0"><?= nl2br(htmlspecialchars($p['features'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>

<?php if ($trainers): ?>
<section class="bg-light py-5">
    <div class="container">
        <h2 class="h4 mb-4 text-center">Our Trainers</h2>
        <div class="row g-3 justify-content-center">
            <?php foreach ($trainers as $t): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100 text-center p-3">
                        <div class="fw-semibold"><?= htmlspecialchars($t['full_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($t['specialization'] ?? '') ?></div>
                        
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="border-top py-4 text-center text-muted small">
    <div class="container">
        IronForge Gym &middot;
        <a href="<?= BASE_URL ?>/login.php">Log in</a> &middot;
        <a href="<?= BASE_URL ?>/register.php">Register</a>
    </div>
</footer>
</body>
</html>