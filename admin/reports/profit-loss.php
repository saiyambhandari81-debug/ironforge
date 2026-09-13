<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireLogin();

$range = $_GET['range'] ?? 'month';

switch ($range) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate   = date('Y-m-d');
        break;
    case 'week':
        $daysSinceMonday = (int) date('N') - 1;
        $startDate = date('Y-m-d', strtotime("-$daysSinceMonday days"));
        $endDate   = date('Y-m-d');
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate   = date('Y-m-d');
        break;
    case 'custom':
        $startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
        $endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));
        if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
            $startDate = date('Y-m-01');
        }
        if (!DateTime::createFromFormat('Y-m-d', $endDate)) {
            $endDate = date('Y-m-d');
        }
        break;
    case 'month':
    default:
        $range     = 'month';
        $startDate = date('Y-m-01');
        $endDate   = date('Y-m-d');
        break;
}

$revenue       = calculateRevenue($pdo, $startDate, $endDate);
$totalExpenses = calculateExpenses($pdo, $startDate, $endDate);
$netProfit     = round($revenue['total'] - $totalExpenses, 2);

$categoryLabels = expenseCategoryLabels();

$categoryStmt = $pdo->prepare(
    "SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM expenses
     WHERE expense_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY total DESC"
);
$categoryStmt->execute([$startDate, $endDate]);
$expensesByCategory = $categoryStmt->fetchAll();

$pageTitle = 'Profit & Loss Statement';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">Profit &amp; Loss Statement</h1>
        <p class="text-muted small mb-0">Financial statement showing net earnings for: <span class="fw-semibold text-dark"><?= formatDate($startDate) ?></span> – <span class="fw-semibold text-dark"><?= formatDate($endDate) ?></span></p>
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

<!-- Financial KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="fin-card">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="fin-label">Total Revenue</span>
                <i class="bi bi-arrow-up-right-circle text-success fs-5"></i>
            </div>
            <div class="fin-value tnum text-success"><?= formatMoney($revenue['total']) ?></div>
            <div class="small text-muted mt-1">Completed member payments</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="fin-card">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="fin-label">Total Operating Expenses</span>
                <i class="bi bi-arrow-down-right-circle text-danger fs-5"></i>
            </div>
            <div class="fin-value tnum text-danger"><?= formatMoney($totalExpenses) ?></div>
            <div class="small text-muted mt-1">Operational &amp; maintenance costs</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="fin-card <?= $netProfit >= 0 ? 'is-primary' : 'bg-danger text-white' ?>">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="fin-label <?= $netProfit >= 0 ? 'text-white-50' : 'text-white-50' ?>"><?= $netProfit >= 0 ? 'Net Profit' : 'Net Loss' ?></span>
                <i class="bi bi-piggy-bank text-warning fs-5"></i>
            </div>
            <div class="fin-value tnum text-white">
                <?= formatMoney(abs($netProfit)) ?>
            </div>
            <div class="small text-white-50 mt-1"><?= $netProfit >= 0 ? 'Surplus income' : 'Deficit for period' ?></div>
        </div>
    </div>
</div>

<!-- Expense Category Breakdown Table -->
<div class="card">
    <div class="card-header bg-white d-flex align-items-center justify-content-between pt-3 pb-3">
        <div class="fw-bold"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Expenses Breakdown by Category</div>
        <a href="<?= BASE_URL ?>/admin/expenses/add.php" class="btn btn-sm btn-outline-dark"><i class="bi bi-plus-lg me-1"></i> Add Expense</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-end">Total Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$expensesByCategory): ?>
                <tr><td colspan="2" class="text-muted text-center py-4">No expense logs found for this date range.</td></tr>
            <?php else: foreach ($expensesByCategory as $c): ?>
                <tr>
                    <td class="fw-medium text-dark">
                        <i class="bi bi-tag-fill me-2 text-secondary"></i>
                        <?= htmlspecialchars($categoryLabels[$c['category']] ?? $c['category']) ?>
                    </td>
                    <td class="text-end num font-monospace fw-bold text-dark"><?= formatMoney($c['total']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>