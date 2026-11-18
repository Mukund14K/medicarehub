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

$errors = [];
$selectedSpecialization = $_POST['specialization'] ?? '';
$selectedDoctorId = (int)($_POST['doctor_id'] ?? 0);
$appointmentDate = $_POST['appointment_date'] ?? '';

// Fetch all distinct specializations from Doctor table
$specializations = $pdo->query('SELECT DISTINCT specialization FROM Doctor ORDER BY specialization ASC')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $date = trim($_POST['appointment_date'] ?? '');

    if ($doctorId <= 0) {
        $errors[] = 'Please select a consulting doctor from the list.';
    }

    if (empty($date) || !strtotime($date)) {
        $errors[] = 'Please select a valid appointment date.';
    } else {
        $todayStr = date('Y-m-d');
        if ($date < $todayStr) {
            $errors[] = 'Appointments cannot be booked for past dates.';
        }
    }

    if (empty($errors)) {
        // 1. Check double booking of the same doctor on the same date by this patient
        $stmtDouble = $pdo->prepare('
            SELECT COUNT(*) FROM Appointment 
            WHERE doctor_id = ? AND appointment_date = ? AND patient_id = ? AND status != "Cancelled"
        ');
        $stmtDouble->execute([$doctorId, $date, $patientId]);
        if ($stmtDouble->fetchColumn() > 0) {
            $errors[] = 'You already have an appointment with this doctor on this date. Please choose a different date or specialist.';
        }

        // 2. Check maximum 10 appointments per doctor per day
        if (empty($errors)) {
            $stmtMax = $pdo->prepare('
                SELECT COUNT(*) FROM Appointment 
                WHERE doctor_id = ? AND appointment_date = ? AND status != "Cancelled"
            ');
            $stmtMax->execute([$doctorId, $date]);
            if ($stmtMax->fetchColumn() >= 10) {
                $errors[] = 'This doctor has reached the maximum appointment capacity for this date (10 patients). Please select an alternate date.';
            }
        }

        // 3. Insert new appointment
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO Appointment (patient_id, doctor_id, appointment_date, status)
                    VALUES (?, ?, ?, "Scheduled")
                ');
                $stmt->execute([$patientId, $doctorId, $date]);
                
                setFlash('success', 'Appointment successfully confirmed for ' . formatDate($date) . '!');
                header('Location: /medicarehub/patient/my_appointments.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Booking failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Book an Appointment';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/patient/dashboard.php'],
    ['label' => 'Book Appointment', 'url' => null],
];
$loadBooking = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Book a Specialist Consultation</h3>
        <p class="text-muted small mb-0">Follow the 3-step interactive wizard to select department, doctor, and consultation slot</p>
    </div>
</div>

<!-- Stepper Navigation -->
<div class="stepper mb-4 bg-white p-3 rounded-3 border">
    <div class="step active" id="step1">
        <i class="bi bi-1-circle me-1"></i> Specialization
    </div>
    <div class="step" id="step2">
        <i class="bi bi-2-circle me-1"></i> Doctor
    </div>
    <div class="step" id="step3">
        <i class="bi bi-3-circle me-1"></i> Date & Confirmation
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-3 px-4 mb-4 rounded-3" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Booking Notice:</div>
        <ul class="mb-0 ps-3 small">
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body p-4">
        <form method="POST" action="/medicarehub/patient/book_appointment.php" id="bookingForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="doctor_id" id="doctor_id" value="<?php echo $selectedDoctorId > 0 ? $selectedDoctorId : ''; ?>" required>

            <!-- Step 1: Specialization -->
            <div class="mb-4">
                <label for="specialization" class="form-label fw-semibold">Step 1: Choose Medical Department / Specialization <span class="text-danger">*</span></label>
                <select class="form-select" id="specialization" name="specialization" required>
                    <option value="">-- Select Medical Department --</option>
                    <?php foreach ($specializations as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo ($selectedSpecialization === $s) ? 'selected' : ''; ?>>
                            <?php echo e($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Doctors available in that specialty will load automatically.</small>
            </div>

            <!-- Step 2: Doctor Selection -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Step 2: Select Consulting Specialist <span class="text-danger">*</span></label>
                <div id="doctorContainer" class="row">
                    <div class="text-center py-4 text-muted w-100">
                        <i class="bi bi-person-badge fs-2 d-block mb-1 text-secondary opacity-50"></i>
                        Please select a department above to view available doctors.
                    </div>
                </div>
            </div>

            <!-- Step 3: Date & Submit -->
            <div class="row g-3 mb-4 pt-3 border-top">
                <div class="col-md-6">
                    <label for="appointment_date" class="form-label fw-semibold">Step 3: Consultation Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" value="<?php echo e($appointmentDate); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                    <small class="text-muted">Consultation slots are available today and onwards.</small>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-medium">
                        <i class="bi bi-calendar-check me-2"></i>Confirm & Book Appointment
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
