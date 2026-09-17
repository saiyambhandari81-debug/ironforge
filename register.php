<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/mailer.php';

if (!empty($_SESSION['member_id'])) {
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

$errors = [];
$old = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $password         = (string) ($_POST['password'] ?? '');
    $confirm          = (string) ($_POST['confirm_password'] ?? '');

    if ($old['full_name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $old['full_name'])) {
        $errors[] = 'Name cannot contain numbers.';
    }

    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strpos($old['email'], ' ') !== false) {
        $errors[] = 'Email address cannot contain spaces.';
    } else {
        $emailParts = explode('@', $old['email']);
        $domain = strtolower($emailParts[1] ?? '');
        if ($domain === '' || strpos($domain, '.') === false) {
            $errors[] = 'Please enter a valid email domain.';
        } else {
            $check = $pdo->prepare('SELECT member_id FROM members WHERE email = ? LIMIT 1');
            $check->execute([$old['email']]);
            if ($check->fetch()) {
                $errors[] = 'This email is already registered.';
            }
        }
    }

    if ($old['phone'] === '') {
        $errors[] = 'Phone is required.';
    } elseif (!preg_match('/^(97|98|96|94)\d{8}$/', $old['phone'])) {
        $errors[] = 'Phone must be 10 digits starting with 97, 98, 96, or 94.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $email = $old['email'];
        $otp = (string) random_int(100000, 999999);
        $otpHash = hash('sha256', $otp);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO members (full_name, email, phone, password_hash, join_date, status, email_verified)
                VALUES (?, ?, ?, ?, CURDATE(), 'active', 0)
            ")->execute([
                $old['full_name'],
                $email,
                $old['phone'],
                $hash,
            ]);

            $pdo->prepare("
                UPDATE email_otps
                SET used_at = NOW()
                WHERE email = ?
                  AND purpose = 'register'
                  AND used_at IS NULL
            ")->execute([$email]);

            $pdo->prepare("
                INSERT INTO email_otps (email, purpose, otp_hash, expires_at, attempts)
                VALUES (?, 'register', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)
            ")->execute([$email, $otpHash]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Register OTP failed: ' . $e->getMessage());
            $errors[] = 'Could not create your account. Please try again.';
        }

        if (!$errors) {
            $body = "Your IronForge email verification code is: {$otp}\n\n"
                . "This code expires in 15 minutes.\n"
                . "If you did not create an account, you can ignore this email.\n";

            $mail = sendGymEmail($email, 'IronForge email verification code', $body);

            $_SESSION['flash_success'] = 'Use an email inbox you can open. We sent a 6-digit code. You cannot log in until you verify.';
            if (!$mail['ok'] && isLocalHost()) {
                $_SESSION['demo_otp'] = $otp;
            } else {
                unset($_SESSION['demo_otp']);
            }

            header('Location: ' . BASE_URL . '/verify-email.php?email=' . urlencode($email));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account - IronForge Gym</title>
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
            padding: 32px 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 480px;
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
        <h1 class="h5 fw-bold mb-1">Join IronForge Gym</h1>
        <p class="text-muted small mb-0">Use an email inbox you can open. You cannot log in until verified.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger mb-4">
            <?php foreach ($errors as $e): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($e) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Full name *</label>
            <div class="input-icon">
                <i class="bi bi-person"></i>
                <input name="full_name" class="form-control"
                       placeholder="e.g. Ram Bahadur"
                       value="<?= htmlspecialchars($old['full_name']) ?>" required autofocus>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Email address *</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" class="form-control"
                       placeholder="name@example.com"
                       value="<?= htmlspecialchars($old['email']) ?>" required>
            </div>
            <div class="form-text">Use an email inbox you can open. We will send a 6-digit code. You cannot log in until verified.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone number *</label>
            <div class="input-icon">
                <i class="bi bi-telephone"></i>
                <input name="phone" class="form-control"
                       value="<?= htmlspecialchars($old['phone']) ?>"
                       placeholder="98XXXXXXXX" required>
            </div>
        </div>
        <div class="row g-2 mb-4">
            <div class="col-md-6">
                <label class="form-label">Password *</label>
                <div class="input-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 chars" required>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Confirm password *</label>
                <div class="input-icon">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-dark btn-lg w-100 fw-semibold">
            <i class="bi bi-person-plus me-1"></i> Create Free Account
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top small text-muted">
        Already registered?
        <a href="<?= BASE_URL ?>/login.php" class="fw-semibold text-dark text-decoration-none">Log in here</a>
    </div>
</div>
</body>
</html>
