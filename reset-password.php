<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

$errors = [];
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$valid = false;
$resetRow = null;

if ($token === '' || strlen($token) < 32) {
    $errors[] = 'Invalid or missing reset link.';
} else {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT * FROM password_resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $resetRow = $stmt->fetch();
    if (!$resetRow) {
        $errors[] = 'This reset link is invalid or has expired.';
    } else {
        $valid = true;
    }
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $token = trim($_POST['token'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $email = $resetRow['email'];
        $type = $resetRow['user_type'];

        if ($type === 'admin') {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE email = ?')->execute([$hash, $email]);
        } elseif ($type === 'trainer') {
            $pdo->prepare('UPDATE trainers SET password_hash = ? WHERE email = ?')->execute([$hash, $email]);
        } else {
            $pdo->prepare('UPDATE members SET password_hash = ? WHERE email = ?')->execute([$hash, $email]);
        }

        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
            ->execute([$resetRow['id']]);

        $success = 'Password updated. You can log in now.';
        $valid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container" style="max-width: 420px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 fw-bold mb-3">Set new password</h1>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-dark w-100">Go to login</a>
            <?php elseif ($valid): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="mb-3">
                        <label class="form-label">New password *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm password *</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-dark w-100">Save password</button>
                </form>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-outline-dark w-100">Request new link</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>