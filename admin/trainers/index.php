<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$perPage      = 10;
$page         = max(1, (int) ($_GET['page'] ?? 1));

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR specialization LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM trainers $where");
$countStmt->execute($params);
$totalTrainers = $countStmt->fetchColumn();
$totalPages    = max(1, (int) ceil($totalTrainers / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

$sql = "SELECT * FROM trainers $where ORDER BY full_name ASC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trainers = $stmt->fetchAll();

$messages = [
    'added'       => 'Trainer added successfully.',
    'updated'     => 'Trainer updated successfully.',
    'activated'   => 'Trainer marked active.',
    'deactivated' => 'Trainer deactivated.',
    'notfound'    => 'That trainer could not be found.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$pageTitle = 'Trainers';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, phone, specialization" value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-outline-dark">Filter</button>
    </form>
    <a href="<?= BASE_URL ?>/admin/trainers/add.php" class="btn btn-dark">+ Add Trainer</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Specialization</th>
                <th>Experience</th>
                <th>Salary</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$trainers): ?>
            <tr><td colspan="8" class="text-muted text-center py-4">No trainers found.</td></tr>
        <?php else: foreach ($trainers as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['full_name']) ?></td>
                <td><?= htmlspecialchars($t['email']) ?></td>
                <td><?= htmlspecialchars($t['phone']) ?></td>
                <td><?= htmlspecialchars($t['specialization'] ?: '-') ?></td>
                <td><?= (int) $t['experience_years'] ?> yrs</td>
                <td><?= formatMoney($t['salary']) ?></td>
                <td><span class="badge bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($t['status']) ?></span></td>
                <td class="d-flex gap-1">
                    <a href="<?= BASE_URL ?>/admin/trainers/edit.php?id=<?= $t['trainer_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/trainers/toggle-status.php" data-confirm="<?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= htmlspecialchars($t['full_name']) ?>?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $t['trainer_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?= $t['status'] === 'active' ? 'danger' : 'success' ?>">
                            <?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                        </button>
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
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>