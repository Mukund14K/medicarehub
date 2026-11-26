<?php
/**
 * MediCare Hub - Patient Sidebar
 * Enterprise clinical software navigation (text-only menu items)
 */
$currentPage = $_SERVER['REQUEST_URI'];
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Patient';

$menuSections = [
    'Overview' => [
        ['label' => 'Dashboard', 'match' => ['dashboard'], 'href' => '/medicarehub/patient.php?view=dashboard'],
    ],
    'Appointments' => [
        ['label' => 'Book Appointment', 'match' => ['book_appointment'], 'href' => '/medicarehub/patient.php?view=book_appointment'],
        ['label' => 'My Appointments', 'match' => ['my_appointments'], 'href' => '/medicarehub/patient.php?view=my_appointments'],
    ],
    'Records' => [
        ['label' => 'Prescriptions', 'match' => ['prescriptions', 'prescription_print'], 'href' => '/medicarehub/patient.php?view=prescriptions'],
        ['label' => 'Profile', 'match' => ['profile'], 'href' => '/medicarehub/patient.php?view=profile'],
    ],
];

function renderPatientSidebarContent(array $sections, string $currentPage, string $userName): string {
    $activeView = $_GET['view'] ?? 'dashboard';
    ob_start(); ?>
    <!-- Logo area -->
    <a href="/medicarehub/patient.php?view=dashboard" class="sidebar-header">
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
            <div class="sidebar-user-role">Patient</div>
        </div>
        <a href="/medicarehub/logout.php" class="sidebar-signout">Sign out</a>
    </div>
    <?php
    return ob_get_clean();
}
?>

<!-- Desktop Fixed Sidebar -->
<aside class="sidebar d-none d-lg-flex" id="sidebar">
    <?php echo renderPatientSidebarContent($menuSections, $currentPage, $userName); ?>
</aside>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header p-2 border-bottom justify-content-end">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column h-100">
        <?php echo renderPatientSidebarContent($menuSections, $currentPage, $userName); ?>
    </div>
</div>
