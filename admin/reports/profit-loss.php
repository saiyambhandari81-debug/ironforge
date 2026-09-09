<?php<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';   // ← This line is required
requireLogin();

$range = $_GET['range'] ?? 'month';
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
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

$pageTitle = 'Profit & Loss';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Profit &amp; Loss</h5>
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

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted">Total Revenue</div>
                <div class="fs-3 fw-semibold text-success"><?= formatMoney($revenue['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted">Total Expenses</div>
                <div class="fs-3 fw-semibold text-danger"><?= formatMoney($totalExpenses) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 <?= $netProfit >= 0 ? 'border-success' : 'border-danger' ?>">
            <div class="card-body text-center">
                <div class="text-muted"><?= $netProfit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
                <div class="fs-3 fw-semibold <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= formatMoney(abs($netProfit)) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Expenses by Category</div>
    <table class="table mb-0">
        <thead>
            <tr>
                <th>Category</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$expensesByCategory): ?>
            <tr><td colspan="2" class="text-muted text-center py-3">No expenses in this range.</td></tr>
        <?php else: foreach ($expensesByCategory as $c): ?>
            <tr>
                <td><?= htmlspecialchars($categoryLabels[$c['category']] ?? $c['category']) ?></td>
                <td><?= formatMoney($c['total']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>