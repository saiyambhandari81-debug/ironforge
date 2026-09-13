<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireLogin();

['range' => $range, 'start' => $startDate, 'end' => $endDate] = resolveDateRange();

$revenue = calculateRevenue($pdo, $startDate, $endDate);

$pageTitle = 'Revenue Analytics';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">Revenue Analytics</h1>
        <p class="text-muted small mb-0">Detailed breakdown of gross revenue for: <span class="fw-semibold text-dark"><?= formatDate($startDate) ?></span> – <span class="fw-semibold text-dark"><?= formatDate($endDate) ?></span></p>
    </div>

    <!-- Date Range Quick Selector -->
    <div class="btn-group" role="group" aria-label="Date Range Filter">
        <a href="?range=today" class="btn btn-sm btn-<?= $range === 'today' ? 'dark' : 'outline-dark' ?>">Today</a>
        <a href="?range=week" class="btn btn-sm btn-<?= $range === 'week' ? 'dark' : 'outline-dark' ?>">This Week</a>
        <a href="?range=month" class="btn btn-sm btn-<?= $range === 'month' ? 'dark' : 'outline-dark' ?>">This Month</a>
        <a href="?range=year" class="btn btn-sm btn-<?= $range === 'year' ? 'dark' : 'outline-dark' ?>">This Year</a>
        <a href="?range=custom" class="btn btn-sm btn-<?= $range === 'custom' ? 'dark' : 'outline-dark' ?>">Custom</a>
    </div>
</div>

<?php if ($range === 'custom'): ?>
<div class="card p-3 mb-4 bg-light border">
    <form method="GET" class="row g-2 align-items-center">
        <input type="hidden" name="range" value="custom">
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">Start Date:</label>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0 small fw-semibold">End Date:</label>
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-sm btn-dark px-3"><i class="bi bi-filter me-1"></i> Apply Filter</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Total Revenue Hero Banner -->
<div class="fin-card is-primary mb-4 text-center py-4">
    <div class="fin-label text-white-50 fs-6 mb-1"><i class="bi bi-cash-stack me-1"></i> Total Gross Revenue</div>
    <div class="display-5 fw-extrabold text-white tnum mb-1"><?= formatMoney($revenue['total']) ?></div>
    <div class="small text-white-50">Collected via completed payment transactions</div>
</div>

<div class="row g-3">
    <!-- By Source -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-layers me-2 text-primary"></i>Revenue by Category Source</div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-card-checklist text-primary"></i>
                        <span class="fw-medium text-dark">Membership Subscriptions</span>
                    </div>
                    <strong class="tnum font-monospace fs-5 text-dark"><?= formatMoney($revenue['membership_revenue']) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-box me-2 text-secondary"></i>
                        <span class="fw-medium text-dark">Other Gym Services</span>
                    </div>
                    <strong class="tnum font-monospace fs-5 text-dark"><?= formatMoney($revenue['other_revenue']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- By Payment Method -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
                <div class="fw-bold"><i class="bi bi-credit-card-2-back me-2 text-success"></i>Revenue by Payment Method</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <tr>
                            <td class="fw-medium"><i class="bi bi-cash me-2 text-success"></i>Cash</td>
                            <td class="text-end num font-monospace fw-bold"><?= formatMoney($revenue['cash_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="bi bi-credit-card me-2 text-info"></i>Card</td>
                            <td class="text-end num font-monospace fw-bold"><?= formatMoney($revenue['card_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="bi bi-bank me-2 text-primary"></i>Bank Transfer</td>
                            <td class="text-end num font-monospace fw-bold"><?= formatMoney($revenue['bank_transfer_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="bi bi-phone me-2 text-success"></i>eSewa</td>
                            <td class="text-end num font-monospace fw-bold"><?= formatMoney($revenue['esewa_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="bi bi-wallet2 me-2 text-warning"></i>Khalti</td>
                            <td class="text-end num font-monospace fw-bold"><?= formatMoney($revenue['khalti_total']) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-medium"><i class="bi bi-three-dots me-2 text-secondary"></i>Other Methods</td>
                            <td class="text-end num font-monospace fw-bold"><?= formatMoney($revenue['other_method_total']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>