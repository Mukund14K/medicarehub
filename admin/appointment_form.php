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
        setFlash('error', 'Appointment record not found.');
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
        $errors['patient_id'] = 'Please select a registered patient.';
    }

    if ($doctorId <= 0) {
        $errors['doctor_id'] = 'Please select a consulting doctor.';
    }

    if (empty($appointmentDate) || !strtotime($appointmentDate)) {
        $errors['appointment_date'] = 'Please provide a valid consultation date.';
    }

    if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'], true)) {
        $errors['status'] = 'Please select a valid appointment status.';
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
                setFlash('success', 'Appointment record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Appointment (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$patientId, $doctorId, $appointmentDate, $status]);
                setFlash('success', 'Appointment scheduled.');
            }
            header('Location: /medicarehub/admin/appointments.php');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit appointment' : 'Create appointment';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Appointments', 'url' => '/medicarehub/admin/appointments.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Create appointment', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit appointment' : 'Create appointment'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update appointment assignment or consultation status' : 'Schedule a clinical consultation for a registered patient'; ?></p>
    </div>
    <a href="/medicarehub/admin/appointments.php" class="btn btn-secondary">Back to appointments</a>
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
                        <div class="form-section-title">Consultation details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="patient_id" class="form-label">Patient</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo $p['patient_id']; ?>" <?php echo ((int)$appointment['patient_id'] === (int)$p['patient_id']) ? 'selected' : ''; ?>>
                                            <?php echo e($p['name']); ?> (<?php echo e($p['phone']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['patient_id'])): ?>
                                    <span class="form-error"><?php echo e($errors['patient_id']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="doctor_id" class="form-label">Consulting doctor</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                    <option value="">Select doctor</option>
                                    <?php foreach ($doctors as $d): ?>
                                        <option value="<?php echo $d['doctor_id']; ?>" <?php echo ((int)$appointment['doctor_id'] === (int)$d['doctor_id']) ? 'selected' : ''; ?>>
                                            <?php echo e($d['name']); ?> — <?php echo e($d['specialization']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['doctor_id'])): ?>
                                    <span class="form-error"><?php echo e($errors['doctor_id']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="appointment_date" class="form-label">Consultation date</label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" value="<?php echo e($appointment['appointment_date']); ?>" required>
                                <?php if (isset($errors['appointment_date'])): ?>
                                    <span class="form-error"><?php echo e($errors['appointment_date']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Scheduled" <?php echo ($appointment['status'] === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="Completed" <?php echo ($appointment['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo ($appointment['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                    <span class="form-error"><?php echo e($errors['status']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/appointments.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save appointment' : 'Create appointment'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
