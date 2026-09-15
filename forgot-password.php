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

$errors = [];
$success = '';
$demoLink = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $userType = null;
        $hasPassword = false;

        $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE email = ? AND status = ? LIMIT 1');
        $stmt->execute([$email, 'active']);
        $row = $stmt->fetch();
        if ($row) {
            $userType = 'admin';
            $hasPassword = !empty($row['password_hash']);
        }

        if (!$userType) {
            $stmt = $pdo->prepare('SELECT password_hash, status FROM trainers WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && ($row['status'] ?? '') !== 'inactive') {
                $userType = 'trainer';
                $hasPassword = !empty($row['password_hash']);
            }
        }

        if (!$userType) {
            $stmt = $pdo->prepare('SELECT password_hash, status FROM members WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && ($row['status'] ?? 'active') === 'active') {
                $userType = 'member';
                $hasPassword = !empty($row['password_hash']);
            }
        }

        $success = 'If that email is registered, a reset link is ready.';

        if ($userType && $hasPassword) {
            $pdo->prepare(
                "UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL"
            )->execute([$email]);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $pdo->prepare(
                "INSERT INTO password_resets (email, user_type, token_hash, expires_at) VALUES (?, ?, ?, ?)"
            )->execute([$email, $userType, $tokenHash, $expires]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
            $demoLink = $scheme . '://' . $host . BASE_URL . '/reset-password.php?token=' . urlencode($token);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container" style="max-width: 420px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h5 fw-bold mb-1">Forgot password</h1>
            <p class="text-muted small mb-4">Enter your account email.</p>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php if ($demoLink): ?>
                    <div class="alert alert-info small">
                        <strong>Local demo link:</strong><br>
                        <a href="<?= htmlspecialchars($demoLink) ?>"><?= htmlspecialchars($demoLink) ?></a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($email) ?>" required autofocus>
                </div>
                <button type="submit" class="btn btn-dark w-100">Get reset link</button>
            </form>

            <div class="text-center mt-3 small">
                <a href="<?= BASE_URL ?>/login.php">Back to login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>