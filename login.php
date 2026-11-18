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

<div class="login-wrapper">
    <!-- Branded Left Panel -->
    <div class="login-brand">
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="fs-2 fw-bold text-white tracking-tight">MediCare Hub</span>
        </div>
        <h2 class="fs-4 fw-semibold text-white-50 mb-4 text-center">Modern Clinic Management System</h2>
        <p class="text-white-50 text-center mb-5" style="max-width: 420px; font-size: 0.95rem;">
            A unified healthcare platform empowering clinics with seamless patient care, intelligent scheduling, and automated clinical workflows.
        </p>

        <div class="d-flex flex-column gap-3 w-100" style="max-width: 380px;">
            <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px);">
                <div>
                    <h6 class="text-white mb-0 fw-semibold">Secure Patient Records</h6>
                    <small class="text-white-50">Confidential, encrypted, and role-governed data</small>
                </div>
            </div>

            <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px);">
                <div>
                    <h6 class="text-white mb-0 fw-semibold">Smart Appointments</h6>
                    <small class="text-white-50">Doctor-specialization filtering & conflict prevention</small>
                </div>
            </div>

            <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px);">
                <div>
                    <h6 class="text-white mb-0 fw-semibold">Digital Prescriptions</h6>
                    <small class="text-white-50">Instant itemized dosing, cost tally & print-ready slip</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Login Form -->
    <div class="login-form-wrapper">
        <div class="login-card">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark mb-1">Welcome Back</h3>
                <p class="text-muted small">Enter your credentials to access your portal</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-4 rounded-3" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <div class="small"><?php echo e($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="/medicarehub/login.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control border-start-0" id="username" name="username" value="<?php echo e($username); ?>" placeholder="e.g. admin, dr.mehta, rahul" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-medium shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="text-muted small mb-0">New patient? <a href="/medicarehub/register.php" class="text-primary fw-semibold text-decoration-none">Create an account</a></p>
            </div>

            <!-- Demo Credentials Box -->
            <div class="demo-box mt-4">
                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-semibold small">
                    <i class="bi bi-key-fill"></i>
                    <span>Quick Demo Credentials</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0 text-muted small" style="font-size: 0.78rem;">
                        <tbody>
                            <tr>
                                <td class="fw-medium text-dark py-0">Admin:</td>
                                <td class="py-0 font-monospace">admin</td>
                                <td class="py-0 text-muted">/</td>
                                <td class="py-0 font-monospace">admin123</td>
                            </tr>
                            <tr>
                                <td class="fw-medium text-dark py-0">Doctor:</td>
                                <td class="py-0 font-monospace">dr.mehta</td>
                                <td class="py-0 text-muted">/</td>
                                <td class="py-0 font-monospace">doctor123</td>
                            </tr>
                            <tr>
                                <td class="fw-medium text-dark py-0">Patient:</td>
                                <td class="py-0 font-monospace">rahul</td>
                                <td class="py-0 text-muted">/</td>
                                <td class="py-0 font-monospace">patient123</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted mt-2 pt-1 border-top" style="font-size: 0.72rem;">
                    Other doctors: <code>dr.sharma</code>, <code>dr.patel</code> | Patients: <code>sneha</code>, <code>vikram</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
