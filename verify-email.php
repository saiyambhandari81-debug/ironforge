<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/mailer.php';

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
unset($_SESSION['flash_success']);

$email = trim((string) ($_GET['email'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
}

// Resend OTP handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_code'])) {
    verifyCsrf();

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $userFound = false;
        $mStmt = $pdo->prepare('SELECT member_id, email_verified FROM members WHERE email = ? LIMIT 1');
        $mStmt->execute([$email]);
        $m = $mStmt->fetch();
        if ($m && (int) $m['email_verified'] === 0) {
            $userFound = true;
        } else {
            $tStmt = $pdo->prepare('SELECT trainer_id, email_verified FROM trainers WHERE email = ? LIMIT 1');
            $tStmt->execute([$email]);
            $t = $tStmt->fetch();
            if ($t && (int) $t['email_verified'] === 0) {
                $userFound = true;
            } else {
                $aStmt = $pdo->prepare('SELECT admin_id, email_verified FROM admins WHERE email = ? LIMIT 1');
                $aStmt->execute([$email]);
                $a = $aStmt->fetch();
                if ($a && (int) $a['email_verified'] === 0) {
                    $userFound = true;
                }
            }
        }

        if (!$userFound) {
            $errors[] = 'Account not found or email is already verified.';
        } else {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM email_otps
                 WHERE email = ? AND purpose = 'register' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)"
            );
            $countStmt->execute([$email]);
            if ((int) $countStmt->fetchColumn() >= 3) {
                $errors[] = 'Too many requests for this email. Please try again in an hour.';
            } else {
                // Invalidate unused previous register OTPs
                $pdo->prepare(
                    "UPDATE email_otps
                     SET used_at = NOW()
                     WHERE email = ? AND purpose = 'register' AND used_at IS NULL"
                )->execute([$email]);

                $otp = (string) random_int(100000, 999999);
                $otpHash = hash('sha256', $otp);

                $pdo->prepare(
                    "INSERT INTO email_otps (email, purpose, otp_hash, expires_at, attempts)
                     VALUES (?, 'register', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)"
                )->execute([$email, $otpHash]);

                $body = "Your IronForge email verification code is: {$otp}\n\n"
                    . "This code expires in 15 minutes.\n"
                    . "If you did not create an account, you can ignore this email.\n";

                $mail = sendGymEmail($email, 'IronForge email verification code', $body);
                $_SESSION['flash_success'] = 'A new verification code has been sent to your email. Check your Inbox and Spam.';
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }
    if (strlen($code) !== 6) {
        $errors[] = 'Enter the 6-digit verification code.';
    }

    if (!$errors) {
        $userType = null;
        $memberStmt = $pdo->prepare(
            'SELECT member_id, email_verified FROM members WHERE email = ? LIMIT 1'
        );
        $memberStmt->execute([$email]);
        $member = $memberStmt->fetch();

        if ($member) {
            $userType = 'member';
            $isVerified = (int) $member['email_verified'];
        } else {
            $trainerStmt = $pdo->prepare(
                'SELECT trainer_id, email_verified FROM trainers WHERE email = ? LIMIT 1'
            );
            $trainerStmt->execute([$email]);
            $trainer = $trainerStmt->fetch();
            if ($trainer) {
                $userType = 'trainer';
                $isVerified = (int) $trainer['email_verified'];
            } else {
                $adminStmt = $pdo->prepare(
                    'SELECT admin_id, email_verified FROM admins WHERE email = ? LIMIT 1'
                );
                $adminStmt->execute([$email]);
                $admin = $adminStmt->fetch();
                if ($admin) {
                    $userType = 'admin';
                    $isVerified = (int) $admin['email_verified'];
                }
            }
        }

        if (!$userType) {
            $errors[] = 'No account found for that email.';
        } elseif ($isVerified === 1) {
            unset($_SESSION['demo_otp']);
            $_SESSION['flash_success'] = 'Email already verified. You can log in.';
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        } else {
            $otpStmt = $pdo->prepare("
                SELECT id, otp_hash, expires_at, attempts
                FROM email_otps
                WHERE email = ?
                  AND purpose = 'register'
                  AND used_at IS NULL
                ORDER BY id DESC
                LIMIT 1
            ");
            $otpStmt->execute([$email]);
            $otpRow = $otpStmt->fetch();

            if (!$otpRow) {
                $errors[] = 'No active verification code for this email.';
            } elseif ((int) $otpRow['attempts'] >= 5) {
                $errors[] = 'Too many attempts. This code is locked. Please request a new code.';
            } elseif (strtotime($otpRow['expires_at']) < time()) {
                $errors[] = 'That code has expired. Please request a new code.';
            } elseif (!hash_equals($otpRow['otp_hash'], hash('sha256', $code))) {
                $newAttempts = (int) $otpRow['attempts'] + 1;
                $pdo->prepare('UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?')
                    ->execute([(int) $otpRow['id']]);

                if ($newAttempts >= 5) {
                    $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE id = ?')
                        ->execute([(int) $otpRow['id']]);
                    $errors[] = 'Incorrect code. Maximum attempts reached. This code is now locked.';
                } else {
                    $left = 5 - $newAttempts;
                    $errors[] = 'Incorrect code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
                }
            } else {
                $pdo->beginTransaction();
                try {
                    if ($userType === 'member') {
                        $pdo->prepare('UPDATE members SET email_verified = 1 WHERE email = ? AND email_verified = 0')
                            ->execute([$email]);
                    } elseif ($userType === 'trainer') {
                        $pdo->prepare('UPDATE trainers SET email_verified = 1 WHERE email = ? AND email_verified = 0')
                            ->execute([$email]);
                    } elseif ($userType === 'admin') {
                        $pdo->prepare('UPDATE admins SET email_verified = 1 WHERE email = ? AND email_verified = 0')
                            ->execute([$email]);
                    }

                    $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE id = ?')
                        ->execute([(int) $otpRow['id']]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Verify email failed: ' . $e->getMessage());
                    $errors[] = 'Could not verify your email. Please try again.';
                }

                if (!$errors) {
                    unset($_SESSION['demo_otp']);
                    $_SESSION['flash_success'] = 'Email verified successfully. You can now log in.';
                    header('Location: ' . BASE_URL . '/login.php');
                    exit;
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
    <title>Verify your email - IronForge Gym</title>
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
        <h1 class="h5 fw-bold mb-1">Verify your email</h1>
        <p class="text-muted small mb-0">Use an email inbox you can open. You cannot log in until verified. Enter the 6-digit code we sent you (check Inbox and Spam).</p>
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

    <form method="POST" id="verifyForm">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Email address</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" id="emailInput" class="form-control"
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
            <i class="bi bi-check2-circle me-1"></i> Verify email
        </button>
    </form>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 small text-muted">
        <span>Didn't receive a code?</span>
        <form method="POST" class="d-inline" id="resendForm">
            <?= csrfField() ?>
            <input type="hidden" name="email" id="resendEmail" value="<?= htmlspecialchars($email) ?>">
            <button type="submit" name="resend_code" value="1" class="btn btn-link p-0 small text-decoration-none fw-semibold text-dark" onclick="var em=document.getElementById('emailInput'); if(em && em.value) document.getElementById('resendEmail').value=em.value;">
                Resend code
            </button>
        </form>
    </div>
    <div class="text-center text-muted small mt-2">
        If you do not receive a code, the email address may be wrong or does not exist.
    </div>

    <div class="text-center mt-4 pt-3 border-top small text-muted">
        <a href="<?= BASE_URL ?>/register.php" class="fw-semibold text-dark text-decoration-none">Back to register</a>
        <span class="mx-2">·</span>
        <a href="<?= BASE_URL ?>/login.php" class="fw-semibold text-dark text-decoration-none">Log in</a>
    </div>
</div>
</body>
</html>
