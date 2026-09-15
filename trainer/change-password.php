<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireTrainer();

$trainerId = (int) $_SESSION['trainer_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    // Server-side validation
    if ($current === '') {
        $errors[] = 'Current password is required.';
    }

    if ($new === '') {
        $errors[] = 'New password is required.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }

    if ($confirm === '') {
        $errors[] = 'Please confirm your new password.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirm password do not match.';
    }

    if ($current !== '' && $new !== '' && $current === $new) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT password_hash FROM trainers WHERE trainer_id = ?');
        $stmt->execute([$trainerId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['password_hash'])) {
            $errors[] = 'Password is not set for this trainer account. Please contact an administrator.';
        } elseif (!password_verify($current, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE trainers SET password_hash = ? WHERE trainer_id = ?')
                ->execute([$hash, $trainerId]);
            $success = 'Password changed successfully.';
        }
    }
}

$pageTitle = 'Change Password';
require_once ROOT_PATH . '/includes/trainer_header.php';
?>

<div class="row">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0 fw-bold">Change Password</h1>
            <a href="<?= BASE_URL ?>/trainer/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please fix the following errors:</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" autocomplete="off">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">New Password *</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required placeholder="At least 6 characters">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required placeholder="Re-enter new password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark">
                            <i class="bi bi-key-fill me-1"></i> Update Password
                        </button>
                        <a href="<?= BASE_URL ?>/trainer/index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/trainer_footer.php'; ?>
