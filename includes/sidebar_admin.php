<?php
/**
 * MediCare Hub - Admin Sidebar
 * Enterprise clinical software navigation
 */
$currentPage = $_SERVER['REQUEST_URI'];
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Administrator';

$menuSections = [
    'Overview' => [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'href' => '/medicarehub/admin/dashboard.php'],
    ],
    'Management' => [
        ['label' => 'Patients', 'icon' => 'bi-people', 'href' => '/medicarehub/admin/patients.php'],
        ['label' => 'Doctors', 'icon' => 'bi-person-badge', 'href' => '/medicarehub/admin/doctors.php'],
        ['label' => 'Medicines', 'icon' => 'bi-capsule', 'href' => '/medicarehub/admin/medicines.php'],
        ['label' => 'Appointments', 'icon' => 'bi-calendar-check', 'href' => '/medicarehub/admin/appointments.php'],
    ],
    'Reports' => [
        ['label' => 'Reports', 'icon' => 'bi-file-earmark-bar-graph', 'href' => '/medicarehub/admin/reports.php'],
    ],
];

function renderAdminSidebarContent(array $sections, string $currentPage, string $userName): string {
    ob_start(); ?>
    <!-- Logo area -->
    <a href="/medicarehub/admin/dashboard.php" class="sidebar-header">
        <span class="sidebar-mark">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 4v16M4 12h16"/>
            </svg>
        </span>
        <span class="sidebar-title">MediCare Hub</span>
    </a>

    <!-- Menu Items -->
    <div class="sidebar-menu">
        <?php foreach ($sections as $sectionTitle => $items): ?>
            <div class="sidebar-section-label"><?php echo htmlspecialchars($sectionTitle); ?></div>
            <?php foreach ($items as $item): 
                $isActive = str_contains($currentPage, $item['href']);
            ?>
                <a class="sidebar-nav-item <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                    <i class="bi <?php echo htmlspecialchars($item['icon']); ?>" style="display: inline-block !important;"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- User & Sign Out Footer -->
    <div class="sidebar-footer">
        <div>
            <div class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
            <div class="sidebar-user-role">Administrator</div>
        </div>
        <a href="/medicarehub/logout.php" class="sidebar-signout">Sign out</a>
    </div>
    <?php
    return ob_get_clean();
}
?>

<!-- Desktop Fixed Sidebar -->
<aside class="sidebar d-none d-lg-flex" id="sidebar">
    <?php echo renderAdminSidebarContent($menuSections, $currentPage, $userName); ?>
</aside>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header p-2 border-bottom justify-content-end">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column h-100">
        <?php echo renderAdminSidebarContent($menuSections, $currentPage, $userName); ?>
    </div>
</div>
