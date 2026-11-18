<?php
/**
 * MediCare Hub - Patient Sidebar
 * Fixed left sidebar with offcanvas mobile support
 */
$current_page = $_SERVER['REQUEST_URI'];
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Patient';

$menu_items = [
    ['label' => 'Dashboard',    'href' => '/medicarehub/patient/dashboard.php'],
    ['label' => 'Book Appointment',  'href' => '/medicarehub/patient/book_appointment.php'],
    ['label' => 'My Appointments',   'href' => '/medicarehub/patient/my_appointments.php'],
    ['label' => 'My Prescriptions',    'href' => '/medicarehub/patient/prescriptions.php'],
    ['label' => 'My Profile',  'href' => '/medicarehub/patient/profile.php'],
    ['type'  => 'divider'],
    ['label' => 'Logout',           'icon' => 'bi-box-arrow-right', 'href' => '/medicarehub/logout.php'],
];
?>

<style>
/* ── Sidebar Variables ─────────────────────────────────── */
:root {
    --sb-width: 260px;
    --sb-bg: #0f172a;
    --sb-bg-hover: rgba(56, 189, 248, 0.10);
    --sb-bg-active: rgba(56, 189, 248, 0.18);
    --sb-text: #94a3b8;
    --sb-text-hover: #e2e8f0;
    --sb-text-active: #38bdf8;
    --sb-accent: #0ea5e9;
    --sb-accent-teal: #2dd4bf;
    --sb-divider: rgba(148, 163, 184, 0.12);
    --sb-logo-gradient: linear-gradient(135deg, #0ea5e9, #2dd4bf);
}

/* ── Fixed Sidebar (desktop) ───────────────────────────── */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sb-width);
    background: var(--sb-bg);
    display: flex;
    flex-direction: column;
    z-index: 1040;
    border-right: 1px solid rgba(148, 163, 184, 0.08);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--sb-divider) transparent;
    transition: transform 0.3s ease;
}

/* ── Logo Area ─────────────────────────────────────────── */
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 24px 20px 20px;
    text-decoration: none;
    flex-shrink: 0;
}
.sidebar-logo-icon {
    display: none !important;
}
.sidebar-nav .nav-link i {
    display: none !important;
}
.sidebar-logo-text {
    font-size: 1.25rem;
    font-weight: 700;
    background: var(--sb-logo-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.3px;
}

/* ── Navigation ────────────────────────────────────────── */
.sidebar-nav {
    flex: 1 1 auto;
    padding: 8px 12px;
    list-style: none;
    margin: 0;
}
.sidebar-nav .nav-item {
    margin-bottom: 2px;
}
.sidebar-nav .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    border-radius: 10px;
    color: var(--sb-text);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}
.sidebar-nav .nav-link i {
    font-size: 1.15rem;
    width: 22px;
    text-align: center;
    flex-shrink: 0;
}
.sidebar-nav .nav-link:hover {
    background: var(--sb-bg-hover);
    color: var(--sb-text-hover);
}
.sidebar-nav .nav-link.active {
    background: var(--sb-bg-active);
    color: var(--sb-text-active);
    font-weight: 600;
}
.sidebar-nav .nav-link.active i {
    color: var(--sb-text-active);
}
.sidebar-nav .nav-link.text-danger {
    color: #f87171 !important;
}
.sidebar-nav .nav-link.text-danger:hover {
    background: rgba(248, 113, 113, 0.10);
    color: #fca5a5 !important;
}
.sidebar-nav .nav-divider {
    height: 1px;
    background: var(--sb-divider);
    margin: 10px 16px;
}

/* ── User Block ────────────────────────────────────────── */
.sidebar-user {
    flex-shrink: 0;
    padding: 16px 20px;
    border-top: 1px solid var(--sb-divider);
    display: flex;
    align-items: center;
    gap: 12px;
}
.sidebar-user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--sb-logo-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: #fff;
    font-weight: 600;
    flex-shrink: 0;
}
.sidebar-user-info {
    flex: 1;
    min-width: 0;
}
.sidebar-user-name {
    color: var(--sb-text-hover);
    font-weight: 600;
    font-size: 0.88rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-user-role .badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
}
.sidebar-user-logout {
    color: var(--sb-text);
    font-size: 1.15rem;
    transition: color 0.2s;
    text-decoration: none;
}
.sidebar-user-logout:hover {
    color: #f87171;
}

