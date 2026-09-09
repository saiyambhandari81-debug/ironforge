<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
$pageTitle = 'Dashboard'; // change per page
require ROOT_PATH . '/includes/user_header.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    // Load current hash
    $stmt = $pdo->prepare('SELECT password_hash FROM members WHERE member_id = ?');
    $stmt->execute([$memberId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['password_hash'])) {
        $errors[] = 'Password is not set for this account.';
    } elseif (!password_verify($current, $row['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New password and confirm password do not match.';
    }
    if ($current !== '' && $new !== '' && $current === $new) {
        $errors[] = 'New password must be different from the current password.';
    }

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE members SET password_hash = ? WHERE member_id = ?')
            ->execute([$hash, $memberId]);
        $success = 'Password changed successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password - IronForge Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= BASE_URL ?>/user/index.php">IronForge</a>
    <div class="d-flex gap-3 flex-wrap">
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/index.php">Dashboard</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/profile.php">Profile</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/user/change-password.php">Password</a>
        <a class="nav-link text-white" href="<?= BASE_URL ?>/logout.php">Log out</a>
    </div>
</nav>

<div class="container py-4" style="max-width: 480px;">
    <h1 class="h4 mb-3">Change Password</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Current password *</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New password *</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm new password *</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-dark">Update password</button>
            <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-outline-secondary">Back</a>
        </form>
    </div>
</div>
<?php require ROOT_PATH . '/includes/user_footer.php'; ?>