<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

// Already logged in?
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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        // Try admin first
        $stmt = $pdo->prepare(
            'SELECT admin_id, full_name, role, password_hash, status FROM admins WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (
            $admin
            && ($admin['status'] ?? 'active') === 'active'
            && password_verify($password, $admin['password_hash'])
        ) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role']; // needed for refund/transfer approve
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        }

        // Try trainer next
        $stmt = $pdo->prepare(
            'SELECT trainer_id, full_name, password_hash, status FROM trainers WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $trainer = $stmt->fetch();

        if (
            $trainer
            && !empty($trainer['password_hash'])
            && password_verify($password, $trainer['password_hash'])
        ) {
            if (($trainer['status'] ?? 'active') === 'inactive') {
                $errors[] = 'Your account is not active. Contact the gym.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
                unset($_SESSION['member_id'], $_SESSION['member_name']);
                $_SESSION['trainer_id']   = $trainer['trainer_id'];
                $_SESSION['trainer_name'] = $trainer['full_name'];
                header('Location: ' . BASE_URL . '/trainer/index.php');
                exit;
            }
        }

        // Then member (only if not an inactive trainer error)
        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'SELECT member_id, full_name, password_hash, status FROM members WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $member = $stmt->fetch();

            if (
                $member
                && !empty($member['password_hash'])
                && password_verify($password, $member['password_hash'])
            ) {
                if (($member['status'] ?? 'active') !== 'active') {
                    $errors[] = 'Your account is not active. Contact the gym.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['member_id']   = $member['member_id'];
                    $_SESSION['member_name'] = $member['full_name'];
                    header('Location: ' . BASE_URL . '/user/index.php');
                    exit;
                }
            } else {
                $errors[] = 'Invalid email or password.';
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
    <title>Log in - IronForge Gym</title>
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
        <h1 class="h5 fw-bold mb-1">Welcome back</h1>
        <p class="text-muted small mb-0">Sign in to your member, trainer, or admin account</p>
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
        <div class="mb-3">
            <label class="form-label">Email address</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" class="form-control"
                       placeholder="name@example.com"
                       value="<?= htmlspecialchars($email) ?>" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label mb-0">Password</label>
            </div>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" class="btn btn-dark btn-lg w-100 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Log in
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top small text-muted">
        New to IronForge?
        <a href="<?= BASE_URL ?>/register.php" class="fw-semibold text-dark text-decoration-none">Create account</a>
    </div>
</div>
</body>
</html>