<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

// Auto-reactivate trainers if applicable
autoReactivateTrainers($pdo);

// Fetch all active memberships expiring in the next 7 days
$stmt = $pdo->prepare("
    SELECT ms.membership_id, ms.start_date, ms.expiry_date, ms.status, ms.price,
           m.member_id, m.full_name AS member_name, m.email AS member_email, m.phone AS member_phone,
           p.plan_id, p.plan_name, p.duration_days
    FROM memberships ms
    JOIN members m ON ms.member_id = m.member_id
    JOIN plans p ON ms.plan_id = p.plan_id
    WHERE ms.status = 'active'
      AND ms.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY ms.expiry_date ASC, m.full_name ASC
");
$stmt->execute();
$expiringMemberships = $stmt->fetchAll();

$pageTitle = 'Memberships Expiring (Next 7 Days)';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0 fw-bold">Memberships Expiring Soon</h5>
        <div class="text-muted small">Active memberships expiring within the next 7 days</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/admin/memberships/" class="btn btn-sm btn-outline-dark">
            All Memberships
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
            <span class="fw-semibold">Action Required (<?= count($expiringMemberships) ?> expiring)</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Contact Info</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days Left</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$expiringMemberships): ?>
                <tr>
                    <td colspan="8" class="text-muted text-center py-4">
                        <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                        No active memberships are expiring in the next 7 days.
                    </td>
                </tr>
            <?php else: foreach ($expiringMemberships as $row):
                $today  = new DateTime(date('Y-m-d'));
                $expiry = new DateTime($row['expiry_date']);
                $diff   = (int) $today->diff($expiry)->format('%r%a');

                if ($diff <= 0) {
                    $badge = '<span class="badge bg-danger">Expires Today</span>';
                } elseif ($diff === 1) {
                    $badge = '<span class="badge bg-danger">Tomorrow</span>';
                } else {
                    $badge = '<span class="badge bg-warning text-dark">' . $diff . ' days left</span>';
                }
            ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/members/view.php?id=<?= $row['member_id'] ?>" class="fw-semibold text-dark text-decoration-none">
                            <?= htmlspecialchars($row['member_name']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['plan_name']) ?></span>
                    </td>
                    <td class="font-monospace text-muted"><?= formatMoney($row['price']) ?></td>
                    <td class="small">
                        <div><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($row['member_phone'] ?? '—') ?></div>
                        <div class="text-muted"><i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($row['member_email']) ?></div>
                    </td>
                    <td class="text-muted small"><?= formatDate($row['start_date']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= formatDate($row['expiry_date']) ?></div>
                    </td>
                    <td><?= $badge ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/admin/memberships/add.php?member_id=<?= $row['member_id'] ?>" class="btn btn-sm btn-dark">
                            <i class="bi bi-arrow-repeat me-1"></i> Renew
                        </a>
                        <a href="<?= BASE_URL ?>/admin/memberships/edit.php?id=<?= $row['membership_id'] ?>" class="btn btn-sm btn-outline-secondary">
                            Edit
                        </a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
