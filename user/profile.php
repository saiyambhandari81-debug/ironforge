<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];
$errors = [];
$success = '';

$stmt = $pdo->prepare('SELECT full_name, email, phone, address, join_date FROM members WHERE member_id = ?');
$stmt->execute([$memberId]);
$m = $stmt->fetch();

$old = [
    'full_name' => $m['full_name'],
    'phone'     => $m['phone'],
    'address'   => $m['address'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['address']   = trim($_POST['address'] ?? '');

    if ($old['full_name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $old['full_name'])) {
        $errors[] = 'Name cannot contain numbers.';
    } elseif (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }

    if ($old['phone'] === '') {
        $errors[] = 'Phone is required.';
    } elseif (!preg_match('/^(97|98|96|94)\d{8}$/', $old['phone'])) {
        $errors[] = 'Phone must be 10 digits starting with 97, 98, 96, or 94.';
    }

    if ($old['address'] !== '' && mb_strlen($old['address']) > 255) {
        $errors[] = 'Address is too long.';
    }

    if (!$errors) {
        $pdo->prepare(
            'UPDATE members SET full_name = ?, phone = ?, address = ? WHERE member_id = ?'
        )->execute([
            $old['full_name'],
            $old['phone'],
            $old['address'] !== '' ? $old['address'] : null,
            $memberId,
        ]);
        $_SESSION['member_name'] = $old['full_name'];
        $success = 'Profile updated successfully.';
        $m['full_name'] = $old['full_name'];
        $m['phone'] = $old['phone'];
        $m['address'] = $old['address'];
    }
}

$pageTitle = 'My Profile';
require ROOT_PATH . '/includes/user_header.php';
?>

<h1 class="h4 mb-3">My Profile</h1>

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

<div class="card p-4" style="max-width: 520px;">
    <p class="text-muted small">Email (cannot change): <strong><?= htmlspecialchars($m['email']) ?></strong></p>
    <p class="text-muted small">Joined: <?= htmlspecialchars($m['join_date']) ?></p>

    <form method="POST">
        <?= csrfField() ?>
        <div class="mb-3">
            <label class="form-label">Full name *</label>
            <input name="full_name" class="form-control" value="<?= htmlspecialchars($old['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone *</label>
            <input name="phone" class="form-control" value="<?= htmlspecialchars($old['phone']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Address</label>
            <input name="address" class="form-control" value="<?= htmlspecialchars($old['address']) ?>">
        </div>
        <button type="submit" class="btn btn-dark">Save changes</button>
        <a href="<?= BASE_URL ?>/user/change-password.php" class="btn btn-outline-secondary">Change password</a>
    </form>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>