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

    <!-- Bootstrap Icons 1.11 (used strictly for outline icons in sidebar and table action buttons) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts - IBM Plex Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Enterprise Custom CSS with cache busting -->
    <link href="/medicarehub/assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>" rel="stylesheet">

    <?php if (isset($isPrintPage) && $isPrintPage): ?>
    <!-- Print CSS -->
    <link href="/medicarehub/assets/css/print.css" rel="stylesheet" media="print">
    <?php endif; ?>
</head>

<?php
$noSidebar = isset($noSidebar) ? $noSidebar : false;
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if (!$userRole) {
    $noSidebar = true;
}
?>

<body>

<?php if (!$noSidebar): ?>
<div class="main-wrapper">
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
    <div class="main-content">

        <!-- Top Bar (56px, White, 1px Border) -->
        <header class="top-bar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary d-lg-none p-1" style="height: 32px; width: 32px;" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle navigation">
                    <i class="bi bi-list" style="display: inline-block !important; font-size: 18px;"></i>
                </button>

                <div class="top-bar-breadcrumb d-flex align-items-center gap-2">
                    <span class="text-secondary small">MediCare Hub</span>
                    <span class="text-muted small">/</span>
                    <span class="fw-semibold text-dark small"><?php echo htmlspecialchars($pageTitle ?? 'Portal'); ?></span>
                </div>
            </div>

            <div class="top-bar-meta">
                <?php if (isset($_SESSION['user_name'])): ?>
                    <span class="top-bar-user"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <span class="text-muted small d-none d-sm-inline">&bull;</span>
                <?php endif; ?>
                <span class="top-bar-date d-none d-sm-inline"><?php echo date('d M Y'); ?></span>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php if (function_exists('getFlash') && $flash = getFlash()): ?>
            <div class="px-4 pt-3">
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show mb-0" role="alert" id="flashMessage">
                    <span><?php echo htmlspecialchars($flash['message']); ?></span>
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
                }, 5000);
            </script>
        <?php endif; ?>

        <!-- Content Body (24px padding) -->
        <main class="content-body">

<?php else: ?>

    <!-- Flash Messages (Auth/No-Sidebar Pages) -->
    <?php if (function_exists('getFlash') && $flash = getFlash()): ?>
        <div class="container pt-3" style="max-width: 480px;">
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert" id="flashMessage">
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
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
            }, 5000);
        </script>
    <?php endif; ?>

<?php endif; ?>
