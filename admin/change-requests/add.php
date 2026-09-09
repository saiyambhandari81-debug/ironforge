<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$members = $pdo->query("SELECT member_id, full_name FROM members WHERE status = 'active' ORDER BY full_name")->fetchAll();

$errors = [];
$old = [
    'member_id'    => '',
    'request_type' => '',
    'details'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['member_id']    = trim($_POST['member_id'] ?? '');
    $old['request_type'] = trim($_POST['request_type'] ?? '');
    $old['details']      = trim($_POST['details'] ?? '');

    $memberId = (int) $old['member_id'];

    // ===== VALIDATION =====

    if ($old['member_id'] === '' || !ctype_digit($old['member_id'])) {
        $errors[] = 'Please select a valid member.';
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id = ? AND status = 'active'");
        $check->execute([$memberId]);
        if ($check->fetchColumn() == 0) {
            $errors[] = 'That member could not be found or is inactive.';
        }
    }

    if (!in_array($old['request_type'], ['plan_change', 'info_change', 'trainer_change', 'other'], true)) {
        $errors[] = 'Please choose a valid request type.';
    }

    if ($old['details'] === '') {
        $errors[] = 'Please describe what is being requested.';
    } elseif (mb_strlen($old['details']) < 5) {
        $errors[] = 'Details must be at least 5 characters.';
    } elseif (mb_strlen($old['details']) > 2000) {
        $errors[] = 'Details cannot be longer than 2000 characters.';
    }

    if (!$errors) {
        $insert = $pdo->prepare(
            "INSERT INTO change_requests (member_id, request_type, details, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $insert->execute([
            $memberId,
            $old['request_type'],
            $old['details']
        ]);

        header('Location: ' . BASE_URL . '/admin/change-requests/?msg=added');
        exit;
    }
}

$pageTitle = 'New Change Request';
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

        <?php if (!$members): ?>
            <div class="alert alert-warning">
                You need at least one active member first. 
                <a href="<?= BASE_URL ?>/admin/members/add.php">Add one here</a>.
            </div>
        <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Member *</label>
                <select name="member_id" class="form-select" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['member_id'] ?>" <?= (string)$old['member_id'] === (string)$m['member_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Request Type *</label>
                <select name="request_type" class="form-select" required>
                    <option value="">-- Select Type --</option>
                    <option value="plan_change"    <?= $old['request_type'] === 'plan_change' ? 'selected' : '' ?>>Membership Plan Change</option>
                    <option value="info_change"    <?= $old['request_type'] === 'info_change' ? 'selected' : '' ?>>Personal Information Change</option>
                    <option value="trainer_change" <?= $old['request_type'] === 'trainer_change' ? 'selected' : '' ?>>Trainer Change</option>
                    <option value="other"          <?= $old['request_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Details *</label>
                <textarea name="details" class="form-control" rows="4" 
                          placeholder="What exactly is being requested?"><?= htmlspecialchars($old['details']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-dark">Submit Request</button>
            <a href="<?= BASE_URL ?>/admin/change-requests/" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>