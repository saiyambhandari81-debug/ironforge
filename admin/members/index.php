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
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter === 'active' || $statusFilter === 'inactive') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM members $where");
$countStmt->execute($params);
$totalMembers = $countStmt->fetchColumn();
$totalPages   = max(1, (int) ceil($totalMembers / $perPage));
$page         = min($page, $totalPages);
$offset       = ($page - 1) * $perPage;

$sql = "SELECT m.*,
        (SELECT COUNT(*) FROM memberships ms
         WHERE ms.member_id = m.member_id AND ms.status = 'active' AND ms.expiry_date >= CURDATE()
        ) AS has_active_membership
        FROM members m
        $where
        ORDER BY full_name ASC
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$messages = [
    'added'       => 'Member added successfully.',
    'updated'     => 'Member updated successfully.',
    'activated'   => 'Member marked active.',
    'deactivated' => 'Member deactivated.',
    'notfound'    => 'That member could not be found.',
];
$flash = $messages[$_GET['msg'] ?? ''] ?? '';

$pageTitle = 'Members';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, phone" value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-outline-dark">Filter</button>
    </form>
    <a href="<?= BASE_URL ?>/admin/members/add.php" class="btn btn-dark">+ Add Member</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Membership</th><th>Joined</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$members): ?>
            <tr><td colspan="7" class="text-muted text-center py-4">No members found.</td></tr>
        <?php else: foreach ($members as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['full_name']) ?></td>
                <td><?= htmlspecialchars($m['email']) ?></td>
                <td><?= htmlspecialchars($m['phone']) ?></td>
                <td><span class="badge bg-<?= $m['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($m['status']) ?></span></td>
                <td><span class="badge bg-<?= $m['has_active_membership'] ? 'info' : 'light text-dark' ?>"><?= $m['has_active_membership'] ? 'Active' : 'None' ?></span></td>
                <td><?= formatDate($m['join_date']) ?></td>
                <td class="d-flex gap-1">
                    <a href="<?= BASE_URL ?>/admin/members/view.php?id=<?= $m['member_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                    <a href="<?= BASE_URL ?>/admin/members/edit.php?id=<?= $m['member_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="<?= BASE_URL ?>/admin/members/toggle-status.php" data-confirm="<?= $m['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= htmlspecialchars($m['full_name']) ?>?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $m['member_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-<?= $m['status'] === 'active' ? 'danger' : 'success' ?>">
                            <?= $m['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
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