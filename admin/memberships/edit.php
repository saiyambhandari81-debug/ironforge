<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT ms.*, m.full_name AS member_name, p.plan_name, p.duration_days
    FROM memberships ms
    JOIN members m ON ms.member_id = m.member_id
    JOIN plans p ON ms.plan_id = p.plan_id
    WHERE ms.membership_id = ?
");
$stmt->execute([$id]);
$membership = $stmt->fetch();

if (!$membership) {
    header('Location: ' . BASE_URL . '/admin/memberships/?msg=notfound');
    exit;
}

$errors = [];
$old = [
    'start_date' => $membership['start_date'] ?? '',
    'status'     => $membership['status'] ?? 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['start_date'] = trim($_POST['start_date'] ?? '');
    $old['status']     = trim($_POST['status'] ?? '');

    // ===== VALIDATION =====
    $startDate = null;
    if ($old['start_date'] === '') {
        $errors[] = 'Start date is required.';
    } else {
        $startDate = DateTime::createFromFormat('Y-m-d', $old['start_date']);
        $today = new DateTime();
        $oneYearAgo = (clone $today)->modify('-1 year');

        if (!$startDate || $startDate->format('Y-m-d') !== $old['start_date']) {
            $errors[] = 'Start date must be a valid date.';
        } elseif ($startDate > $today) {
            $errors[] = 'Start date cannot be in the future.';
        } elseif ($startDate < $oneYearAgo) {
            $errors[] = 'Start date cannot be more than 1 year in the past.';
        }
    }

    if (!in_array($old['status'], ['active', 'suspended', 'cancelled'], true)) {
        $errors[] = 'Please choose a valid status.';
    }

    if (!$errors) {
        $expiryDate = clone $startDate;
        $expiryDate->modify('+' . (int) $membership['duration_days'] . ' days');

        $update = $pdo->prepare(
            "UPDATE memberships SET start_date = ?, expiry_date = ?, status = ? WHERE membership_id = ?"
        );
        $update->execute([
            $startDate->format('Y-m-d'),
            $expiryDate->format('Y-m-d'),
            $old['status'],
            $id,
        ]);

        header('Location: ' . BASE_URL . '/admin/memberships/?msg=updated');
        exit;
    }
}

$pageTitle = 'Edit Membership';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card" style="max-width: 600px;">
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

        <p class="mb-1"><strong>Member:</strong> <?= htmlspecialchars($membership['member_name']) ?></p>
        <p class="mb-3"><strong>Plan:</strong> <?= htmlspecialchars($membership['plan_name']) ?> (<?= (int)$membership['duration_days'] ?> days)</p>
        <p class="text-muted small mb-3">Member and plan cannot be changed here.</p>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?= htmlspecialchars($old['start_date']) ?>" required>
                    <div class="form-text">Expiry recalculates automatically.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="active"    <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= $old['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="cancelled" <?= $old['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-dark">Save Changes</button>
            <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>