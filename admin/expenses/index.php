<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$categories = [
    'equipment'      => 'Equipment',
    'electricity'    => 'Electricity',
    'water'          => 'Water',
    'rent'           => 'Rent',
    'trainer_salary' => 'Trainer Salary',
    'maintenance'    => 'Maintenance',
    'cleaning'       => 'Cleaning',
    'internet'       => 'Internet',
    'other'          => 'Other',
];

$search         = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$startDate      = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate        = trim($_GET['end_date'] ?? date('Y-m-d'));

if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!DateTime::createFromFormat('Y-m-d', $endDate)) {
    $endDate = date('Y-m-d');
}

$perPage = 15;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = "WHERE expense_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($search !== '') {
    $where .= " AND description LIKE ?";
    $params[] = "%$search%";
}

if (array_key_exists($categoryFilter, $categories)) {
    $where .= " AND category = ?";
    $params[] = $categoryFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM expenses $where");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "
    SELECT e.*, a.full_name AS created_by_name
    FROM expenses e
    LEFT JOIN admins a ON e.created_by = a.admin_id
    $where
    ORDER BY e.expense_date DESC, e.expense_id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$totalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses $where");
$totalStmt->execute($params);
$totalAmount = $totalStmt->fetchColumn();

$messages = [
    'added'   => 'Expense added successfully.',
    'updated' => 'Expense updated successfully.',
    'deleted' => 'Expense deleted.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$pageTitle = 'Expenses';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Expenses</h5>
    <a href="<?= BASE_URL ?>/admin/expenses/add.php" class="btn btn-dark">+ Add Expense</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="text" name="search" class="form-control" placeholder="Search description" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-auto">
        <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $value => $label): ?>
                <option value="<?= $value ?>" <?= $categoryFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="col-auto">
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-dark">Filter</button>
    </div>
</form>

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <span class="text-muted">Total for this filter</span>
        <span class="fs-5 fw-semibold"><?= formatMoney($totalAmount) ?></span>
    </div>
</div>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Logged By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$expenses): ?>
            <tr><td colspan="6" class="text-muted text-center py-4">No expenses in this range.</td></tr>
        <?php else: foreach ($expenses as $e): ?>
            <tr>
                <td><?= formatDate($e['expense_date']) ?></td>
                <td><?= htmlspecialchars($categories[$e['category']] ?? $e['category']) ?></td>
                <td><?= htmlspecialchars($e['description'] ?? '-') ?></td>
                <td><?= formatMoney($e['amount']) ?></td>
                <td><?= htmlspecialchars($e['created_by_name'] ?? '-') ?></td>
                <td class="d-flex gap-1">
                    <a href="<?= BASE_URL ?>/admin/expenses/edit.php?id=<?= $e['expense_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/expenses/delete.php" data-confirm="Delete this expense?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $e['expense_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination">
        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($categoryFilter) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"><?= $pg ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>