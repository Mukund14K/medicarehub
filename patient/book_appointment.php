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
        $errors[] = 'Please select a consulting doctor from the table.';
    }

    if (empty($date) || !strtotime($date)) {
        $errors[] = 'Please select a valid consultation date.';
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
            $errors[] = 'An active appointment with this doctor already exists on this date.';
        }

        // 2. Check maximum 10 appointments per doctor per day
        if (empty($errors)) {
            $stmtMax = $pdo->prepare('
                SELECT COUNT(*) FROM Appointment 
                WHERE doctor_id = ? AND appointment_date = ? AND status != "Cancelled"
            ');
            $stmtMax->execute([$doctorId, $date]);
            if ($stmtMax->fetchColumn() >= 10) {
                $errors[] = 'Doctor capacity reached for this date (10 patients maximum). Please select another date.';
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
                
                setFlash('success', 'Appointment confirmed for ' . formatDate($date) . '.');
                header('Location: /medicarehub/patient/my_appointments.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Booking failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Book Appointment';
$loadBooking = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Book appointment</h2>
        <p class="page-description">Schedule a clinical consultation with an in-house medical specialist</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="/medicarehub/patient/book_appointment.php" id="bookingForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="doctor_id" id="doctor_id" value="<?php echo $selectedDoctorId > 0 ? $selectedDoctorId : ''; ?>" required>

    <!-- Section 1: Select specialization -->
    <div class="booking-section">
        <div class="booking-section-header">1. Select specialization</div>
        <div class="row">
            <div class="col-md-6">
                <label for="specialization" class="form-label">Medical specialization</label>
                <select class="form-select" id="specialization" name="specialization" required>
                    <option value="">Select department or specialty</option>
                    <?php foreach ($specializations as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo ($selectedSpecialization === $s) ? 'selected' : ''; ?>>
                            <?php echo e($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Available doctors under this department will load below.</div>
            </div>
        </div>
    </div>

    <!-- Section 2: Select doctor -->
    <div class="booking-section">
        <div class="booking-section-header">2. Select doctor</div>
        <div id="doctorContainer">
            <div class="empty-state">Select a specialization above to view available doctors.</div>
        </div>
    </div>

    <!-- Section 3: Select date -->
    <div class="booking-section">
        <div class="booking-section-header">3. Select date</div>
        <div class="row align-items-end g-3">
            <div class="col-md-6">
                <label for="appointment_date" class="form-label">Consultation date</label>
                <input type="date" class="form-control" id="appointment_date" name="appointment_date" value="<?php echo e($appointmentDate); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                <div class="form-text">Appointments can be scheduled for today and future dates.</div>
            </div>
            <div class="col-md-6 text-md-end">
                <button type="submit" class="btn btn-primary">Book appointment</button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