/* ── Main content offset ───────────────────────────────── */
.main-content {
    margin-left: var(--sb-width);
}

/* ── Mobile: hide fixed, show hamburger ────────────────── */
.sidebar-mobile-toggle {
    display: none;
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 1050;
    background: var(--sb-bg);
    border: 1px solid rgba(148,163,184,0.15);
    color: #e2e8f0;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    font-size: 1.3rem;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
@media (max-width: 991.98px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .sidebar-mobile-toggle { display: flex; }
}

/* ── Offcanvas overrides ───────────────────────────────── */
.offcanvas.sidebar-offcanvas {
    width: var(--sb-width);
    background: var(--sb-bg);
    border-right: 1px solid rgba(148,163,184,0.08);
}
.offcanvas.sidebar-offcanvas .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}
</style>

<!-- ── Mobile Toggle Button ────────────────────────────── -->
<button class="sidebar-mobile-toggle" type="button"
        data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas"
        aria-controls="sidebarOffcanvas" aria-label="Toggle navigation">
    <i class="bi bi-list"></i>
</button>

<!-- ── Fixed Sidebar (Desktop) ─────────────────────────── -->
<aside class="sidebar d-none d-lg-flex" id="sidebar">
    <?php echo renderPatientSidebarContent($menu_items, $current_page, $user_name, 'Patient'); ?>
</aside>

<!-- ── Offcanvas Sidebar (Mobile) ──────────────────────── -->
<div class="offcanvas offcanvas-start sidebar-offcanvas d-lg-none"
     tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header" style="padding:8px 12px 0; justify-content:flex-end;">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column" style="overflow-y:auto;">
        <?php echo renderPatientSidebarContent($menu_items, $current_page, $user_name, 'Patient'); ?>
    </div>
</div>

<?php
/**
 * Render the shared sidebar inner HTML
 */
function renderPatientSidebarContent(array $items, string $currentPage, string $userName, string $role): string {
    $initials = '';
    $parts = explode(' ', trim($userName));
    foreach ($parts as $p) { if ($p !== '') $initials .= strtoupper($p[0]); }
    $initials = substr($initials, 0, 2);

    $roleBadgeClass = match ($role) {
        'Admin'   => 'bg-danger',
        'Doctor'  => 'bg-info text-dark',
        'Patient' => 'bg-success',
        default   => 'bg-secondary',
    };

    ob_start(); ?>
    <!-- Logo -->
    <a href="<?php echo $items[0]['href']; ?>" class="sidebar-logo">
        <span class="sidebar-logo-text">MediCare Hub</span>
    </a>

    <!-- Navigation -->
    <ul class="sidebar-nav">
        <?php foreach ($items as $item): ?>
            <?php if (isset($item['type']) && $item['type'] === 'divider'): ?>
                <li><div class="nav-divider"></div></li>
            <?php else:
                $isActive = str_contains($currentPage, $item['href']);
                $isLogout = ($item['label'] === 'Logout');
                $classes  = 'nav-link';
                if ($isActive && !$isLogout) $classes .= ' active';
                if ($isLogout) $classes .= ' text-danger';
            ?>
                <li class="nav-item">
                    <a class="<?php echo $classes; ?>" href="<?php echo $item['href']; ?>">
                        <span><?php echo $item['label']; ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <!-- User Block -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?php echo $initials; ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></div>
            <div class="sidebar-user-role">
                <span class="badge <?php echo $roleBadgeClass; ?>"><?php echo $role; ?></span>
            </div>
        </div>
        <a href="/medicarehub/logout.php" class="sidebar-user-logout" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
    <?php
    return ob_get_clean();
}
?>
