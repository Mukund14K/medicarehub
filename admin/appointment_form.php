<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$appointment = [
    'patient_id' => '',
    'doctor_id' => '',
    'appointment_date' => date('Y-m-d'),
    'status' => 'Scheduled'
];
$errors = [];

// Fetch patients for dropdown
$patients = $pdo->query('SELECT patient_id, name, phone FROM Patient ORDER BY name ASC')->fetchAll();

// Fetch doctors for dropdown
$doctors = $pdo->query('SELECT doctor_id, name, specialization FROM Doctor ORDER BY name ASC')->fetchAll();

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Appointment WHERE appointment_id = ?');
    $stmt->execute([$id]);
    $aptData = $stmt->fetch();
    if (!$aptData) {
        setFlash('error', 'Appointment not found.');
        header('Location: /medicarehub/admin/appointments.php');
        exit;
    }
    $appointment = $aptData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Scheduled');

    if ($patientId <= 0) {
        $errors[] = 'Please select a registered patient.';
    }

    if ($doctorId <= 0) {
        $errors[] = 'Please select a doctor.';
    }

    if (empty($appointmentDate) || !strtotime($appointmentDate)) {
        $errors[] = 'Please provide a valid appointment date.';
    }

    if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'], true)) {
        $errors[] = 'Invalid status selected.';
    }

    $appointment['patient_id'] = $patientId;
    $appointment['doctor_id'] = $doctorId;
    $appointment['appointment_date'] = $appointmentDate;
    $appointment['status'] = $status;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Appointment SET patient_id = ?, doctor_id = ?, appointment_date = ?, status = ? WHERE appointment_id = ?');
                $stmt->execute([$patientId, $doctorId, $appointmentDate, $status, $id]);
                setFlash('success', 'Appointment details updated successfully.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Appointment (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$patientId, $doctorId, $appointmentDate, $status]);
                setFlash('success', 'New appointment scheduled successfully.');
            }
            header('Location: /medicarehub/admin/appointments.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit Appointment' : 'Schedule Appointment';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Appointments', 'url' => '/medicarehub/admin/appointments.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'New Appointment', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title"><?php echo $isEdit ? 'Edit Appointment Details' : 'Book New Clinical Appointment'; ?></h3>
        <p class="text-muted small mb-0">Select patient, consulting physician, and consultation date</p>
    </div>
    <a href="/medicarehub/admin/appointments.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Schedule
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
                        <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['patient_id']; ?>" <?php echo ((int)$appointment['patient_id'] === (int)$p['patient_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($p['name']); ?> (Phone: <?php echo e($p['phone']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="doctor_id" class="form-label">Doctor & Specialty <span class="text-danger">*</span></label>
                        <select class="form-select" id="doctor_id" name="doctor_id" required>
                            <option value="">-- Choose Doctor --</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?php echo $d['doctor_id']; ?>" <?php echo ((int)$appointment['doctor_id'] === (int)$d['doctor_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($d['name']); ?> — <?php echo e($d['specialization']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="appointment_date" class="form-label">Appointment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" value="<?php echo e($appointment['appointment_date']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Scheduled" <?php echo ($appointment['status'] === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="Completed" <?php echo ($appointment['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo ($appointment['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/appointments.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i><?php echo $isEdit ? 'Save Changes' : 'Confirm Appointment'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
