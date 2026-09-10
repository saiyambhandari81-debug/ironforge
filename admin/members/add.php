<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$errors = [];
$old = [
    'full_name'     => '',
    'email'         => '',
    'phone'         => '',
    'address'       => '',
    'date_of_birth' => '',
    'gender'        => '',
    'join_date'     => date('Y-m-d'),
];

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

    // Full name
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
        $check = $pdo->prepare('SELECT COUNT(*) FROM members WHERE email = ?');
        $check->execute([$old['email']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'A member with that email already exists.';
        }
    }

    // Phone (Nepal)
    if ($old['phone'] === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^(97|98|96|94)\d{8}$/', $old['phone'])) {
        $errors[] = 'Phone number must be exactly 10 digits and start with 97, 98, 96, or 94.';
    }

    // Address
    if ($old['address'] !== '' && mb_strlen($old['address']) > 255) {
        $errors[] = 'Address cannot be longer than 255 characters.';
    }

    // Date of birth
    if ($old['date_of_birth'] !== '') {
        $dob = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
        $today = new DateTime('today');

        if (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth']) {
            $errors[] = 'Please enter a valid date of birth.';
        } elseif ($dob > $today) {
            $errors[] = 'Date of birth cannot be in the future.';
        } else {
            $age = $dob->diff($today)->y;
            if ($age < 16) {
                $errors[] = 'Member must be at least 16 years old to join.';
            }
            if ($age > 100) {
                $errors[] = 'Please enter a realistic date of birth.';
            }
        }
    }

    // Gender
    if ($old['gender'] !== '' && !in_array($old['gender'], ['male', 'female', 'other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }

    // Join date
    if ($old['join_date'] === '') {
        $errors[] = 'Join date is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $old['join_date'])
        || DateTime::createFromFormat('Y-m-d', $old['join_date'])->format('Y-m-d') !== $old['join_date']) {
        $errors[] = 'Please enter a valid join date.';
    } elseif ($old['join_date'] > date('Y-m-d')) {
        $errors[] = 'Join date cannot be in the future.';
    }

    // Password (required so they can use member login)
    if ($password === '') {
        $errors[] = 'Password is required so the member can log in to the member portal.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO members
                (full_name, email, phone, address, date_of_birth, gender, join_date, status, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)"
        );
        $stmt->execute([
            $old['full_name'],
            $old['email'],
            $old['phone'],
            $old['address'] !== '' ? $old['address'] : null,
            $old['date_of_birth'] !== '' ? $old['date_of_birth'] : null,
            $old['gender'] !== '' ? $old['gender'] : null,
            $old['join_date'],
            $hash,
        ]);

        header('Location: ' . BASE_URL . '/admin/members/?msg=added');
        exit;
    }
}

$pageTitle = 'Add Member';
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

        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= htmlspecialchars($old['full_name']) ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($old['email']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone *</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($old['phone']) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control"
                       value="<?= htmlspecialchars($old['address']) ?>">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control"
                           value="<?= htmlspecialchars($old['date_of_birth']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Not specified --</option>
                        <option value="male" <?= $old['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $old['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= $old['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Join Date *</label>
                <input type="date" name="join_date" class="form-control"
                       value="<?= htmlspecialchars($old['join_date']) ?>" required>
            </div>

            <hr>
            <p class="text-muted small">This password is for the <strong>member portal</strong> login.</p>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm password *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>

            <button type="submit" class="btn btn-dark">Save Member</button>
            <a href="<?= BASE_URL ?>/admin/members/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>