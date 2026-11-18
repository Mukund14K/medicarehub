<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            if (loginUser($pdo, $username, $password)) {
                redirectByRole();
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Sign In';
$noSidebar = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/medicarehub/login.php" class="auth-logo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 4v16M4 12h16"/>
                </svg>
                <span>MediCare Hub</span>
            </a>
            <h1 class="auth-title">Sign in to MediCare Hub</h1>
            <p class="auth-subtitle">Enter your account credentials to continue.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="/medicarehub/login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo e($username); ?>" required autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign in</button>
        </form>

        <div class="text-center mt-3 pt-2">
            <a href="/medicarehub/register.php" class="small">Create patient account</a>
        </div>
    </div>

    <!-- Demo Credentials (Muted Table) -->
    <div class="demo-credentials-card">
        <div class="demo-credentials-title">Demonstration Accounts</div>
        <table class="demo-table">
            <tbody>
                <tr>
                    <td class="fw-medium">Administrator</td>
                    <td><code>admin</code></td>
                    <td><code>admin123</code></td>
                </tr>
                <tr>
                    <td class="fw-medium">Doctor</td>
                    <td><code>dr.mehta</code></td>
                    <td><code>doctor123</code></td>
                </tr>
                <tr>
                    <td class="fw-medium">Patient</td>
                    <td><code>rahul</code></td>
                    <td><code>patient123</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
