<div id="sidebar" class="d-flex flex-column text-white"
     style="width: 260px; height: 100vh; background: #0f1419; position: sticky; top: 0;">

    <div class="px-3 py-4 border-bottom border-secondary flex-shrink-0">
        <div class="fw-bold fs-5">IronForge</div>
        <div class="text-white-50 small">Gym Admin</div>
    </div>

    <div class="flex-grow-1 p-3" style="overflow-y: auto;">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="d-block text-decoration-none px-3 py-2 rounded mb-1 text-white">Dashboard</a>

        <div class="text-uppercase text-white-50 small px-3 mt-3 mb-1">Members</div>
        <a href="<?= BASE_URL ?>/admin/members/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Members</a>
        <a href="<?= BASE_URL ?>/admin/memberships/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Memberships</a>
        <a href="<?= BASE_URL ?>/admin/plans/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Plans</a>

        <div class="text-uppercase text-white-50 small px-3 mt-3 mb-1">Training</div>
        <a href="<?= BASE_URL ?>/admin/trainers/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Trainers</a>
        <a href="<?= BASE_URL ?>/admin/trainer-slots/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Trainer Slots</a>
        <a href="<?= BASE_URL ?>/admin/bookings/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Bookings</a>

        <div class="text-uppercase text-white-50 small px-3 mt-3 mb-1">Attendance</div>
        <a href="<?= BASE_URL ?>/admin/attendance/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Attendance</a>

        <div class="text-uppercase text-white-50 small px-3 mt-3 mb-1">Finance</div>
        <a href="<?= BASE_URL ?>/admin/payments/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Payments</a>
        <a href="<?= BASE_URL ?>/admin/expenses/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Expenses</a>
        <a href="<?= BASE_URL ?>/admin/refunds/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Refunds</a>
        <a href="<?= BASE_URL ?>/admin/reports/profit-loss.php" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Profit &amp; Loss</a>

        <div class="text-uppercase text-white-50 small px-3 mt-3 mb-1">Requests</div>
        <a href="<?= BASE_URL ?>/admin/transfers/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Transfers</a>
        <a href="<?= BASE_URL ?>/admin/change-requests/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Change Requests</a>

        <div class="border-top border-secondary mt-3 pt-3">
            <a href="<?= BASE_URL ?>/admin/reports/" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Reports</a>
            <a href="<?= BASE_URL ?>/logout.php" class="d-block text-decoration-none px-3 py-2 rounded text-white-50">Log out</a>
        </div>
    </div>
</div>