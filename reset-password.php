<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}
if (!empty($_SESSION['trainer_id'])) {
    header('Location: ' . BASE_URL . '/trainer/index.php');
    exit;
}
if (!empty($_SESSION['member_id'])) {
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

if (empty($_SESSION['password_reset_ok'])) {
    header('Location: ' . BASE_URL . '/forgot-password.php');
    exit;
}

$resetAuth = $_SESSION['password_reset_ok'];
$email = (string) ($resetAuth['email'] ?? '');
$userType = (string) ($resetAuth['user_type'] ?? '');
$sessionExpires = (int) ($resetAuth['expires'] ?? 0);

$errors = [];
$isValid = false;

if ($email === '' || !in_array($userType, ['admin', 'trainer', 'member'], true)) {
    unset($_SESSION['password_reset_ok']);
    $errors[] = 'Invalid password reset session. Please start again.';
} elseif ($sessionExpires > 0 && $sessionExpires < time()) {
    unset($_SESSION['password_reset_ok']);
    $errors[] = 'Your password reset session has expired. Please request a new code.';
} else {
    $isValid = true;
}

if ($isValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $newPassword)) {
        $errors[] = 'Password must include at least one letter.';
    }
    if (!preg_match('/[0-9]/', $newPassword)) {
        $errors[] = 'Password must include at least one number.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            if ($userType === 'admin') {
                $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE email = ?');
                $stmt->execute([$hash, $email]);
            } elseif ($userType === 'trainer') {
                $stmt = $pdo->prepare('UPDATE trainers SET password_hash = ? WHERE email = ?');
                $stmt->execute([$hash, $email]);
            } elseif ($userType === 'member') {
                $stmt = $pdo->prepare('UPDATE members SET password_hash = ? WHERE email = ?');
                $stmt->execute([$hash, $email]);
            }

            $pdo->commit();

            // OTP was only in session — clear reset authorization
            unset($_SESSION['password_reset_ok']);
            $_SESSION['flash_success'] = 'Password updated successfully. You can log in now.';
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Reset password failed: ' . $e->getMessage());
            $errors[] = 'Could not update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set new password - IronForge Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background-color: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 36px 32px;
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="text-center mb-4">
        <a href="<?= BASE_URL ?>/" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark mb-2">
            <div class="sidebar-brand p-0" style="height:auto;border:none;">
                <div class="mark" style="width:36px;height:36px;font-size:1.1rem;">IF</div>
            </div>
            <span class="fw-bold fs-4 text-dark">IronForge</span>
        </a>
        <h1 class="h5 fw-bold mb-1">Set new password</h1>
        <p class="text-muted small mb-0">Enter a secure new password for <strong><?= htmlspecialchars($email) ?></strong></p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger mb-4">
            <?php foreach ($errors as $e): ?>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($e) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($isValid): ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">New password</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="new_password" class="form-control"
                           placeholder="••••••••" required minlength="8" autofocus>
                </div>
                <div class="form-text">At least 8 characters, with at least one letter and one number.</div>
            </div>
            <div class="mb-4">
                <label class="form-label">Confirm password</label>
                <div class="input-icon">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" name="confirm_password" class="form-control"
                           placeholder="••••••••" required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn btn-dark btn-lg w-100 fw-semibold">
                <i class="bi bi-check2-circle me-1"></i> Save new password
            </button>
        </form>
    <?php else: ?>
        <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-dark w-100">
                Request new verification code
            </a>
        </div>
    <?php endif; ?>

    <div class="text-center mt-4 pt-3 border-top small text-muted">
        <a href="<?= BASE_URL ?>/login.php" class="text-muted text-decoration-none">Back to login</a>
    </div>
</div>
</body>
</html>