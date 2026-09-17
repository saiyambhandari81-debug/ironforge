<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$plans = $pdo->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();

$messages = [
    'added'       => 'Plan added successfully.',
    'updated'     => 'Plan updated successfully.',
    'activated'   => 'Plan marked active.',
    'deactivated' => 'Plan deactivated.',
    'notfound'    => 'That plan could not be found.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$pageTitle = 'Membership Plans';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">All Plans</h5>
    <a href="<?= BASE_URL ?>/admin/plans/add.php" class="btn btn-dark">+ Add Plan</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="row g-3">
<?php if (!$plans): ?>
    <div class="col-12"><p class="text-muted">No plans yet - add your first one above.</p></div>
<?php else: foreach ($plans as $plan): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title"><?= htmlspecialchars($plan['plan_name']) ?></h5>
                    <span class="badge bg-<?= $plan['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($plan['status']) ?></span>
                </div>
                <p class="fs-4 fw-semibold mb-1"><?= formatMoney($plan['price']) ?></p>
                <p class="text-muted mb-1"><?= (int) $plan['duration_days'] ?> days</p>
                <p class="mb-2">
                    <strong>Trainer:</strong>
                    <?= !empty($plan['includes_trainer']) ? 'Yes' : 'No' ?>
                </p>
                <p class="mb-4 flex-grow-1"><?= nl2br(htmlspecialchars($plan['features'] ?: 'No features listed.')) ?></p>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/admin/plans/edit.php?id=<?= $plan['plan_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/plans/toggle-status.php" data-confirm="<?= $plan['status'] === 'active' ? 'Deactivate' : 'Activate' ?> the <?= htmlspecialchars($plan['plan_name']) ?> plan?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $plan['plan_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?= $plan['status'] === 'active' ? 'danger' : 'success' ?>">
                            <?= $plan['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>