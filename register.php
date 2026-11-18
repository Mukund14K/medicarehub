<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirectByRole();
}

$errors = [];
$name = '';
$age = '';
$gender = '';
$phone = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $age = trim($_POST['age'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        }

        if ($age === '' || !is_numeric($age) || (int)$age < 0 || (int)$age > 150) {
            $errors[] = 'Please enter a valid age (0-150).';
        }

        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors[] = 'Please select a valid gender.';
        }

        if (!validatePhone($phone)) {
            $errors[] = 'Phone number must be exactly 10 digits.';
        }

        if (empty($username) || strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9._]+$/', $username)) {
            $errors[] = 'Username must be at least 3 characters and contain only letters, numbers, dots, or underscores.';
        } else {
            // Check uniqueness
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM Users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Username is already taken. Please choose another.';
            }
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        // If no errors, create patient and user account inside transaction
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Insert Patient
                $stmt = $pdo->prepare('INSERT INTO Patient (name, age, gender, phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, (int)$age, $gender, $phone]);
                $patientId = (int)$pdo->lastInsertId();

                // 2. Insert User
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO Users (username, password_hash, role, patient_id) VALUES (?, ?, "patient", ?)');
                $stmt->execute([$username, $hash, $patientId]);

                $pdo->commit();

                setFlash('success', 'Registration successful! You can now log in with your credentials.');
                header('Location: ' . getBasePath() . '/login.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Patient Registration';
$noSidebar = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-wrapper">
    <!-- Branded Left Panel -->
    <div class="login-brand">
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="fs-2 fw-bold text-white tracking-tight">MediCare Hub</span>
        </div>
        <h2 class="fs-4 fw-semibold text-white-50 mb-4 text-center">Join Our Healthcare Network</h2>
        <p class="text-white-50 text-center mb-5" style="max-width: 420px; font-size: 0.95rem;">
            Create your patient profile to consult top specialists, manage appointments in real time, and download digital prescriptions.
        </p>

        <div class="d-flex flex-column gap-3 w-100" style="max-width: 380px;">
            <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px);">
                <div>
                    <h6 class="text-white mb-0 fw-semibold">24/7 Portal Access</h6>
                    <small class="text-white-50">Review consultations and prescriptions anytime</small>
                </div>
            </div>

            <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(8px);">
                <div>
                    <h6 class="text-white mb-0 fw-semibold">Certified Specialists</h6>
                    <small class="text-white-50">Cardiology, Dermatology, Orthopedics & more</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Register Form -->
    <div class="login-form-wrapper py-4">
        <div class="login-card" style="max-width: 480px;">
            <div class="text-center mb-4">
                <h3 class="fw-bold text-dark mb-1">Create Patient Account</h3>
                <p class="text-muted small">Fill in your information to get started</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2 px-3 mb-4 rounded-3" role="alert">
                    <div class="fw-semibold small mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please resolve the following errors:</div>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="/medicarehub/register.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control border-start-0" id="name" name="name" value="<?php echo e($name); ?>" placeholder="e.g. Vikram Sharma" required>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="age" name="age" value="<?php echo e($age); ?>" min="0" max="150" placeholder="e.g. 29" required>
                    </div>
                    <div class="col-md-6">
                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number (10 digits) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control border-start-0" id="phone" name="phone" value="<?php echo e($phone); ?>" placeholder="e.g. 9876543210" pattern="[0-9]{10}" maxlength="10" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Choose Username <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-at"></i></span>
                        <input type="text" class="form-control border-start-0" id="username" name="username" value="<?php echo e($username); ?>" placeholder="e.g. vikram_s" required>
                    </div>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 chars" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-medium shadow-sm">
                    <i class="bi bi-person-plus me-2"></i>Complete Registration
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="text-muted small mb-0">Already registered? <a href="/medicarehub/login.php" class="text-primary fw-semibold text-decoration-none">Sign in here</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
