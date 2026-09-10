<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

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
        $errors[] = 'Please enter a valid email.';
    } else {
        $check = $pdo->prepare('SELECT member_id FROM members WHERE email = ? LIMIT 1');
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $errors[] = 'This email is already registered.';
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
        $pdo->prepare("
            INSERT INTO members (full_name, email, phone, password_hash, join_date, status)
            VALUES (?, ?, ?, ?, CURDATE(), 'active')
        ")->execute([
            $old['full_name'],
            $old['email'],
            $old['phone'],
            $hash,
        ]);

        $memberId = (int) $pdo->lastInsertId();
        $_SESSION['member_id'] = $memberId;
        $_SESSION['member_name'] = $old['full_name'];

        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account - IronForge Gym</title>
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
            max-width: 460px;
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
        <div class="text-muted mt-1">Create your account</div>
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
            <label class="form-label">Full name *</label>
            <input name="full_name" class="form-control"
                   value="<?= htmlspecialchars($old['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($old['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone *</label>
            <input name="phone" class="form-control"
                   value="<?= htmlspecialchars($old['phone']) ?>"
                   placeholder="98XXXXXXXX" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm password *</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-dark btn-lg w-100">Create free account</button>
    </form>

    <div class="text-center mt-4 small text-muted">
        Already have an account?
        <a href="<?= BASE_URL ?>/login.php">Log in</a>
    </div>
</div>
</body>
</html>