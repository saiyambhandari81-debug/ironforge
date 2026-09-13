<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireLogin();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);

// Flash messages
$messages = [
    'approved' => ['type' => 'success', 'text' => 'Trainer leave request approved. Trainer status updated to On leave.'],
    'rejected' => ['type' => 'warning', 'text' => 'Trainer leave request rejected.'],
    'notfound' => ['type' => 'danger',  'text' => 'Leave request not found or access denied.'],
    'invalid'  => ['type' => 'danger',  'text' => 'Invalid action requested.'],
];
$msgKey = $_GET['msg'] ?? '';
$flash  = $messages[$msgKey] ?? null;

// Filter
$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'])) {
    $filter = 'pending';
}

// Handle Admin Action (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $leaveId = (int) ($_POST['leave_id'] ?? 0);
    $action  = trim($_POST['action'] ?? '');

    // Fetch leave request
    $stmt = $pdo->prepare("
        SELECT tlr.*, t.full_name AS trainer_name 
        FROM trainer_leave_requests tlr
        JOIN trainers t ON tlr.trainer_id = t.trainer_id
        WHERE tlr.leave_id = ?
    ");
    $stmt->execute([$leaveId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        header('Location: ' . BASE_URL . '/admin/trainer-leave.php?filter=' . urlencode($filter) . '&msg=notfound');
        exit;
    }

    if ($action === 'approve') {
        // 1. Mark request as approved
        $updReq = $pdo->prepare("
            UPDATE trainer_leave_requests 
            SET status = 'approved', reviewed_by = ? 
            WHERE leave_id = ?
        ");
        $updReq->execute([$adminId, $leaveId]);

        // 2. Update trainers table: set leave_start, leave_end, status = 'on_leave'
        $updTrainer = $pdo->prepare("
            UPDATE trainers 
            SET leave_start = ?, leave_end = ?, status = 'on_leave' 
            WHERE trainer_id = ?
        ");
        $updTrainer->execute([
            $req['start_date'],
            $req['end_date'],
            $req['trainer_id']
        ]);

        header('Location: ' . BASE_URL . '/admin/trainer-leave.php?filter=' . urlencode($filter) . '&msg=approved');
        exit;

    } elseif ($action === 'reject') {
        // Mark request as rejected
        $updReq = $pdo->prepare("
            UPDATE trainer_leave_requests 
            SET status = 'rejected', reviewed_by = ? 
            WHERE leave_id = ?
        ");
        $updReq->execute([$adminId, $leaveId]);

        header('Location: ' . BASE_URL . '/admin/trainer-leave.php?filter=' . urlencode($filter) . '&msg=rejected');
        exit;

    } else {
        header('Location: ' . BASE_URL . '/admin/trainer-leave.php?filter=' . urlencode($filter) . '&msg=invalid');
        exit;
    }
}

// Build query
$whereClause = "";
$params = [];

if ($filter === 'pending') {
    $whereClause = "WHERE tlr.status = 'pending'";
} elseif ($filter === 'approved') {
    $whereClause = "WHERE tlr.status = 'approved'";
} elseif ($filter === 'rejected') {
    $whereClause = "WHERE tlr.status = 'rejected'";
}

$stmt = $pdo->prepare("
    SELECT tlr.*, 
           t.full_name AS trainer_name, 
           t.email AS trainer_email, 
           t.phone AS trainer_phone,
           a.full_name AS reviewer_name
    FROM trainer_leave_requests tlr
    JOIN trainers t ON tlr.trainer_id = t.trainer_id
    LEFT JOIN admins a ON tlr.reviewed_by = a.admin_id
    $whereClause
    ORDER BY 
        CASE WHEN tlr.status = 'pending' THEN 0 ELSE 1 END ASC,
        tlr.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Badges count
$counts = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected_count,
        COUNT(*) AS all_count
    FROM trainer_leave_requests
")->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Trainer Leave Requests';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">Trainer Leave Requests</h2>
        <p class="text-muted mb-0">Review, approve, or reject time-off requests submitted by gym trainers.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
            <span><?= htmlspecialchars($flash['text']) ?></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="btn-group" role="group" aria-label="Leave request filters">
        <a href="<?= BASE_URL ?>/admin/trainer-leave.php?filter=pending" 
           class="btn btn-sm <?= $filter === 'pending' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Pending Requests
            <span class="badge bg-warning text-dark ms-1"><?= (int) ($counts['pending_count'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/admin/trainer-leave.php?filter=approved" 
           class="btn btn-sm <?= $filter === 'approved' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Approved
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['approved_count'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/admin/trainer-leave.php?filter=rejected" 
           class="btn btn-sm <?= $filter === 'rejected' ? 'btn-dark' : 'btn-outline-dark' ?>">
            Rejected
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['rejected_count'] ?? 0) ?></span>
        </a>
        <a href="<?= BASE_URL ?>/admin/trainer-leave.php?filter=all" 
           class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">
            All Requests
            <span class="badge bg-secondary ms-1"><?= (int) ($counts['all_count'] ?? 0) ?></span>
        </a>
    </div>

    <span class="text-muted small">
        Showing <?= count($requests) ?> request<?= count($requests) === 1 ? '' : 's' ?>
    </span>
</div>

<!-- Requests Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Trainer</th>
                    <th>Leave Dates</th>
                    <th>Duration</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="7" class="text-muted text-center py-5">
                            <i class="bi bi-calendar2-check fs-2 d-block mb-2 text-faint"></i>
                            <div class="fw-semibold">No requests found</div>
                            <div class="small mt-1 text-muted">
                                <?php if ($filter === 'pending'): ?>
                                    No trainer leave requests currently require review.
                                <?php else: ?>
                                    No records match this filter.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r): 
                        $startTs = strtotime($r['start_date']);
                        $endTs   = strtotime($r['end_date']);
                        $days = max(1, round(($endTs - $startTs) / (60 * 60 * 24)) + 1);
                        $status = $r['status'];
                    ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-person-badge me-1 text-muted"></i><?= htmlspecialchars($r['trainer_name']) ?>
                                </div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($r['trainer_email']) ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?= formatDate($r['start_date']) ?>
                                    <?php if ($r['start_date'] !== $r['end_date']): ?>
                                        – <?= formatDate($r['end_date']) ?>
                                    <?php endif; ?>
                                </div>
                                <span class="text-muted small">
                                    Applied on <?= formatDate($r['created_at']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= $days ?> day<?= $days > 1 ? 's' : '' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($r['reason'])): ?>
                                    <span class="small text-dark"><?= htmlspecialchars($r['reason']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($status === 'approved'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Approved
                                    </span>
                                <?php elseif ($status === 'rejected'): ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle me-1"></i>Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-hourglass-split me-1"></i>Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['reviewer_name'])): ?>
                                    <span class="small text-dark"><?= htmlspecialchars($r['reviewer_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($status === 'pending'): ?>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- Approve Button -->
                                        <form method="POST" data-confirm="Approve leave for <?= htmlspecialchars($r['trainer_name']) ?>?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="leave_id" value="<?= (int) $r['leave_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-sm btn-success" title="Approve Leave">
                                                <i class="bi bi-check-lg me-1"></i> Approve
                                            </button>
                                        </form>

                                        <!-- Reject Button -->
                                        <form method="POST" data-confirm="Reject leave request for <?= htmlspecialchars($r['trainer_name']) ?>?">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="leave_id" value="<?= (int) $r['leave_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject Leave">
                                                <i class="bi bi-x-lg me-1"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
