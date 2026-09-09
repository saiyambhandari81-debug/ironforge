<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$members = $pdo->query("SELECT member_id, full_name FROM members WHERE status = 'active' ORDER BY full_name")->fetchAll();
$plans   = $pdo->query("SELECT plan_id, plan_name, price, duration_days FROM plans WHERE status = 'active' ORDER BY price")->fetchAll();

$errors = [];
$old = [
    'member_id'  => '',
    'plan_id'    => '',
    'start_date' => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['member_id']  = trim($_POST['member_id'] ?? '');
    $old['plan_id']    = trim($_POST['plan_id'] ?? '');
    $old['start_date'] = trim($_POST['start_date'] ?? '');

    $member = null;
    $plan   = null;

    // ===== VALIDATION =====

    // Member
    if ($old['member_id'] === '' || !ctype_digit($old['member_id'])) {
        $errors[] = 'Please select a valid member.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ? AND status = 'active'");
        $stmt->execute([$old['member_id']]);
        $member = $stmt->fetch();
        if (!$member) {
            $errors[] = 'That member could not be found or is inactive.';
        }
    }

    // Plan
    if ($old['plan_id'] === '' || !ctype_digit($old['plan_id'])) {
        $errors[] = 'Please select a valid plan.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE plan_id = ? AND status = 'active'");
        $stmt->execute([$old['plan_id']]);
        $plan = $stmt->fetch();
        if (!$plan) {
            $errors[] = 'That plan could not be found or is inactive.';
        }
    }

    // Start Date
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

    // Prevent overlapping active membership
    if (!$errors && $member) {
        $activeCheck = $pdo->prepare(
            "SELECT expiry_date FROM memberships
             WHERE member_id = ? AND status = 'active' AND expiry_date >= CURDATE()
             ORDER BY expiry_date DESC LIMIT 1"
        );
        $activeCheck->execute([$member['member_id']]);
        $existing = $activeCheck->fetch();
        if ($existing) {
            $errors[] = $member['full_name'] . ' already has an active membership until ' . formatDate($existing['expiry_date']) . '.';
        }
    }

    // Save
    if (!$errors) {
        $expiryDate = clone $startDate;
        $expiryDate->modify('+' . (int) $plan['duration_days'] . ' days');

        $stmt = $pdo->prepare(
            "INSERT INTO memberships (member_id, plan_id, start_date, expiry_date, total_price, status)
             VALUES (?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $member['member_id'],
            $plan['plan_id'],
            $startDate->format('Y-m-d'),
            $expiryDate->format('Y-m-d'),
            $plan['price'],
        ]);

        header('Location: ' . BASE_URL . '/admin/memberships/?msg=added');
        exit;
    }
}

$pageTitle = 'Add Membership';
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

        <?php if (!$members): ?>
            <div class="alert alert-warning">
                You need at least one active member. 
                <a href="<?= BASE_URL ?>/admin/members/add.php">Add one here</a>.
            </div>
        <?php elseif (!$plans): ?>
            <div class="alert alert-warning">
                You need at least one active plan. 
                <a href="<?= BASE_URL ?>/admin/plans/add.php">Add one here</a>.
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
                <label class="form-label">Plan *</label>
                <select name="plan_id" id="planSelect" class="form-select" required>
                    <option value="">-- Select Plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['plan_id'] ?>" 
                                data-duration="<?= (int)$p['duration_days'] ?>"
                                <?= (string)$old['plan_id'] === (string)$p['plan_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['plan_name']) ?> - <?= formatMoney($p['price']) ?> (<?= (int)$p['duration_days'] ?> days)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Start Date *</label>
                <input type="date" name="start_date" id="startDate" class="form-control" 
                       value="<?= htmlspecialchars($old['start_date']) ?>" required>
                <div class="form-text" id="expiryPreview"></div>
            </div>

            <button type="submit" class="btn btn-dark">Save Membership</button>
            <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>