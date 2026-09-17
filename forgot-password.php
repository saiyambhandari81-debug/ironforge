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

$genericMessage = 'If that email is registered, check your email.';
$waitMessage = 'Please wait before trying again.';

/**
 * Demo reset URL is shown only on a local host (any port).
 */
function isLocalPasswordResetHost(): bool
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $hostname = preg_replace('/:\d+$/', '', $host);

    return in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)
        || in_array($host, ['localhost', 'localhost:8080', '127.0.0.1', '127.0.0.1:8080'], true);
}

function forgotPasswordClientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (strlen($ip) > 45) {
        $ip = substr($ip, 0, 45);
    }
    return $ip;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $ip = forgotPasswordClientIp();
        $ipLimited = false;
        $emailLimited = false;

        try {
            $ipCount = $pdo->prepare(
                "SELECT COUNT(*) FROM password_reset_attempts
                 WHERE ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)"
            );
            $ipCount->execute([$ip]);
            if ((int) $ipCount->fetchColumn() >= 10) {
                $ipLimited = true;
            }

            $logAttempt = $pdo->prepare(
                'INSERT INTO password_reset_attempts (ip, email) VALUES (?, ?)'
            );
            $logAttempt->execute([$ip, $email]);
        } catch (PDOException $e) {
            // Attempts table missing: skip IP throttle until migration is run.
        }

        if ($ipLimited) {
            $success = $waitMessage;
        } else {
            try {
                $emailCount = $pdo->prepare(
                    "SELECT COUNT(*) FROM password_resets
                     WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)"
                );
                $emailCount->execute([$email]);
                if ((int) $emailCount->fetchColumn() >= 3) {
                    $emailLimited = true;
                }
            } catch (PDOException $e) {
                $emailLimited = false;
            }

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

            $success = $genericMessage;

            if (!$emailLimited && $userType && $hasPassword) {
                $pdo->prepare(
                    'UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL'
                )->execute([$email]);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', time() + 900);

                $pdo->prepare(
                    'INSERT INTO password_resets (email, user_type, token_hash, expires_at) VALUES (?, ?, ?, ?)'
                )->execute([$email, $userType, $tokenHash, $expires]);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
                $resetUrl = $scheme . '://' . $host . BASE_URL . '/reset-password.php?token=' . urlencode($token);

                @mail(
                    $email,
                    'IronForge password reset',
                    "Use this link to reset your password (valid for 15 minutes):\n" . $resetUrl
                );

                if (isLocalPasswordResetHost()) {
                    $demoLink = $resetUrl;
                }
            }
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
                <div class="alert <?= $success === $waitMessage ? 'alert-warning' : 'alert-success' ?>"><?= htmlspecialchars($success) ?></div>
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
