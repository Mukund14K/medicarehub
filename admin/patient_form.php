<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$patient = [
    'name' => '',
    'age' => '',
    'gender' => '',
    'phone' => ''
];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Patient WHERE patient_id = ?');
    $stmt->execute([$id]);
    $patientData = $stmt->fetch();
    if (!$patientData) {
        setFlash('error', 'Patient record not found.');
        header('Location: /medicarehub/admin/patients.php');
        exit;
    }
    $patient = $patientData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $name = trim($_POST['name'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors['name'] = 'Full name is required.';
    }

    if ($age === '' || !is_numeric($age) || (int)$age < 0 || (int)$age > 150) {
        $errors['age'] = 'Age must be a valid number between 0 and 150.';
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors['gender'] = 'Please select a valid gender option.';
    }

    if (!validatePhone($phone)) {
        $errors['phone'] = 'Phone number must be exactly 10 digits.';
    }

    $patient['name'] = $name;
    $patient['age'] = $age;
    $patient['gender'] = $gender;
    $patient['phone'] = $phone;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Patient SET name = ?, age = ?, gender = ?, phone = ? WHERE patient_id = ?');
                $stmt->execute([$name, (int)$age, $gender, $phone, $id]);
                setFlash('success', 'Patient record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Patient (name, age, gender, phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, (int)$age, $gender, $phone]);
                setFlash('success', 'Patient record created.');
            }
            header('Location: /medicarehub/admin/patients.php');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit patient' : 'Add patient';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Patients', 'url' => '/medicarehub/admin/patients.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add patient', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit patient' : 'Add patient'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update demographic and contact details' : 'Register a new patient record'; ?></p>
    </div>
    <a href="/medicarehub/admin/patients.php" class="btn btn-secondary">Back to patients</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger mb-4">
                        <span><?php echo e($errors['db']); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-section">
                        <div class="form-section-title">Demographics</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($patient['name']); ?>" placeholder="e.g. Ramesh Kumar" required autofocus>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="form-error"><?php echo e($errors['name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($patient['phone']); ?>" placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <span class="form-error"><?php echo e($errors['phone']); ?></span>
                                <?php else: ?>
                                    <span class="form-text">Enter 10 digits without country code or spaces.</span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="age" class="form-label">Age (years)</label>
                                <input type="number" class="form-control" id="age" name="age" value="<?php echo e((string)$patient['age']); ?>" min="0" max="150" placeholder="e.g. 35" required>
                                <?php if (isset($errors['age'])): ?>
                                    <span class="form-error"><?php echo e($errors['age']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo ($patient['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($patient['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($patient['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($errors['gender'])): ?>
                                    <span class="form-error"><?php echo e($errors['gender']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/patients.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save patient' : 'Add patient'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
