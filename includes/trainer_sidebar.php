<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside id="sidebar">
    <div class="sidebar-brand">
        <div class="mark">IF</div>
        <div>
            <div class="name">IronForge</div>
            <div class="tag">Trainer Portal</div>
        </div>
    </div>

    <div class="sidebar-scroll">
        <div class="sidebar-label"><span>Navigation</span></div>
        <a href="<?= BASE_URL ?>/trainer/index.php" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/slots.php" class="nav-item <?= $currentPage === 'slots.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i>
            <span>My Slots</span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/attendance.php" class="nav-item <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i>
            <span>Mark Attendance</span>
        </a>
        <a href="<?= BASE_URL ?>/trainer/leave.php" class="nav-item <?= $currentPage === 'leave.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar2-x"></i>
            <span>Apply for Leave</span>
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
