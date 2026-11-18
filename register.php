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
            $errors[] = 'Please enter a valid age between 0 and 150.';
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

                setFlash('success', 'Account created successfully. You can now sign in.');
                header('Location: ' . getBasePath() . '/login.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Create Patient Account';
$noSidebar = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <a href="/medicarehub/login.php" class="auth-logo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 4v16M4 12h16"/>
                </svg>
                <span>MediCare Hub</span>
            </a>
            <h1 class="auth-title">Create patient account</h1>
            <p class="auth-subtitle">Enter your personal details to register for consultations.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/medicarehub/register.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Full name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($name); ?>" required autofocus>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="age" class="form-label">Age</label>
                    <input type="number" class="form-control" id="age" name="age" value="<?php echo e($age); ?>" min="0" max="150" required>
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select gender</option>
                        <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($phone); ?>" maxlength="10" placeholder="10-digit number" required>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo e($username); ?>" required>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Create account</button>
        </form>

        <div class="text-center mt-3 pt-2">
            <span class="text-muted small">Already registered? </span>
            <a href="/medicarehub/login.php" class="small">Sign in</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
