<?php
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/includes/auth.php';
requireLogin();

// Members eligible to check in today
$eligibleMembers = $pdo->query("
    SELECT m.member_id, m.full_name
    FROM members m
    WHERE m.status = 'active'
      AND EXISTS (
          SELECT 1 FROM memberships ms
          WHERE ms.member_id = m.member_id 
            AND ms.status = 'active' 
            AND ms.expiry_date >= CURDATE()
      )
      AND NOT EXISTS (
          SELECT 1 FROM attendance a 
          WHERE a.member_id = m.member_id 
            AND a.attendance_date = CURDATE()
      )
    ORDER BY m.full_name
")->fetchAll();

// Today's attendance
$todayAttendance = $pdo->query("
    SELECT a.*, m.full_name
    FROM attendance a
    JOIN members m ON a.member_id = m.member_id
    WHERE a.attendance_date = CURDATE()
    ORDER BY a.check_in_time DESC
")->fetchAll();

$messages = [
    'checkedin'  => 'Checked in successfully.',
    'checkedout' => 'Checked out successfully.',
    'already'    => 'That member has already checked in today.',
    'notmember'  => 'That member is not eligible — they need an active membership to check in.',
];
$flash     = $messages[$_GET['msg'] ?? ''] ?? '';
$flashType = in_array($_GET['msg'] ?? '', ['already', 'notmember'], true) ? 'warning' : 'success';

$pageTitle = 'Attendance';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Check In a Member</div>
            <div class="card-body">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
                <?php endif; ?>

                <?php if (!$eligibleMembers): ?>
                    <p class="text-muted mb-0">
                        No one is currently eligible to check in.<br>
                        Everyone active has either already checked in today, or has no active membership.
                    </p>
                <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/attendance/check-in.php">
                    <?= csrfField() ?>
                    <select name="member_id" class="form-select mb-2" required>
                        <option value="">-- Select Member --</option>
                        <?php foreach ($eligibleMembers as $m): ?>
                            <option value="<?= $m['member_id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-dark w-100">Check In</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Today's Attendance</span>
                <a href="<?= BASE_URL ?>/admin/attendance/history.php" class="small">View full history &raquo;</a>
            </div>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$todayAttendance): ?>
                    <tr><td colspan="4" class="text-muted text-center py-4">No check-ins yet today.</td></tr>
                <?php else: foreach ($todayAttendance as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['full_name']) ?></td>
                        <td><?= $a['check_in_time'] ? date('g:i A', strtotime($a['check_in_time'])) : '-' ?></td>
                        <td><?= $a['check_out_time'] ? date('g:i A', strtotime($a['check_out_time'])) : '-' ?></td>
                        <td>
                            <?php if (!$a['check_out_time']): ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/attendance/check-out.php">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="attendance_id" value="<?= $a['attendance_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Check Out</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>