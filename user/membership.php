<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireMember();

$memberId = (int) $_SESSION['member_id'];
$errors = [];
$success = '';

// Current memberships
$listStmt = $pdo->prepare("
    SELECT ms.start_date, ms.expiry_date, ms.status, ms.total_price, p.plan_name, p.price
    FROM memberships ms
    JOIN plans p ON p.plan_id = ms.plan_id
    WHERE ms.member_id = ?
    ORDER BY ms.start_date DESC
");
$listStmt->execute([$memberId]);
$rows = $listStmt->fetchAll();

// Pending or active membership already?
$blockStmt = $pdo->prepare("
    SELECT membership_id, expiry_date, status
    FROM memberships
    WHERE member_id = ?
      AND (
            status = 'pending'
         OR (status = 'active' AND expiry_date >= CURDATE())
      )
    LIMIT 1
");
$blockStmt->execute([$memberId]);
$existing = $blockStmt->fetch();

// Available plans
$plans = $pdo->query("
    SELECT plan_id, plan_name, price, duration_days, features
    FROM plans
    WHERE status = 'active'
    ORDER BY price ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $planId = (int) ($_POST['plan_id'] ?? 0);

    if ($existing) {
        if ($existing['status'] === 'pending') {
            $errors[] = 'You already have a pending membership. Pay the full amount in My Payments to activate it.';
        } else {
            $errors[] = 'You already have an active membership until ' . $existing['expiry_date'] . '.';
        }
    } elseif ($planId <= 0) {
        $errors[] = 'Please select a plan.';
    } else {
        $planStmt = $pdo->prepare("
            SELECT plan_id, plan_name, price, duration_days
            FROM plans
            WHERE plan_id = ? AND status = 'active'
        ");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch();

        if (!$plan) {
            $errors[] = 'That plan is not available.';
        } else {
            $start  = date('Y-m-d');
            $expiry = date('Y-m-d', strtotime('+' . (int) $plan['duration_days'] . ' days'));

            $ins = $pdo->prepare("
                INSERT INTO memberships (member_id, plan_id, start_date, expiry_date, total_price, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([
                $memberId,
                $plan['plan_id'],
                $start,
                $expiry,
                $plan['price'],
            ]);

            $success = 'Membership request for ' . $plan['plan_name'] .
                       ' submitted. Go to My Payments and pay the full amount to activate it.';

            $listStmt->execute([$memberId]);
            $rows = $listStmt->fetchAll();
            $blockStmt->execute([$memberId]);
            $existing = $blockStmt->fetch();
        }
    }
}

$pageTitle = 'My Membership';
require ROOT_PATH . '/includes/user_header.php';
?>

<h1 class="h4 mb-3">My Membership</h1>

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

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Your memberships</div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Start</th>
                    <th>Expiry</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-muted">No membership yet. Apply for a plan below.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['plan_name']) ?></td>
                    <td><?= formatMoney($r['price']) ?></td>
                    <td><?= htmlspecialchars($r['start_date']) ?></td>
                    <td><?= htmlspecialchars($r['expiry_date']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark">Pending payment</span>
                        <?php else: ?>
                            <?= htmlspecialchars(ucfirst($r['status'])) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h2 class="h6 mb-3">Apply for a membership</h2>

    <?php if ($existing): ?>
        <?php if ($existing['status'] === 'pending'): ?>
            <p class="text-muted mb-2">You have a pending membership. Pay it to activate:</p>
            <a href="<?= BASE_URL ?>/user/payments.php" class="btn btn-dark">Go to My Payments</a>
        <?php else: ?>
            <p class="text-muted mb-0">
                You already have an active membership until
                <strong><?= htmlspecialchars($existing['expiry_date']) ?></strong>.
            </p>
        <?php endif; ?>
    <?php elseif (!$plans): ?>
        <p class="text-muted mb-0">No plans available right now.</p>
    <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Choose a plan *</label>
                <select name="plan_id" class="form-select" required>
                    <option value="">-- Select plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['plan_id'] ?>">
                            <?= htmlspecialchars($p['plan_name']) ?>
                            — <?= formatMoney($p['price']) ?>
                            / <?= (int) $p['duration_days'] ?> days
                            <?= !empty($p['features']) ? ' — ' . htmlspecialchars($p['features']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-dark">Apply membership</button>
        </form>
        <p class="small text-muted mt-2 mb-0">Membership stays pending until you pay the full amount.</p>
    <?php endif; ?>
</div>

<?php require ROOT_PATH . '/includes/user_footer.php'; ?>