<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, m.full_name AS member_name, pl.plan_name
    FROM payments p
    JOIN members m ON p.member_id = m.member_id
    LEFT JOIN memberships ms ON p.membership_id = ms.membership_id
    LEFT JOIN plans pl ON ms.plan_id = pl.plan_id
    WHERE p.payment_id = ?
");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: ' . BASE_URL . '/admin/payments/');
    exit;
}

$pageTitle = 'Payment Receipt';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <a href="<?= BASE_URL ?>/admin/payments/" class="btn btn-outline-secondary btn-sm">Back to Payments</a>
    <button onclick="window.print()" class="btn btn-dark btn-sm">Print Receipt</button>
</div>

<div class="card mx-auto" style="max-width: 500px;">
    <div class="card-body">
        <h4 class="text-center mb-1">IronForge Gym</h4>
        <p class="text-center text-muted mb-4">Payment Receipt</p>

        <dl class="row mb-0">
            <dt class="col-6">Receipt No.</dt>
            <dd class="col-6 text-end">#<?= str_pad((string)$payment['payment_id'], 6, '0', STR_PAD_LEFT) ?></dd>
            
            <dt class="col-6">Date</dt>
            <dd class="col-6 text-end"><?= formatDate($payment['payment_date']) ?></dd>
            
            <dt class="col-6">Member</dt>
            <dd class="col-6 text-end"><?= htmlspecialchars($payment['member_name']) ?></dd>
            
            <dt class="col-6">Plan</dt>
            <dd class="col-6 text-end"><?= htmlspecialchars($payment['plan_name'] ?? '-') ?></dd>
            
            <dt class="col-6">Method</dt>
            <dd class="col-6 text-end"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></dd>
            
            <dt class="col-6">Reference</dt>
            <dd class="col-6 text-end"><?= htmlspecialchars($payment['transaction_reference'] ?: '-') ?></dd>
            
            <dt class="col-6">Status</dt>
            <dd class="col-6 text-end"><?= ucfirst($payment['payment_status']) ?></dd>
        </dl>
        
        <hr>
        
        <div class="d-flex justify-content-between fs-5 fw-semibold">
            <span>Amount Paid</span>
            <span><?= formatMoney($payment['amount']) ?></span>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>