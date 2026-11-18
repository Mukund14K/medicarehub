<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$doctor = [
    'name' => '',
    'specialization' => '',
    'phone' => ''
];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Doctor WHERE doctor_id = ?');
    $stmt->execute([$id]);
    $doctorData = $stmt->fetch();
    if (!$doctorData) {
        setFlash('error', 'Doctor record not found.');
        header('Location: /medicarehub/admin/doctors.php');
        exit;
    }
    $doctor = $doctorData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $name = trim($_POST['name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors['name'] = 'Doctor full name is required.';
    }

    if (empty($specialization)) {
        $errors['specialization'] = 'Clinical specialization is required.';
    }

    if (!validatePhone($phone)) {
        $errors['phone'] = 'Phone number must be exactly 10 digits.';
    }

    $doctor['name'] = $name;
    $doctor['specialization'] = $specialization;
    $doctor['phone'] = $phone;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Doctor SET name = ?, specialization = ?, phone = ? WHERE doctor_id = ?');
                $stmt->execute([$name, $specialization, $phone, $id]);
                setFlash('success', 'Doctor record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Doctor (name, specialization, phone) VALUES (?, ?, ?)');
                $stmt->execute([$name, $specialization, $phone]);
                setFlash('success', 'Doctor record created.');
            }
            header('Location: /medicarehub/admin/doctors.php');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit doctor' : 'Add doctor';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Doctors', 'url' => '/medicarehub/admin/doctors.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add doctor', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit doctor' : 'Add doctor'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update clinical specialty and contact details' : 'Register a new clinical specialist'; ?></p>
    </div>
    <a href="/medicarehub/admin/doctors.php" class="btn btn-secondary">Back to doctors</a>
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
                        <div class="form-section-title">Specialist details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Doctor name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($doctor['name']); ?>" placeholder="e.g. Dr. Rajesh Mehta" required autofocus>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="form-error"><?php echo e($errors['name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo e($doctor['specialization']); ?>" placeholder="e.g. Cardiology" list="specialtyList" required>
                                <datalist id="specialtyList">
                                    <option value="Cardiology">
                                    <option value="Dermatology">
                                    <option value="Orthopedics">
                                    <option value="Pediatrics">
                                    <option value="Neurology">
                                    <option value="General Medicine">
                                </datalist>
                                <?php if (isset($errors['specialization'])): ?>
                                    <span class="form-error"><?php echo e($errors['specialization']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($doctor['phone']); ?>" placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <span class="form-error"><?php echo e($errors['phone']); ?></span>
                                <?php else: ?>
                                    <span class="form-text">Direct clinic or mobile contact line.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/doctors.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save doctor' : 'Add doctor'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
