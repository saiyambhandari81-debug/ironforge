<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

// If already logged in, send to the right place
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}
if (function_exists('isMemberLoggedIn') && isMemberLoggedIn()) {
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        // 1) Try admin
        $stmt = $pdo->prepare(
            'SELECT admin_id, full_name, role, password_hash, status FROM admins WHERE email = ?'
        );
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && $admin['status'] === 'active' && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        }

        // 2) Try member
        $stmt = $pdo->prepare(
            'SELECT member_id, full_name, password_hash, status FROM members WHERE email = ?'
        );
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if (
            $member
            && !empty($member['password_hash'])
            && password_verify($password, $member['password_hash'])
        ) {
            if ($member['status'] !== 'active') {
                $error = 'Your account is inactive. Please contact the gym.';
            } else {
                session_regenerate_id(true);
                $_SESSION['member_id']   = $member['member_id'];
                $_SESSION['member_name'] = $member['full_name'];
                header('Location: ' . BASE_URL . '/user/index.php');
                exit;
            }
        } else {
            if ($error === '') {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
            <div class="card-body p-4">
                <h1 class="h4 mb-3 text-center">IronForge Gym</h1>
                <p class="text-muted text-center mb-4">Log in to continue</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-dark w-100">Log In</button>
                </form>

                <p class="text-center text-muted mt-3 mb-0">
                    New member? <a href="<?= BASE_URL ?>/register.php">Create account</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>