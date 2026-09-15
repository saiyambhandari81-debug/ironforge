<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$errors = [];
$old = [
    'full_name'        => '',
    'email'            => '',
    'phone'            => '',
    'specialization'   => '',
    'experience_years' => '',
    'salary'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['full_name']        = trim($_POST['full_name'] ?? '');
    $old['email']            = trim($_POST['email'] ?? '');
    $old['phone']            = trim($_POST['phone'] ?? '');
    $old['specialization']   = trim($_POST['specialization'] ?? '');
    $old['experience_years'] = trim($_POST['experience_years'] ?? '');
    $old['salary']           = trim($_POST['salary'] ?? '');
    $password                = (string) ($_POST['password'] ?? '');
    $confirm                 = (string) ($_POST['confirm_password'] ?? '');

    // Full name
    if ($old['full_name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($old['full_name']) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    } elseif (mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Full name cannot be longer than 100 characters.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $old['full_name'])) {
        $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }

    // Email
    if ($old['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM trainers WHERE email = ?');
        $check->execute([$old['email']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'A trainer with that email already exists.';
        }
    }

    // Phone
    if ($old['phone'] === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^(97|98|96|94)\d{8}$/', $old['phone'])) {
        $errors[] = 'Phone must be 10 digits starting with 97, 98, 96, or 94.';
    }

    if ($old['specialization'] !== '' && mb_strlen($old['specialization']) > 100) {
        $errors[] = 'Specialization cannot be longer than 100 characters.';
    }

    if ($old['experience_years'] === '' || !ctype_digit($old['experience_years'])) {
        $errors[] = 'Experience must be a whole number (0 or more).';
    }

    if ($old['salary'] === '' || !is_numeric($old['salary']) || (float) $old['salary'] < 0) {
        $errors[] = 'Salary must be a valid amount (0 or more).';
    }

    // Portal password (required for trainer login)
    if ($password === '') {
        $errors[] = 'Password is required so the trainer can log in.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO trainers
                (full_name, email, phone, specialization, experience_years, salary, status, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $stmt->execute([
            $old['full_name'],
            $old['email'],
            $old['phone'],
            $old['specialization'] !== '' ? $old['specialization'] : null,
            (int) $old['experience_years'],
            (float) $old['salary'],
            $hash,
        ]);

        header('Location: ' . BASE_URL . '/admin/trainers/?msg=added');
        exit;
    }
}

$pageTitle = 'Add Trainer';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 700px;">
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
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= htmlspecialchars($old['full_name']) ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($old['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone *</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($old['phone']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Specialization</label>
                <input type="text" name="specialization" class="form-control"
                       value="<?= htmlspecialchars($old['specialization']) ?>">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Experience (years) *</label>
                    <input type="number" step="1" min="0" name="experience_years" class="form-control"
                           value="<?= htmlspecialchars($old['experience_years']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Salary (Rs./month) *</label>
                    <input type="number" step="0.01" min="0" name="salary" class="form-control"
                           value="<?= htmlspecialchars($old['salary']) ?>" required>
                </div>
            </div>

            <hr>
            <p class="text-muted small">Portal password — used on the main login page for the trainer account.</p>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm password *</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
            </div>

            <button type="submit" class="btn btn-dark">Save Trainer</button>
            <a href="<?= BASE_URL ?>/admin/trainers/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>