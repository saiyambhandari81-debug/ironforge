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
$success = (string) ($_SESSION['flash_success'] ?? '');
$demoOtp = '';
if (isLocalHost() && !empty($_SESSION['demo_otp'])) {
    $demoOtp = (string) $_SESSION['demo_otp'];
}
unset($_SESSION['flash_success'], $_SESSION['demo_otp']);

$email = strtolower(trim((string) ($_GET['email'] ?? '')));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // Check code against SESSION only (not database)
        $result = otpSessionVerify($email, 'reset', $code);

        if (!$result['ok']) {
            $errors[] = $result['error'];
        } else {
            $userType = (string) ($result['data']['user_type'] ?? '');

            // Fallback if user_type missing in session
            if (!in_array($userType, ['admin', 'trainer', 'member'], true)) {
                $stmt = $pdo->prepare('SELECT admin_id, status FROM admins WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $admin = $stmt->fetch();
                if ($admin && ($admin['status'] ?? 'active') === 'active') {
                    $userType = 'admin';
                } else {
                    $stmt = $pdo->prepare('SELECT trainer_id, status FROM trainers WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $trainer = $stmt->fetch();
                    if ($trainer && ($trainer['status'] ?? 'active') !== 'inactive') {
                        $userType = 'trainer';
                    } else {
                        $stmt = $pdo->prepare('SELECT member_id, status FROM members WHERE email = ? LIMIT 1');
                        $stmt->execute([$email]);
                        $member = $stmt->fetch();
                        if ($member && ($member['status'] ?? 'active') === 'active') {
                            $userType = 'member';
                        }
                    }
                }
            }

            if (!in_array($userType, ['admin', 'trainer', 'member'], true)) {
                $errors[] = 'No active account found with that email.';
            } else {
                // Clear OTP; allow password change only via session
                otpSessionClear();
                $_SESSION['password_reset_ok'] = [
                    'email'     => $email,
                    'user_type' => $userType,
                    'expires'   => time() + 60, // 1 minute to set new password
                ];
                header('Location: ' . BASE_URL . '/reset-password.php');
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
    <title>Enter verification code - IronForge Gym</title>
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
        <h1 class="h5 fw-bold mb-1">Enter verification code</h1>
        <p class="text-muted small mb-0">Enter the 6-digit code sent to your email</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success mb-4">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($demoOtp !== ''): ?>
        <div class="alert alert-warning mb-4">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <span>Email could not be sent on this computer. Demo code: <strong><?= htmlspecialchars($demoOtp) ?></strong></span>
            </div>
        </div>
    <?php endif; ?>

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
        <div class="mb-3">
            <label class="form-label">Email address</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" class="form-control"
                       placeholder="name@example.com"
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label">Verification code</label>
            <div class="input-icon">
                <i class="bi bi-shield-lock"></i>
                <input type="text" name="code" class="form-control"
                       inputmode="numeric" autocomplete="one-time-code"
                       pattern="\d{6}" maxlength="6" placeholder="123456" required autofocus>
            </div>
        </div>
        <button type="submit" class="btn btn-dark btn-lg w-100 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i> Verify code
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top small text-muted">
        Didn't receive a code?
        <a href="<?= BASE_URL ?>/forgot-password.php<?= $email !== '' ? '?email=' . urlencode($email) : '' ?>" class="fw-semibold text-dark text-decoration-none">Request new code</a>
        <br class="my-1">
        <a href="<?= BASE_URL ?>/login.php" class="text-muted text-decoration-none mt-1 d-inline-block">Back to login</a>
    </div>
</div>
</body>
</html>