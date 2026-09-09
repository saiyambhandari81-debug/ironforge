<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$errors = [];
$old = [
    'plan_name'     => '',
    'price'         => '',
    'duration_days' => '',
    'features'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['plan_name']     = trim($_POST['plan_name'] ?? '');
    $old['price']         = trim($_POST['price'] ?? '');
    $old['duration_days'] = trim($_POST['duration_days'] ?? '');
    $old['features']      = trim($_POST['features'] ?? '');

    // ====================== VALIDATION ======================

    // Plan Name
    if ($old['plan_name'] === '') {
        $errors[] = 'Plan name is required.';
    } elseif (mb_strlen($old['plan_name']) < 2) {
        $errors[] = 'Plan name must be at least 2 characters.';
    } elseif (mb_strlen($old['plan_name']) > 100) {
        $errors[] = 'Plan name cannot be longer than 100 characters.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.0-9]+$/u', $old['plan_name'])) {
        $errors[] = 'Plan name can only contain letters, numbers, spaces, hyphens, and apostrophes.';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM plans WHERE plan_name = ?");
        $check->execute([$old['plan_name']]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A plan with that name already exists.';
        }
    }

    // Price
    if ($old['price'] === '' || !is_numeric($old['price'])) {
        $errors[] = 'Price must be a valid number.';
    } elseif ((float) $old['price'] < 0) {
        $errors[] = 'Price cannot be negative.';
    } elseif ((float) $old['price'] > 1000000) {
        $errors[] = 'Price cannot be more than Rs. 1,000,000.';
    }

    // Duration
    if ($old['duration_days'] === '' || !ctype_digit($old['duration_days'])) {
        $errors[] = 'Duration must be a whole number of days.';
    } elseif ((int) $old['duration_days'] < 1) {
        $errors[] = 'Duration must be at least 1 day.';
    } elseif ((int) $old['duration_days'] > 3650) {
        $errors[] = 'Duration cannot be more than 10 years (3650 days).';
    }

    // Features
    if ($old['features'] !== '' && mb_strlen($old['features']) > 2000) {
        $errors[] = 'Features text cannot be longer than 2000 characters.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "INSERT INTO plans (plan_name, price, duration_days, features, status)
             VALUES (?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $old['plan_name'],
            $old['price'],
            $old['duration_days'],
            $old['features'] !== '' ? $old['features'] : null,
        ]);

        header('Location: ' . BASE_URL . '/admin/plans/?msg=added');
        exit;
    }
}

$pageTitle = 'Add Plan';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Plan Name *</label>
                <input type="text" name="plan_name" class="form-control" value="<?= htmlspecialchars($old['plan_name']) ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Price (Rs.) *</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= htmlspecialchars($old['price']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Duration (days) *</label>
                    <input type="number" step="1" min="1" name="duration_days" class="form-control" value="<?= htmlspecialchars($old['duration_days']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Features</label>
                <textarea name="features" class="form-control" rows="4" placeholder="One per line, e.g.&#10;Sauna access&#10;2 guest passes/month"><?= htmlspecialchars($old['features']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-dark">Save Plan</button>
            <a href="<?= BASE_URL ?>/admin/plans/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>