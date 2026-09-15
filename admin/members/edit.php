<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: ' . BASE_URL . '/admin/members/?msg=notfound');
    exit;
}

$errors = [];
$old = [
    'full_name'     => $member['full_name'],
    'email'         => $member['email'],
    'phone'         => $member['phone'],
    'address'       => $member['address'],
    'date_of_birth' => $member['date_of_birth'],
    'gender'        => $member['gender'],
    'join_date'     => $member['join_date'],
];

$hasPassword = !empty($member['password_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['full_name']     = trim($_POST['full_name'] ?? '');
    $old['email']         = trim($_POST['email'] ?? '');
    $old['phone']         = trim($_POST['phone'] ?? '');
    $old['address']       = trim($_POST['address'] ?? '');
    $old['date_of_birth'] = trim($_POST['date_of_birth'] ?? '');
    $old['gender']        = trim($_POST['gender'] ?? '');
    $old['join_date']     = trim($_POST['join_date'] ?? '');
    $password             = (string) ($_POST['password'] ?? '');
    $confirm              = (string) ($_POST['confirm_password'] ?? '');

    // ====================== VALIDATION ======================

    // Full Name
    if ($old['full_name'] === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($old['full_name']) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    } elseif (mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Full name cannot be longer than 100 characters.';
    } elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $old['full_name'])) {
        $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes. Numbers are not allowed.';
    }

    // Email
    if ($old['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ? AND member_id != ?");
        $check->execute([$old['email'], $id]);
        if ($check->fetchColumn() > 0) {
            $errors[] = 'A member with that email already exists.';
        }
    }

    // Phone (Nepal - exactly 10 digits)
    if ($old['phone'] === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^(97|98|96|94)\d{8}$/', $old['phone'])) {
        $errors[] = 'Phone number must be exactly 10 digits and start with 97, 98, 96, or 94.';
    }

    // Address
    if ($old['address'] !== '' && mb_strlen($old['address']) > 255) {
        $errors[] = 'Address cannot be longer than 255 characters.';
    }

    // Date of Birth
    if ($old['date_of_birth'] !== '') {
        $dob = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
        $today = new DateTime();

        if (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth']) {
            $errors[] = 'Date of birth is not a valid date.';
        } elseif ($dob > $today) {
            $errors[] = 'Date of birth cannot be in the future.';
        } else {
            $age = $today->diff($dob)->y;
            if ($age < 10) {
                $errors[] = 'Member must be at least 10 years old.';
            }
            if ($age > 100) {
                $errors[] = 'Please enter a realistic date of birth.';
            }
        }
    }

    // Gender
    if ($old['gender'] !== '' && !in_array($old['gender'], ['male', 'female', 'other'], true)) {
        $errors[] = 'Please choose a valid gender option.';
    }

    // Join Date
    if ($old['join_date'] === '') {
        $errors[] = 'Join date is required.';
    } else {
        $join = DateTime::createFromFormat('Y-m-d', $old['join_date']);
        $today = new DateTime();
        $oneYearAgo = (clone $today)->modify('-1 year');

        if (!$join || $join->format('Y-m-d') !== $old['join_date']) {
            $errors[] = 'Join date is not a valid date.';
        } elseif ($join > $today) {
            $errors[] = 'Join date cannot be in the future.';
        } elseif ($join < $oneYearAgo) {
            $errors[] = 'Join date cannot be more than 1 year in the past.';
        }
    }

    // Optional portal password
    if ($password !== '') {
        if (strlen($password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'New password and confirm password do not match.';
        }
    }

    if (!$errors) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare(
                "UPDATE members SET
                    full_name = ?, email = ?, phone = ?, address = ?,
                    date_of_birth = ?, gender = ?, join_date = ?, password_hash = ?
                 WHERE member_id = ?"
            );
            $update->execute([
                $old['full_name'],
                $old['email'],
                $old['phone'],
                $old['address'] !== '' ? $old['address'] : null,
                $old['date_of_birth'] !== '' ? $old['date_of_birth'] : null,
                $old['gender'] !== '' ? $old['gender'] : null,
                $old['join_date'],
                $hash,
                $id,
            ]);
        } else {
            $update = $pdo->prepare(
                "UPDATE members SET
                    full_name = ?, email = ?, phone = ?, address = ?,
                    date_of_birth = ?, gender = ?, join_date = ?
                 WHERE member_id = ?"
            );
            $update->execute([
                $old['full_name'],
                $old['email'],
                $old['phone'],
                $old['address'] !== '' ? $old['address'] : null,
                $old['date_of_birth'] !== '' ? $old['date_of_birth'] : null,
                $old['gender'] !== '' ? $old['gender'] : null,
                $old['join_date'],
                $id,
            ]);
        }

        header('Location: ' . BASE_URL . '/admin/members/?msg=updated');
        exit;
    }
}

$pageTitle = 'Edit Member';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($old['full_name']) ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($old['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone *</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($old['phone']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($old['address'] ?? '') ?>">
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Not specified --</option>
                        <option value="male" <?= $old['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $old['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $old['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Join Date *</label>
                    <input type="date" name="join_date" class="form-control" value="<?= htmlspecialchars($old['join_date']) ?>" required>
                </div>
            </div>

            <hr class="my-4">
            <h6 class="fw-bold mb-1">Portal password</h6>
            <p class="text-muted small mb-3">
                <?php if ($hasPassword): ?>
                    Password is already set. Leave blank to keep the current password. Fill only if you want to change it.
                <?php else: ?>
                    <span class="text-danger fw-semibold">No password set — this member cannot log in until you set one.</span>
                <?php endif; ?>
            </p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><?= $hasPassword ? 'New password' : 'Password' ?></label>
                    <input type="password" name="password" class="form-control" minlength="6"
                           placeholder="<?= $hasPassword ? 'Leave blank to keep current' : 'Set login password' ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6"
                           placeholder="Re-enter if setting password">
                </div>
            </div>

            <button type="submit" class="btn btn-dark">Save Changes</button>
            <a href="<?= BASE_URL ?>/admin/members/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>