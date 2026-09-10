<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $name)) {
        $errors[] = 'Name cannot contain numbers.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email.';
    }

    if (!preg_match('/^(97|98|96|94)\d{8}$/', $phone)) {
        $errors[] = 'Phone must be 10 digits and start with 97, 98, 96, or 94.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    // Check email already used
    if (!$errors) {
        $check = $pdo->prepare('SELECT member_id FROM members WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    // Save
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare(
            "INSERT INTO members (full_name, email, phone, join_date, status, password_hash)
             VALUES (?, ?, ?, CURDATE(), 'active', ?)"
        )->execute([$name, $email, $phone, $hash]);

        $_SESSION['member_id']   = $pdo->lastInsertId();
        $_SESSION['member_name'] = $name;

        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 420px;">
    <h1 class="h4 mb-3">Create account</h1>

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
            <label class="form-label">Full name</label>
            <input name="full_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input name="phone" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-dark w-100">Register</button>
    </form>

    <p class="mt-3"><a href="<?= BASE_URL ?>/login.php">Already have account? Log in</a></p>
</div>
</body>
</html>
<p class="mt-3"><a href="<?= BASE_URL ?>/login.php">Already have account? Log in</a></p>
</div>