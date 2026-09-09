<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$transferableMemberships = $pdo->query("
    SELECT ms.membership_id, m.full_name, p.plan_name, ms.expiry_date, ms.member_id
    FROM memberships ms
    JOIN members m ON ms.member_id = m.member_id
    JOIN plans p ON ms.plan_id = p.plan_id
    WHERE ms.status = 'active' AND ms.expiry_date >= CURDATE()
    ORDER BY m.full_name
")->fetchAll();

$members = $pdo->query("SELECT member_id, full_name FROM members WHERE status = 'active' ORDER BY full_name")->fetchAll();

$errors = [];
$old = [
    'membership_id' => '',
    'to_member_id'  => '',
    'reason'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $old['membership_id'] = trim($_POST['membership_id'] ?? '');
    $old['to_member_id']  = trim($_POST['to_member_id'] ?? '');
    $old['reason']        = trim($_POST['reason'] ?? '');

    $membershipId = (int) $old['membership_id'];
    $toMemberId   = (int) $old['to_member_id'];

    $membership = null;

    // ===== VALIDATION =====

    // Membership
    if ($old['membership_id'] === '' || !ctype_digit($old['membership_id'])) {
        $errors[] = 'Please select a valid membership to transfer.';
    } else {
        $mStmt = $pdo->prepare("
            SELECT ms.*
            FROM memberships ms
            WHERE ms.membership_id = ? 
              AND ms.status = 'active' 
              AND ms.expiry_date >= CURDATE()
        ");
        $mStmt->execute([$membershipId]);
        $membership = $mStmt->fetch();
        if (!$membership) {
            $errors[] = 'That membership is not eligible for transfer.';
        }
    }

    // Recipient
    if ($old['to_member_id'] === '' || !ctype_digit($old['to_member_id'])) {
        $errors[] = 'Please select a valid recipient.';
    } else {
        $tCheck = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id = ? AND status = 'active'");
        $tCheck->execute([$toMemberId]);
        if ($tCheck->fetchColumn() == 0) {
            $errors[] = 'That recipient could not be found or is inactive.';
        }
    }

    // Cannot transfer to the same person
    if (!$errors && $membership && $toMemberId === (int) $membership['member_id']) {
        $errors[] = 'You cannot transfer a membership to the member who already owns it.';
    }

    // Recipient must not already have an active membership
    if (!$errors) {
        $activeCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM memberships 
             WHERE member_id = ? AND status = 'active' AND expiry_date >= CURDATE()"
        );
        $activeCheck->execute([$toMemberId]);
        if ($activeCheck->fetchColumn() > 0) {
            $errors[] = 'The recipient already has an active membership of their own.';
        }
    }

    // Reason length
    if ($old['reason'] !== '' && mb_strlen($old['reason']) > 1000) {
        $errors[] = 'Reason cannot be longer than 1000 characters.';
    }

    // Save request
    if (!$errors) {
        $insert = $pdo->prepare(
            "INSERT INTO transfers (membership_id, from_member_id, to_member_id, reason, status)
             VALUES (?, ?, ?, ?, 'pending')"
        );
        $insert->execute([
            $membershipId,
            $membership['member_id'],
            $toMemberId,
            $old['reason'] !== '' ? $old['reason'] : null,
        ]);

        header('Location: ' . BASE_URL . '/admin/transfers/?msg=added');
        exit;
    }
}

$pageTitle = 'Request Transfer';
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

        <?php if (!$transferableMemberships): ?>
            <div class="alert alert-warning">No active memberships are eligible for transfer right now.</div>
        <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Membership to Transfer *</label>
                <select name="membership_id" class="form-select" required>
                    <option value="">-- Select Membership --</option>
                    <?php foreach ($transferableMemberships as $m): ?>
                        <option value="<?= $m['membership_id'] ?>" <?= (string)$old['membership_id'] === (string)$m['membership_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['full_name']) ?> — <?= htmlspecialchars($m['plan_name']) ?> 
                            (expires <?= formatDate($m['expiry_date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Transfer To *</label>
                <select name="to_member_id" class="form-select" required>
                    <option value="">-- Select Recipient --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['member_id'] ?>" <?= (string)$old['to_member_id'] === (string)$m['member_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Must be an active member with no active membership of their own.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Optional"><?= htmlspecialchars($old['reason']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-dark">Submit Transfer Request</button>
            <a href="<?= BASE_URL ?>/admin/transfers/" class="btn btn-outline-secondary">Cancel</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>