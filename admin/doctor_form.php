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
        $errors[] = 'Doctor name is required.';
    }

    if (empty($specialization)) {
        $errors[] = 'Specialization field is required.';
    }

    if (!validatePhone($phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }

    $doctor['name'] = $name;
    $doctor['specialization'] = $specialization;
    $doctor['phone'] = $phone;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Doctor SET name = ?, specialization = ?, phone = ? WHERE doctor_id = ?');
                $stmt->execute([$name, $specialization, $phone, $id]);
                setFlash('success', 'Doctor details updated successfully.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Doctor (name, specialization, phone) VALUES (?, ?, ?)');
                $stmt->execute([$name, $specialization, $phone]);
                setFlash('success', 'New doctor profile created successfully.');
            }
            header('Location: /medicarehub/admin/doctors.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit Doctor' : 'Add New Doctor';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Doctors', 'url' => '/medicarehub/admin/doctors.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add New', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title"><?php echo $isEdit ? 'Edit Doctor Profile' : 'Add New Specialist'; ?></h3>
        <p class="text-muted small mb-0">Medical specialty and clinic contact details</p>
    </div>
    <a href="/medicarehub/admin/doctors.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Doctors
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 px-3 mb-4 rounded-3" role="alert">
                        <div class="fw-semibold small mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Please correct the following:</div>
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Doctor Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person-badge"></i></span>
                            <input type="text" class="form-control border-start-0" id="name" name="name" value="<?php echo e($doctor['name']); ?>" placeholder="e.g. Dr. Rajesh Mehta" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="specialization" class="form-label">Specialization <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-heart-pulse"></i></span>
                            <input type="text" class="form-control border-start-0" id="specialization" name="specialization" value="<?php echo e($doctor['specialization']); ?>" placeholder="e.g. Cardiology, Dermatology, Orthopedics" required>
                        </div>
                        <small class="text-muted">Standard specialties: Cardiology, Dermatology, Orthopedics, Pediatrics, Neurology</small>
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label">Direct Contact Phone (10 digits) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                            <input type="tel" class="form-control border-start-0" id="phone" name="phone" value="<?php echo e($doctor['phone']); ?>" placeholder="e.g. 9876543210" pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/doctors.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i><?php echo $isEdit ? 'Save Changes' : 'Save Doctor'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
