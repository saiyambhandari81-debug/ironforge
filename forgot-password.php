<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/otp_session.php';

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
$email = trim((string) ($_GET['email'] ?? ''));

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
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        // IP rate limit: max 10 per hour (table optional)
        $ip = forgotPasswordClientIp();
        try {
            $ipCount = $pdo->prepare(
                "SELECT COUNT(*) FROM password_reset_attempts
                 WHERE ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)"
            );
            $ipCount->execute([$ip]);
            if ((int) $ipCount->fetchColumn() >= 10) {
                $errors[] = 'Too many attempts from this IP. Please try again later.';
            }

            $logAttempt = $pdo->prepare(
                'INSERT INTO password_reset_attempts (ip, email) VALUES (?, ?)'
            );
            $logAttempt->execute([$ip, $email]);
        } catch (PDOException $e) {
            // Table missing — continue
        }

        // Session rate limit: max 3 reset OTPs per email per hour
        if (empty($errors) && otpSessionTooManyRequests($email, 'reset')) {
            $errors[] = 'Too many password reset requests for this email. Please try again in an hour.';
        }

        // Lookup: admin → trainer → member
        if (empty($errors)) {
            $userType = null;

            $stmt = $pdo->prepare(
                'SELECT admin_id, status, email_verified FROM admins WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin) {
                if (($admin['status'] ?? 'active') !== 'active') {
                    $errors[] = 'Your account is not active. Contact the gym.';
                } elseif (isset($admin['email_verified']) && (int) $admin['email_verified'] === 0) {
                    $errors[] = 'Please verify your email first.';
                } else {
                    $userType = 'admin';
                }
            } else {
                $stmt = $pdo->prepare(
                    'SELECT trainer_id, status, email_verified FROM trainers WHERE email = ? LIMIT 1'
                );
                $stmt->execute([$email]);
                $trainer = $stmt->fetch();

                if ($trainer) {
                    if (($trainer['status'] ?? 'active') === 'inactive') {
                        $errors[] = 'Your account is not active. Contact the gym.';
                    } elseif (isset($trainer['email_verified']) && (int) $trainer['email_verified'] === 0) {
                        $errors[] = 'Please verify your email first.';
                    } else {
                        $userType = 'trainer';
                    }
                } else {
                    $stmt = $pdo->prepare(
                        'SELECT member_id, status, email_verified FROM members WHERE email = ? LIMIT 1'
                    );
                    $stmt->execute([$email]);
                    $member = $stmt->fetch();

                    if ($member) {
                        if (($member['status'] ?? 'active') !== 'active') {
                            $errors[] = 'Your account is not active. Contact the gym.';
                        } elseif (isset($member['email_verified']) && (int) $member['email_verified'] === 0) {
                            $errors[] = 'Please verify your email first.';
                        } else {
                            $userType = 'member';
                        }
                    } else {
                        $errors[] = 'No account found with that email.';
                    }
                }
            }

            if (empty($errors) && $userType !== null) {
                // OTP only in session — not in database
                otpSessionLogRequest($email, 'reset');
                $otp = otpSessionCreate($email, 'reset', $userType);

                $mail = sendPasswordResetOtp($email, $otp);

                $_SESSION['flash_success'] = 'A 6-digit verification code has been sent to your email.';
                if (!$mail['ok'] && isLocalHost()) {
                    $_SESSION['demo_otp'] = $otp;
                } else {
                    unset($_SESSION['demo_otp']);
                }

                header('Location: ' . BASE_URL . '/verify-reset-otp.php?email=' . urlencode($email));
                exit;
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
        <h1 class="h5 fw-bold mb-1">Forgot password</h1>
        <p class="text-muted small mb-0">Enter your registered email to receive a 6-digit verification code</p>
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

    <form method="POST">
        <?= csrfField() ?>
        <div class="mb-4">
            <label class="form-label">Email address</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" class="form-control"
                       placeholder="name@example.com"
                       value="<?= htmlspecialchars($email) ?>" required autofocus>
            </div>
            <div class="form-text">We'll send a 6-digit code valid for 1 minute.</div>
        </div>
        <button type="submit" class="btn btn-dark btn-lg w-100 fw-semibold">
            <i class="bi bi-send me-1"></i> Send reset code
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top small text-muted">
        Remember your password?
        <a href="<?= BASE_URL ?>/login.php" class="fw-semibold text-dark text-decoration-none">Log in</a>
    </div>
</div>
</body>
</html>