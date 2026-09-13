<?php
require_once __DIR__ . '/../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';
requireTrainer();

$trainerId = (int) $_SESSION['trainer_id'];
$errors    = [];

$startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
$endDate   = trim($_POST['end_date'] ?? date('Y-m-d'));
$reason    = trim($_POST['reason'] ?? '');

// Flash messages
$messages = [
    'applied'   => ['type' => 'success', 'text' => 'Leave request submitted successfully. Awaiting admin review.'],
    'cancelled' => ['type' => 'info',    'text' => 'Leave request cancelled.'],
    'notfound'  => ['type' => 'danger',  'text' => 'Leave request not found or access denied.'],
];
$msgKey = $_GET['msg'] ?? '';
$flash  = $messages[$msgKey] ?? null;

// Handle New Leave Request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? 'apply';

    if ($action === 'apply') {
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate   = trim($_POST['end_date'] ?? '');
        $reason    = trim($_POST['reason'] ?? '');

        // 1. Validation
        if ($startDate === '') {
            $errors[] = 'Start date is required.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $startDate)) {
            $errors[] = 'Start date format is invalid.';
        } elseif ($startDate < date('Y-m-d')) {
            $errors[] = 'Start date cannot be in the past.';
        }

        if ($endDate === '') {
            $errors[] = 'End date is required.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $endDate)) {
            $errors[] = 'End date format is invalid.';
        } elseif ($endDate < $startDate) {
            $errors[] = 'End date must be on or after start date.';
        }

        if (mb_strlen($reason) > 500) {
            $errors[] = 'Reason cannot exceed 500 characters.';
        }

        // 2. Check overlapping pending or approved leave for this trainer
        if (empty($errors)) {
            $overlapStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM trainer_leave_requests 
                WHERE trainer_id = ? 
                  AND status IN ('pending', 'approved') 
                  AND start_date <= ? 
                  AND end_date >= ?
            ");
            $overlapStmt->execute([$trainerId, $endDate, $startDate]);
            if ($overlapStmt->fetchColumn() > 0) {
                $errors[] = 'You already have an active (pending or approved) leave request covering this date range.';
            }
        }

        // 3. Insert leave request
        if (empty($errors)) {
            $insert = $pdo->prepare("
                INSERT INTO trainer_leave_requests (trainer_id, start_date, end_date, reason, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $insert->execute([
                $trainerId,
                $startDate,
                $endDate,
                $reason !== '' ? $reason : null
            ]);

            header('Location: ' . BASE_URL . '/trainer/leave.php?msg=applied');
            exit;
        }
    } elseif ($action === 'cancel_request') {
        // Allow trainer to cancel own pending leave request
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        $del = $pdo->prepare("
            DELETE FROM trainer_leave_requests 
            WHERE leave_id = ? AND trainer_id = ? AND status = 'pending'
        ");
        $del->execute([$leaveId, $trainerId]);

        if ($del->rowCount() > 0) {
            header('Location: ' . BASE_URL . '/trainer/leave.php?msg=cancelled');
            exit;
        } else {
            header('Location: ' . BASE_URL . '/trainer/leave.php?msg=notfound');
            exit;
        }
    }
}

// Fetch all leave requests for this trainer
$stmt = $pdo->prepare("
    SELECT tlr.*, a.full_name AS reviewer_name
    FROM trainer_leave_requests tlr
    LEFT JOIN admins a ON tlr.reviewed_by = a.admin_id
    WHERE tlr.trainer_id = ?
    ORDER BY tlr.created_at DESC
");
$stmt->execute([$trainerId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Apply for Leave';
require_once ROOT_PATH . '/includes/trainer_header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold mb-1">Apply for Leave</h2>
        <p class="text-muted mb-0">Submit time-off requests for gym admin review.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mb-4" role="alert">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : ($flash['type'] === 'info' ? 'info-circle-fill' : 'exclamation-triangle-fill') ?>"></i>
            <span><?= htmlspecialchars($flash['text']) ?></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Apply Leave Form -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <span class="fw-bold"><i class="bi bi-calendar2-plus me-2"></i>Apply for Leave</span>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4">
                        <div class="fw-semibold mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-circle-fill"></i>
                            <span>Please fix the following:</span>
                        </div>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="apply">

                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-calendar-event"></i>
                            <input type="date" name="start_date" id="start_date" class="form-control" 
                                   value="<?= htmlspecialchars($startDate) ?>" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-calendar-check"></i>
                            <input type="date" name="end_date" id="end_date" class="form-control" 
                                   value="<?= htmlspecialchars($endDate) ?>" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="reason" class="form-label">Reason / Notes <span class="text-muted small">(Optional)</span></label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" 
                                  placeholder="e.g. Personal vacation, health checkup, exam preparation..."><?= htmlspecialchars($reason) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 fw-semibold">
                        <i class="bi bi-send me-1"></i> Apply Leave
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- My Requests History -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-clock-history me-2"></i>My Leave History</span>
                <span class="badge bg-secondary"><?= count($requests) ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Dates</th>
                            <th>Duration</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center py-5">
                                    <i class="bi bi-calendar-check fs-2 d-block mb-2 text-faint"></i>
                                    <div class="fw-semibold">You have not applied for leave.</div>
                                    <div class="small mt-1 text-muted">Use the form on the left to submit a leave request.</div>
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
                                            <?= formatDate($r['start_date']) ?>
                                            <?php if ($r['start_date'] !== $r['end_date']): ?>
                                                – <?= formatDate($r['end_date']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-muted" style="font-size: 0.72rem;">
                                            Submitted <?= formatDate($r['created_at']) ?>
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
                                    <td class="text-end">
                                        <?php if ($status === 'pending'): ?>
                                            <form method="POST" data-confirm="Cancel this pending leave request?" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="cancel_request">
                                                <input type="hidden" name="leave_id" value="<?= (int) $r['leave_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Request">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Finalized</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/trainer_footer.php'; ?>
