<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireLogin();

['range' => $range, 'start' => $startDate, 'end' => $endDate] = resolveDateRange();

$revenue = calculateRevenue($pdo, $startDate, $endDate);

$pageTitle = 'Revenue';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Revenue</h5>
</div>

<div class="btn-group mb-3" role="group">
    <a href="?range=today" class="btn btn-<?= $range === 'today' ? 'dark' : 'outline-dark' ?>">Today</a>
    <a href="?range=week" class="btn btn-<?= $range === 'week' ? 'dark' : 'outline-dark' ?>">This Week</a>
    <a href="?range=month" class="btn btn-<?= $range === 'month' ? 'dark' : 'outline-dark' ?>">This Month</a>
    <a href="?range=year" class="btn btn-<?= $range === 'year' ? 'dark' : 'outline-dark' ?>">This Year</a>
    <a href="?range=custom" class="btn btn-<?= $range === 'custom' ? 'dark' : 'outline-dark' ?>">Custom</a>
</div>

<?php if ($range === 'custom'): ?>
<form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="range" value="custom">
    <div class="col-auto">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="col-auto">
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-dark">Apply</button>
    </div>
</form>
<?php endif; ?>

<p class="text-muted"><?= formatDate($startDate) ?> – <?= formatDate($endDate) ?></p>

<div class="card mb-3">
    <div class="card-body text-center">
        <div class="text-muted">Total Revenue</div>
        <div class="display-6 fw-semibold text-success"><?= formatMoney($revenue['total']) ?></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">By Source</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Membership Payments</span>
                    <strong><?= formatMoney($revenue['membership_revenue']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Other Gym Services</span>
                    <strong><?= formatMoney($revenue['other_revenue']) ?></strong>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">By Payment Method</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Cash</span>
                    <strong><?= formatMoney($revenue['cash_total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Card</span>
                    <strong><?= formatMoney($revenue['card_total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Bank Transfer</span>
                    <strong><?= formatMoney($revenue['bank_transfer_total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>eSewa</span>
                    <strong><?= formatMoney($revenue['esewa_total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Khalti</span>
                    <strong><?= formatMoney($revenue['khalti_total']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Other</span>
                    <strong><?= formatMoney($revenue['other_method_total']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>