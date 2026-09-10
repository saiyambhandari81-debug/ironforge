<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

// Already logged in?
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
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
        $stmt = $pdo->prepare('SELECT admin_id, full_name, password_hash FROM admins WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        }

        // Then member
        $stmt = $pdo->prepare('SELECT member_id, full_name, password_hash, status FROM members WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if ($member && !empty($member['password_hash']) && password_verify($password, $member['password_hash'])) {
            if (($member['status'] ?? 'active') !== 'active') {
                $errors[] = 'Your account is not active. Contact the gym.';
            } else {
                $_SESSION['member_id'] = $member['member_id'];
                $_SESSION['member_name'] = $member['full_name'];
                header('Location: ' . BASE_URL . '/user/index.php');
                exit;
            }
        } else {
            $errors[] = 'Invalid email or password.';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #f5f6f8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,.06);
            padding: 32px;
        }
        .brand {
            font-weight: 800;
            color: #0f1419;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="text-center mb-4">
        <a href="<?= BASE_URL ?>/" class="brand fs-4">IronForge Gym</a>
        <div class="text-muted mt-1">Welcome back</div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control form-control-lg"
                   value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control form-control-lg" required>
        </div>
        <button type="submit" class="btn btn-dark btn-lg w-100">Log in</button>
    </form>

    <div class="text-center mt-4 small text-muted">
        New member?
        <a href="<?= BASE_URL ?>/register.php">Create account</a>
    </div>
</div>
</body>
</html>