<?php
/**
 * MediCare Hub - Admin Sidebar
 * Enterprise clinical software navigation (text-only menu items)
 */
$currentPage = $_SERVER['REQUEST_URI'];
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Administrator';

$menuSections = [
    'Management' => [
        ['label' => 'Patients', 'view' => 'patients', 'match' => ['patients', 'patient_form'], 'href' => '/medicarehub/admin.php?view=patients'],
        ['label' => 'Doctors', 'view' => 'doctors', 'match' => ['doctors', 'doctor_form'], 'href' => '/medicarehub/admin.php?view=doctors'],
        ['label' => 'Medicines', 'view' => 'medicines', 'match' => ['medicines', 'medicine_form'], 'href' => '/medicarehub/admin.php?view=medicines'],
        ['label' => 'Appointments', 'view' => 'appointments', 'match' => ['appointments', 'appointment_form'], 'href' => '/medicarehub/admin.php?view=appointments'],
    ],
    'Reports & SQL' => [
        ['label' => 'Reports', 'view' => 'reports', 'match' => ['reports'], 'href' => '/medicarehub/admin.php?view=reports'],
        ['label' => 'SQL Query Runner', 'view' => 'sql_runner', 'match' => ['sql_runner'], 'href' => '/medicarehub/admin.php?view=sql_runner'],
    ],
];

function renderAdminSidebarContent(array $sections, string $currentPage, string $userName): string {
    $activeView = $_GET['view'] ?? 'patients';
    ob_start(); ?>
    <!-- Logo area -->
    <a href="/medicarehub/admin.php?view=patients" class="sidebar-header">
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
                $isActive = in_array($activeView, $item['match'] ?? [], true);
            ?>
                <a class="sidebar-nav-item <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
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
