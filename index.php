<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

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
    <style>
        body { background: #fff; color: #0f1419; }
        .nav-bar {
            background: #0f1419;
            padding: 14px 20px;
        }
        .nav-bar a { text-decoration: none; }
        .hero {
            background: linear-gradient(120deg, #0f1419 55%, #1c2430 100%);
            color: #fff;
            padding: 72px 20px;
        }
        .hero h1 { font-weight: 800; letter-spacing: -0.5px; }
        .plan-card, .trainer-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            height: 100%;
        }
        .plan-featured {
            background: #0f1419;
            color: #fff;
        }
        .footer {
            background: #0f1419;
            color: #9ca3af;
            padding: 28px 20px;
        }
        .footer a { color: #d1d5db; text-decoration: none; }
    </style>
</head>
<body>

<nav class="nav-bar d-flex justify-content-between align-items-center">
    <div class="text-white fw-bold fs-5">IronForge Gym</div>
    <div>
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-outline-light me-2">Log in</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-light">Join Now</a>
    </div>
</nav>

<section class="hero text-center">
    <div class="container" style="max-width: 760px;">
        <h1 class="display-5 mb-3">Train hard.<br>Stay consistent.</h1>
        <p class="lead text-white-50 mb-4">
            Memberships, trainers, attendance and bookings — all in one place.
        </p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-light btn-lg me-2">Create free account</a>
        <a href="#plans" class="btn btn-outline-light btn-lg">View plans</a>
    </div>
</section>

<section id="plans" class="container py-5">
    <div class="text-center mb-4">
        <h2 class="h3 fw-bold">Membership plans for every goal</h2>
        <p class="text-muted mb-0">Transparent pricing. No hidden fees.</p>
    </div>

    <div class="row g-3 justify-content-center">
        <?php if (!$plans): ?>
            <p class="text-muted text-center">Plans will appear here once the gym adds them.</p>
        <?php else: ?>
            <?php foreach ($plans as $i => $p): ?>
                <div class="col-md-4">
                    <div class="card plan-card p-4 <?= $i === 1 ? 'plan-featured' : '' ?>">
                        <div class="<?= $i === 1 ? 'text-white-50' : 'text-muted' ?> small mb-1">
                            <?= (int)$p['duration_days'] ?> days
                        </div>
                        <h3 class="h5 fw-bold"><?= htmlspecialchars($p['plan_name']) ?></h3>
                        <div class="fs-3 fw-bold mb-3">
                            <?= formatMoney($p['price']) ?>
                        </div>
                        <?php if (!empty($p['features'])): ?>
                            <p class="small mb-4 <?= $i === 1 ? 'text-white-50' : 'text-muted' ?>">
                                <?= nl2br(htmlspecialchars($p['features'])) ?>
                            </p>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/register.php"
                           class="btn <?= $i === 1 ? 'btn-light' : 'btn-dark' ?> w-100">
                            Get started
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($trainers): ?>
<section class="py-5" style="background:#f5f6f8;">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold">Train with our coaches</h2>
            <p class="text-muted mb-0">Experienced trainers to guide your progress.</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php foreach ($trainers as $t): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card trainer-card p-3 text-center">
                        <div class="fw-semibold"><?= htmlspecialchars($t['full_name'] ?? '') ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($t['specialization'] ?? '') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="footer">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="text-white fw-semibold">IronForge Gym</div>
            <div class="small">Train hard. Stay consistent.</div>
        </div>
        <div class="small">
            <a href="<?= BASE_URL ?>/login.php">Log in</a>
            &nbsp;·&nbsp;
            <a href="<?= BASE_URL ?>/register.php">Register</a>
        </div>
    </div>
</footer>

</body>
</html>