<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('patient');

$patientId = (int)($_SESSION['patient_id'] ?? 0);
if ($patientId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// Fetch current patient details
$stmt = $pdo->prepare('SELECT * FROM Patient WHERE patient_id = ?');
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    setFlash('error', 'Patient record not found.');
    header('Location: /medicarehub/login.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $name = trim($_POST['name'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    }

    if ($age === '' || !is_numeric($age) || (int)$age < 0 || (int)$age > 150) {
        $errors[] = 'Age must be between 0 and 150.';
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender option.';
    }

    if (!validatePhone($phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }

    $patient['name'] = $name;
    $patient['age'] = $age;
    $patient['gender'] = $gender;
    $patient['phone'] = $phone;

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('UPDATE Patient SET name = ?, age = ?, gender = ?, phone = ? WHERE patient_id = ?');
            $stmt->execute([$name, (int)$age, $gender, $phone, $patientId]);

            // Update session display name
            $_SESSION['user_name'] = $name;

            setFlash('success', 'Profile updated successfully.');
            header('Location: /medicarehub/patient/profile.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Personal profile</h2>
        <p class="page-description">Registered demographics and contact details</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div>Account details</div>
                <span class="small text-muted">Username: <strong><?php echo e($_SESSION['username'] ?? ''); ?></strong></span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-4" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/medicarehub/patient/profile.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo e($patient['name']); ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="age" class="form-label">Age</label>
                            <input type="number" class="form-control" id="age" name="age" value="<?php echo e((string)$patient['age']); ?>" min="0" max="150" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="Male" <?php echo ($patient['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($patient['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($patient['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label">Phone number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($patient['phone']); ?>" pattern="[0-9]{10}" maxlength="10" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/patient/dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
