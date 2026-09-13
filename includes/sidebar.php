<aside id="sidebar">
    <div class="sidebar-brand">
        <div class="mark">IF</div>
        <div>
            <div class="name">IronForge</div>
            <div class="tag">Gym Management</div>
        </div>
    </div>

    <div class="sidebar-scroll">
        <div class="sidebar-label"><span>General</span></div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-label"><span>Members &amp; Plans</span></div>
        <a href="<?= BASE_URL ?>/admin/members/" class="nav-item">
            <i class="bi bi-people-fill"></i>
            <span>Members</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/memberships/" class="nav-item">
            <i class="bi bi-card-checklist"></i>
            <span>Memberships</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/plans/" class="nav-item">
            <i class="bi bi-box-seam-fill"></i>
            <span>Plans</span>
        </a>

        <div class="sidebar-label"><span>Training</span></div>
        <a href="<?= BASE_URL ?>/admin/trainers/" class="nav-item">
            <i class="bi bi-person-badge-fill"></i>
            <span>Trainers</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/trainer-slots/" class="nav-item">
            <i class="bi bi-calendar3"></i>
            <span>Trainer Slots</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/trainer-leave.php" class="nav-item">
            <i class="bi bi-calendar2-x"></i>
            <span>Trainer Leave</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/bookings/" class="nav-item">
            <i class="bi bi-journal-bookmark-fill"></i>
            <span>Bookings</span>
        </a>

        <div class="sidebar-label"><span>Operations</span></div>
        <a href="<?= BASE_URL ?>/admin/attendance/" class="nav-item">
            <i class="bi bi-clock-history"></i>
            <span>Attendance</span>
        </a>

        <div class="sidebar-label"><span>Finance &amp; Reports</span></div>
        <a href="<?= BASE_URL ?>/admin/payments/" class="nav-item">
            <i class="bi bi-credit-card-2-front-fill"></i>
            <span>Payments</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/expenses/" class="nav-item">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Expenses</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/refunds/" class="nav-item">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span>Refunds</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/reports/profit-loss.php" class="nav-item">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Profit &amp; Loss</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/reports/" class="nav-item">
            <i class="bi bi-pie-chart-fill"></i>
            <span>All Reports</span>
        </a>

        <div class="sidebar-label"><span>Requests Queue</span></div>
        <a href="<?= BASE_URL ?>/admin/transfers/" class="nav-item">
            <i class="bi bi-arrow-left-right"></i>
            <span>Transfers</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/change-requests/" class="nav-item">
            <i class="bi bi-pencil-square"></i>
            <span>Change Requests</span>
        </a>
    </div>

    <div class="sidebar-foot">
        <a href="<?= BASE_URL ?>/logout.php" class="nav-item text-danger mb-2">
            <i class="bi bi-box-arrow-right"></i>
            <span>Log out</span>
        </a>
        <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseToggle" title="Toggle Sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
</aside>