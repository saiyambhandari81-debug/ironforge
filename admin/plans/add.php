<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$errors = [];
$old = [
    'plan_name'        => '',
    'price'            => '',
    'duration_days'    => '',
    'features'         => '',
    'status'           => 'active',
    'includes_trainer' => '0',
];

$hasStatusCol  = plansHasColumn($pdo, 'status');
$hasTrainerCol = plansHasColumn($pdo, 'includes_trainer');
$allowedStatus = ['active', 'inactive'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['plan_name']        = trim($_POST['plan_name'] ?? '');
    $old['price']            = trim($_POST['price'] ?? '');
    $old['duration_days']    = trim($_POST['duration_days'] ?? '');
    $old['features']         = trim($_POST['features'] ?? '');
    $old['status']           = trim($_POST['status'] ?? 'active');
    $old['includes_trainer'] = (isset($_POST['includes_trainer']) && (string) $_POST['includes_trainer'] === '1') ? '1' : '0';

    // Plan Name
    if ($old['plan_name'] === '') {
        $errors[] = 'Plan name is required.';
    } elseif (mb_strlen($old['plan_name']) < 2) {
        $errors[] = 'Plan name must be at least 2 characters.';
    } elseif (mb_strlen($old['plan_name']) > 100) {
        $errors[] = 'Plan name cannot be longer than 100 characters.';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM plans WHERE plan_name = ?');
        $check->execute([$old['plan_name']]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A plan with that name already exists.';
        }
    }

    // Price
    if ($old['price'] === '') {
        $errors[] = 'Price is required.';
    } elseif (!is_numeric($old['price'])) {
        $errors[] = 'Price must be a valid number.';
    } elseif ((float) $old['price'] < 0) {
        $errors[] = 'Price cannot be negative.';
    } elseif ((float) $old['price'] > 999999.99) {
        $errors[] = 'Price cannot be more than Rs. 999,999.99.';
    }

    // Duration
    if ($old['duration_days'] === '') {
        $errors[] = 'Duration is required.';
    } elseif (!ctype_digit($old['duration_days'])) {
        $errors[] = 'Duration must be a whole number of days.';
    } elseif ((int) $old['duration_days'] < 1) {
        $errors[] = 'Duration must be at least 1 day.';
    } elseif ((int) $old['duration_days'] > 3650) {
        $errors[] = 'Duration cannot be more than 10 years (3650 days).';
    }

    // Features (optional; whitespace-only becomes empty after trim)
    if ($old['features'] !== '' && mb_strlen($old['features']) > 2000) {
        $errors[] = 'Features text cannot be longer than 2000 characters.';
    }

    // Status
    if ($hasStatusCol && !in_array($old['status'], $allowedStatus, true)) {
        $errors[] = 'Status must be active or inactive.';
    }

    if (!$errors) {
        $includesTrainer = (int) $old['includes_trainer'];
        $featuresValue   = $old['features'] !== '' ? $old['features'] : null;
        $statusValue     = $hasStatusCol ? $old['status'] : 'active';

        if ($hasTrainerCol && $hasStatusCol) {
            $stmt = $pdo->prepare(
                'INSERT INTO plans (plan_name, price, duration_days, features, status, includes_trainer)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['plan_name'],
                $old['price'],
                $old['duration_days'],
                $featuresValue,
                $statusValue,
                $includesTrainer,
            ]);
        } elseif ($hasStatusCol) {
            $stmt = $pdo->prepare(
                'INSERT INTO plans (plan_name, price, duration_days, features, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['plan_name'],
                $old['price'],
                $old['duration_days'],
                $featuresValue,
                $statusValue,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO plans (plan_name, price, duration_days, features)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['plan_name'],
                $old['price'],
                $old['duration_days'],
                $featuresValue,
            ]);
        }

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
                    <input type="number" step="0.01" min="0" max="999999.99" name="price" class="form-control" value="<?= htmlspecialchars($old['price']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Duration (days) *</label>
                    <input type="number" step="1" min="1" max="3650" name="duration_days" class="form-control" value="<?= htmlspecialchars($old['duration_days']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Features</label>
                <textarea name="features" class="form-control" rows="4" placeholder="Examples:&#10;Gym access only&#10;Gym + Cardio&#10;Gym + Cardio + Personal trainer"><?= htmlspecialchars($old['features']) ?></textarea>
            </div>

            <?php if ($hasStatusCol): ?>
            <div class="mb-3">
                <label class="form-label">Status *</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="mb-3 form-check">
                <input type="checkbox" name="includes_trainer" value="1" class="form-check-input" id="includes_trainer"
                    <?= $old['includes_trainer'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="includes_trainer">Includes trainer booking (personal training)</label>
            </div>

            <button type="submit" class="btn btn-dark">Save Plan</button>
            <a href="<?= BASE_URL ?>/admin/plans/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
