<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

$search    = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate   = trim($_GET['end_date'] ?? date('Y-m-d'));

// Basic date validation
if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!DateTime::createFromFormat('Y-m-d', $endDate)) {
    $endDate = date('Y-m-d');
}

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = "WHERE a.attendance_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($search !== '') {
    $where .= " AND m.full_name LIKE ?";
    $params[] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN members m ON a.member_id = m.member_id $where");
$countStmt->execute($params);
$total      = $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "
    SELECT a.*, m.full_name
    FROM attendance a
    JOIN members m ON a.member_id = m.member_id
    $where
    ORDER BY a.attendance_date DESC, a.check_in_time DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$pageTitle = 'Attendance History';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Attendance History</h5>
    <a href="<?= BASE_URL ?>/admin/attendance/" class="btn btn-outline-secondary btn-sm">Back to Today</a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="text" name="search" class="form-control" placeholder="Search member" value="<?= htmlspecialchars($search) ?>">
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

<div class="card">
    <table class="table mb-0 align-middle">
        <thead>
            <tr>
                <th>Member</th>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$records): ?>
            <tr><td colspan="5" class="text-muted text-center py-4">No attendance records in this range.</td></tr>
        <?php else: foreach ($records as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= formatDate($r['attendance_date']) ?></td>
                <td><?= $r['check_in_time'] ? date('g:i A', strtotime($r['check_in_time'])) : '-' ?></td>
                <td><?= $r['check_out_time'] ? date('g:i A', strtotime($r['check_out_time'])) : '-' ?></td>
                <td><span class="badge bg-<?= $r['status'] === 'present' ? 'success' : 'secondary' ?>"><?= ucfirst($r['status']) ?></span></td>
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
                <a class="page-link" href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>"><?= $pg ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>