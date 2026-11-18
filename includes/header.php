<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | MediCare Hub' : 'MediCare Hub'; ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons 1.11 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="/medicarehub/assets/css/style.css" rel="stylesheet">

    <?php if (isset($isPrintPage) && $isPrintPage): ?>
    <!-- Print CSS -->
    <link href="/medicarehub/assets/css/print.css" rel="stylesheet" media="print">
    <?php endif; ?>
</head>

<?php
// Determine if sidebar should be shown
$noSidebar = isset($noSidebar) ? $noSidebar : false;
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if (!$userRole) {
    $noSidebar = true;
}
?>

<body class="<?php echo !$noSidebar ? 'd-flex' : ''; ?>">

<?php if (!$noSidebar): ?>
    <!-- Sidebar -->
    <?php
    switch ($userRole) {
        case 'admin':
            include __DIR__ . '/sidebar_admin.php';
            break;
        case 'doctor':
            include __DIR__ . '/sidebar_doctor.php';
            break;
        case 'patient':
            include __DIR__ . '/sidebar_patient.php';
            break;
    }
    ?>

    <!-- Main Content Wrapper -->
    <div class="main-content flex-grow-1">

        <!-- Top Bar -->
        <nav class="top-bar d-flex align-items-center justify-content-between px-4 py-3 bg-white border-bottom">
            <div class="d-flex align-items-center gap-3">
                <!-- Hamburger Toggle (mobile) -->
                <button class="btn btn-outline-secondary d-lg-none" id="sidebarToggle" type="button">
                    <i class="bi bi-list fs-5"></i>
                </button>

                <div>
                    <?php if (isset($pageTitle)): ?>
                        <h4 class="mb-0 fw-semibold"><?php echo htmlspecialchars($pageTitle); ?></h4>
                    <?php endif; ?>

                    <?php if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 0): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 small">
                                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                    <?php if ($index === count($breadcrumbs) - 1): ?>
                                        <li class="breadcrumb-item active" aria-current="page">
                                            <?php echo htmlspecialchars($crumb['label']); ?>
                                        </li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item">
                                            <a href="<?php echo htmlspecialchars($crumb['url']); ?>">
                                                <?php echo htmlspecialchars($crumb['label']); ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Current Date -->
                <span class="text-muted d-none d-md-inline">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?php echo date('l, F j, Y'); ?>
                </span>

                <!-- User Dropdown -->
                <?php if (isset($_SESSION['user_name'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($userRole === 'patient'): ?>
                                <li>
                                    <a class="dropdown-item" href="/medicarehub/patient/profile.php">
                                        <i class="bi bi-person me-2"></i>My Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item text-danger" href="/medicarehub/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
        <!-- End Top Bar -->

        <!-- Flash Messages -->
        <?php if (function_exists('getFlash') && $flash = getFlash()): ?>
            <div class="px-4 pt-3">
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert" id="flashMessage">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
            <script>
                setTimeout(function () {
                    var flash = document.getElementById('flashMessage');
                    if (flash) {
                        var bsAlert = bootstrap.Alert.getOrCreateInstance(flash);
                        bsAlert.close();
                    }
                }, 4000);
            </script>
        <?php endif; ?>

        <!-- Main Container -->
        <div class="container-fluid p-4">

<?php else: ?>

    <!-- Flash Messages (no-sidebar pages) -->
    <?php if (function_exists('getFlash') && $flash = getFlash()): ?>
        <div class="container mt-3">
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert" id="flashMessage">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <script>
            setTimeout(function () {
                var flash = document.getElementById('flashMessage');
                if (flash) {
                    var bsAlert = bootstrap.Alert.getOrCreateInstance(flash);
                    bsAlert.close();
                }
            }, 4000);
        </script>
    <?php endif; ?>

<?php endif; ?>
