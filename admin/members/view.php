<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: ' . BASE_URL . '/admin/members/?msg=notfound');
    exit;
}

$membershipStmt = $pdo->prepare(
    "SELECT ms.*, p.plan_name
     FROM memberships ms
     JOIN plans p ON ms.plan_id = p.plan_id
     WHERE ms.member_id = ?
     ORDER BY ms.start_date DESC
     LIMIT 1"
);
$membershipStmt->execute([$id]);
$membership = $membershipStmt->fetch();

$totalPaidStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE member_id = ? AND payment_status = 'completed'"
);
$totalPaidStmt->execute([$id]);
$totalPaid = $totalPaidStmt->fetchColumn();

$pageTitle = 'Member Details';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <?= htmlspecialchars($member['full_name']) ?>
                    <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($member['status']) ?></span>
                </h5>
                <dl class="row mt-3 mb-0">
                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">
                        <?= htmlspecialchars($member['email']) ?>
                        <span class="badge ms-2 bg-<?= !empty($member['email_verified']) ? 'success' : 'warning text-dark' ?>">
                            <?= !empty($member['email_verified']) ? 'Email Verified' : 'Unverified' ?>
                        </span>
                    </dd>
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= htmlspecialchars($member['phone']) ?></dd>
                    <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= htmlspecialchars($member['address'] ?: '-') ?></dd>
                    <dt class="col-sm-4">Date of Birth</dt><dd class="col-sm-8"><?= $member['date_of_birth'] ? formatDate($member['date_of_birth']) : '-' ?></dd>
                    <dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= $member['gender'] ? ucfirst($member['gender']) : '-' ?></dd>
                    <dt class="col-sm-4">Join Date</dt><dd class="col-sm-8"><?= formatDate($member['join_date']) ?></dd>
                </dl>
                <a href="<?= BASE_URL ?>/admin/members/edit.php?id=<?= $member['member_id'] ?>" class="btn btn-dark btn-sm mt-2">Edit</a>
                <a href="<?= BASE_URL ?>/admin/members/" class="btn btn-outline-secondary btn-sm mt-2">Back to List</a>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Membership Status</div>
            <div class="card-body">
                <?php if ($membership): ?>
                    <p class="mb-1"><strong><?= htmlspecialchars($membership['plan_name']) ?></strong></p>
                    <p class="mb-1 text-muted">Expires <?= formatDate($membership['expiry_date']) ?></p>
                    <span class="badge bg-<?= $membership['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($membership['status']) ?></span>
                <?php else: ?>
                    <p class="text-muted mb-0">No membership on record yet. This will populate once Membership Management (Phase 8) is built.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Payment Status</div>
            <div class="card-body">
                <p class="mb-0">Total paid to date: <strong><?= formatMoney($totalPaid) ?></strong></p>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>