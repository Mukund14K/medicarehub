<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('patient');

$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, ['dashboard', 'book_appointment', 'my_appointments', 'prescriptions', 'prescription_print', 'profile', 'get_doctors'], true)) {
    $view = 'dashboard';
}

if ($view === 'get_doctors') {
// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$specialization = trim($_GET['specialization'] ?? '');

if (empty($specialization)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare('SELECT doctor_id AS id, doctor_id, name, specialization, phone FROM Doctor WHERE specialization = ? ORDER BY name ASC');
$stmt->execute([$specialization]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($doctors);
exit;
    exit;
}

switch ($view) {
    case 'dashboard':
$patientId = (int)($_SESSION['patient_id'] ?? 0);
if ($patientId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// 1. Stat Counts
$upcomingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = "Scheduled"');
$upcomingCountStmt->execute([$patientId]);
$upcomingCount = (int)$upcomingCountStmt->fetchColumn();

$totalCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE patient_id = ?');
$totalCountStmt->execute([$patientId]);
$totalCount = (int)$totalCountStmt->fetchColumn();

$prescriptionsCountStmt = $pdo->prepare('
    SELECT COUNT(*) FROM Prescription pr
    JOIN Appointment a ON pr.appointment_id = a.appointment_id
    WHERE a.patient_id = ?
');
$prescriptionsCountStmt->execute([$patientId]);
$prescriptionsCount = (int)$prescriptionsCountStmt->fetchColumn();

// 2. Next Scheduled Visits
$upcomingStmt = $pdo->prepare('
    SELECT a.*, d.name AS doctor_name, d.specialization, d.phone AS doctor_phone
    FROM Appointment a
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status = "Scheduled"
    ORDER BY a.appointment_date ASC
    LIMIT 5
');
$upcomingStmt->execute([$patientId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

// 3. Recent Consultations & Prescription History
$historyStmt = $pdo->prepare('
    SELECT a.*, d.name AS doctor_name, d.specialization,
           (SELECT COUNT(*) FROM Prescription WHERE appointment_id = a.appointment_id) AS rx_count
    FROM Appointment a
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_id DESC
    LIMIT 6
');
$historyStmt->execute([$patientId]);
$recentHistory = $historyStmt->fetchAll();

$pageTitle = 'Patient Portal';
        break;

    case 'book_appointment':
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
                header('Location: /medicarehub/patient.php?view=my_appointments');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Booking failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Book Appointment';
$loadBooking = true;
        break;

    case 'my_appointments':
$patientId = (int)($_SESSION['patient_id'] ?? 0);
if ($patientId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// Handle Appointment Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    checkCSRF();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    if ($aptId > 0) {
        $stmt = $pdo->prepare('UPDATE Appointment SET status = "Cancelled" WHERE appointment_id = ? AND patient_id = ? AND status = "Scheduled"');
        $stmt->execute([$aptId, $patientId]);
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Appointment #' . $aptId . ' has been cancelled.');
        } else {
            setFlash('error', 'Unable to cancel this appointment.');
        }
    }
    header('Location: /medicarehub/patient.php?view=my_appointments');
    exit;
}

// Filter by status
$statusFilter = trim($_GET['status'] ?? '');
$whereConditions = ['a.patient_id = ?'];
$params = [$patientId];

if (!empty($statusFilter) && in_array($statusFilter, ['Scheduled', 'Completed', 'Cancelled'], true)) {
    $whereConditions[] = 'a.status = ?';
    $params[] = $statusFilter;
}

$whereClause = ' WHERE ' . implode(' AND ', $whereConditions);

// Pagination
$countSql = "SELECT COUNT(*) FROM Appointment a $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT a.*, d.name AS doctor_name, d.specialization, d.phone AS doctor_phone,
        (SELECT COUNT(*) FROM Prescription pr WHERE pr.appointment_id = a.appointment_id) AS rx_count
        FROM Appointment a
        JOIN Doctor d ON a.doctor_id = d.doctor_id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_id DESC
        LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments';
        break;

    case 'prescriptions':
$patientId = (int)($_SESSION['patient_id'] ?? 0);
if ($patientId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// Fetch all appointments that have prescriptions for this patient
$stmt = $pdo->prepare('
    SELECT a.appointment_id, a.appointment_date, a.status, d.name AS doctor_name, d.specialization
    FROM Appointment a
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ?
      AND EXISTS (SELECT 1 FROM Prescription pr WHERE pr.appointment_id = a.appointment_id)
    ORDER BY a.appointment_date DESC, a.appointment_id DESC
');
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

// For each appointment, fetch the prescribed medicines
$prescriptionsByAppointment = [];
foreach ($appointments as $apt) {
    $pStmt = $pdo->prepare('
        SELECT pr.*, m.medicine_name, m.type, m.price
        FROM Prescription pr
        JOIN Medicine m ON pr.medicine_id = m.medicine_id
        WHERE pr.appointment_id = ?
        ORDER BY pr.prescription_id ASC
    ');
    $pStmt->execute([$apt['appointment_id']]);
    $items = $pStmt->fetchAll();

    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += (float)$it['price'];
    }

    $prescriptionsByAppointment[] = [
        'appointment' => $apt,
        'items' => $items,
        'total' => $subtotal
    ];
}

$pageTitle = 'Prescriptions';
        break;

    case 'prescription_print':
$patientId = (int)($_SESSION['patient_id'] ?? 0);
$aptId = (int)($_GET['appointment_id'] ?? 0);

if ($patientId <= 0 || $aptId <= 0) {
    header('Location: /medicarehub/patient.php?view=prescriptions');
    exit;
}

$stmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone AS patient_phone,
           d.name AS doctor_name, d.specialization, d.phone AS doctor_phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.patient_id = ?
');
$stmt->execute([$aptId, $patientId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header('Location: /medicarehub/patient.php?view=prescriptions');
    exit;
}

$pStmt = $pdo->prepare('
    SELECT pr.*, m.medicine_name, m.type, m.price
    FROM Prescription pr
    JOIN Medicine m ON pr.medicine_id = m.medicine_id
    WHERE pr.appointment_id = ?
    ORDER BY pr.prescription_id ASC
');
$pStmt->execute([$aptId]);
$prescriptions = $pStmt->fetchAll();

$totalCost = 0.0;
foreach ($prescriptions as $p) {
    $totalCost += (float)$p['price'];
}

$pageTitle = 'Prescription Slip - RX-' . str_pad((string)$aptId, 5, '0', STR_PAD_LEFT);
$isPrintPage = true;
        break;

    case 'profile':
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
            header('Location: /medicarehub/patient.php?view=profile');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Profile';
        break;

}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($view === 'dashboard'): ?>
<div class="page-header">
    <div>
        <h2 class="page-title">Patient portal</h2>
        <p class="page-description">Medical appointments, consultation queue, and prescriptions</p>
    </div>
    <a href="/medicarehub/patient.php?view=book_appointment" class="btn btn-primary">Book appointment</a>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="stat-label">Upcoming appointments</div>
            <div class="stat-value"><?php echo $upcomingCount; ?></div>
            <div class="stat-caption">Confirmed consultations</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="stat-label">Total appointments</div>
            <div class="stat-value"><?php echo $totalCount; ?></div>
            <div class="stat-caption">Lifetime clinic consultations</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="stat-card">
            <div class="stat-label">Active prescriptions</div>
            <div class="stat-value"><?php echo $prescriptionsCount; ?></div>
            <div class="stat-caption">Prescribed medications on record</div>
        </div>
    </div>
</div>

<!-- Upcoming Appointments Card -->
<div class="card mb-4">
    <div class="card-header">
        <div>Next scheduled visits</div>
        <a href="/medicarehub/patient.php?view=my_appointments" class="small">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Scheduled date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($upcomingAppointments)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">No scheduled appointments on record.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($upcomingAppointments as $a): ?>
                        <tr>
                            <td class="text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td class="fw-medium"><?php echo e($a['doctor_name']); ?></td>
                            <td><?php echo e($a['specialization']); ?></td>
                            <td><?php echo formatDate($a['appointment_date']); ?></td>
                            <td>
                                <span class="<?php echo getStatusBadgeClass($a['status']); ?>">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="/medicarehub/patient.php?view=my_appointments" class="d-inline m-0" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                    <button type="submit" class="table-action-link text-danger border-0 bg-transparent p-0">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Consultation History Table -->
<div class="card">
    <div class="card-header">
        <div>Recent consultation history</div>
        <a href="/medicarehub/patient.php?view=prescriptions" class="small">All prescriptions</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Prescription</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentHistory)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">No past consultation history recorded.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentHistory as $h): ?>
                        <tr>
                            <td class="text-muted">#<?php echo e((string)$h['appointment_id']); ?></td>
                            <td class="fw-medium"><?php echo e($h['doctor_name']); ?></td>
                            <td><?php echo e($h['specialization']); ?></td>
                            <td><?php echo formatDate($h['appointment_date']); ?></td>
                            <td>
                                <span class="<?php echo getStatusBadgeClass($h['status']); ?>">
                                    <?php echo e($h['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($h['rx_count'] > 0): ?>
                                    <span class="text-secondary"><?php echo $h['rx_count']; ?> medicines</span>
                                <?php else: ?>
                                    <span class="text-muted small">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($h['rx_count'] > 0): ?>
                                    <a href="/medicarehub/patient.php?view=prescription_print&appointment_id=<?php echo $h['appointment_id']; ?>" target="_blank" class="table-action-link">View slip</a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($view === 'book_appointment'): ?>
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

<form method="POST" action="/medicarehub/patient.php?view=book_appointment" id="bookingForm" novalidate>
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

<?php elseif ($view === 'my_appointments'): ?>
<div class="page-header">
    <div>
        <h2 class="page-title">My appointments</h2>
        <p class="page-description">Complete consultation history and scheduled visits</p>
    </div>
    <a href="/medicarehub/patient.php?view=book_appointment" class="btn btn-primary">Book appointment</a>
</div>

<!-- Filter Toolbar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/medicarehub/patient.php?view=my_appointments" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="my_appointments">
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="Scheduled" <?php echo ($statusFilter === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <?php if (!empty($statusFilter)): ?>
                <div class="col-auto">
                    <a href="/medicarehub/patient.php?view=my_appointments" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            <?php endif; ?>
            <div class="col-auto ms-auto text-muted small">
                Showing <?php echo count($appointments); ?> of <?php echo $pagination['total_records']; ?> records
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Doctor</th>
                <th>Specialization</th>
                <th>Phone</th>
                <th>Appointment date</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="7" class="empty-state">No appointments found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td class="text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                        <td class="fw-medium"><?php echo e($a['doctor_name']); ?></td>
                        <td><?php echo e($a['specialization']); ?></td>
                        <td class="tabular-nums"><?php echo e($a['doctor_phone']); ?></td>
                        <td><?php echo formatDate($a['appointment_date']); ?></td>
                        <td>
                            <span class="<?php echo getStatusBadgeClass($a['status']); ?>">
                                <?php echo e($a['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($a['rx_count'] > 0): ?>
                                <a href="/medicarehub/patient.php?view=prescription_print&appointment_id=<?php echo $a['appointment_id']; ?>" target="_blank" class="table-action-link">Slip</a>
                            <?php endif; ?>

                            <?php if ($a['status'] === 'Scheduled'): ?>
                                <form method="POST" action="/medicarehub/patient.php?view=my_appointments" class="d-inline m-0" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                    <button type="submit" class="table-action-link text-danger border-0 bg-transparent p-0">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination-container">
        <span>Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + 8, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
        <?php 
        $queryString = http_build_query(array_filter(['status' => $statusFilter]));
        echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/patient.php?view=my_appointments' . (!empty($queryString) ? '?' . $queryString : '')); 
        ?>
    </div>
<?php endif; ?>

<?php elseif ($view === 'prescriptions'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Prescriptions</h1>
        <p class="page-description">Archived medication records and printable consultation slips</p>
    </div>
</div>

<?php if (empty($prescriptionsByAppointment)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">No prescription records found for this patient account.</div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($prescriptionsByAppointment as $block): ?>
        <?php 
        $apt = $block['appointment'];
        $items = $block['items'];
        $total = $block['total'];
        ?>
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-3">
                <div>
                    <div class="fw-semibold text-dark fs-6">Consultation with <?php echo e($apt['doctor_name']); ?> <span class="text-secondary fw-normal">(<?php echo e($apt['specialization']); ?>)</span></div>
                    <div class="small text-muted mt-1">Date: <?php echo formatDate($apt['appointment_date']); ?> &middot; Rx #RX-<?php echo str_pad((string)$apt['appointment_id'], 5, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-secondary">Estimated total: <strong class="text-dark tabular-nums"><?php echo formatCurrency($total); ?></strong></span>
                    <a href="/medicarehub/patient.php?view=prescription_print&appointment_id=<?php echo $apt['appointment_id']; ?>" target="_blank" class="btn btn-secondary btn-sm">Print slip</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 25%;">Medicine name</th>
                            <th style="width: 15%;">Form</th>
                            <th>Dosage instructions</th>
                            <th style="width: 15%;">Duration</th>
                            <th class="text-end" style="width: 120px;">Unit price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $it): ?>
                            <tr>
                                <td class="text-muted tabular-nums">#<?php echo $idx + 1; ?></td>
                                <td class="fw-medium"><?php echo e($it['medicine_name']); ?></td>
                                <td><?php echo e($it['type']); ?></td>
                                <td><?php echo e($it['dosage']); ?></td>
                                <td class="tabular-nums"><?php echo e((string)$it['duration_days']); ?> days</td>
                                <td class="text-end tabular-nums fw-medium"><?php echo formatCurrency((float)$it['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php elseif ($view === 'prescription_print'): ?>
<div class="container my-4" style="max-width: 800px;">
    <!-- Print toolbar (hidden during print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print card p-3">
        <div>
            <div class="fw-semibold">Prescription Slip</div>
            <div class="small text-muted">Consultation #<?php echo $aptId; ?> &middot; <?php echo e($appointment['patient_name']); ?></div>
        </div>
        <div class="d-flex gap-2">
            <a href="/medicarehub/patient.php?view=prescriptions" class="btn btn-secondary btn-sm">Back</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>
        </div>
    </div>

    <!-- Official Prescription Paper Container -->
    <div class="card p-5" id="printableArea">
        <!-- Clinic Header -->
        <div class="print-header d-flex justify-content-between align-items-start pb-3 mb-4 border-bottom">
            <div>
                <h2 class="h4 fw-bold mb-1">MediCare Hub</h2>
                <div class="small text-muted">Multi-Specialty Clinic & Diagnostic Centre</div>
            </div>
            <div class="text-end small text-muted">
                <div>123 Healthcare Avenue, Medical District</div>
                <div>Bangalore, Karnataka 560001</div>
                <div>Phone: 1800-633-4227 &middot; care@medicarehub.local</div>
            </div>
        </div>

        <!-- Ruled Metadata Section -->
        <div class="print-meta-grid row g-3 pb-3 mb-4 border-bottom">
            <div class="col-6">
                <div class="small text-muted">Patient details</div>
                <div class="fw-semibold"><?php echo e($appointment['patient_name']); ?></div>
                <div class="small text-muted">ID: #<?php echo e((string)$appointment['patient_id']); ?> &middot; <?php echo e((string)$appointment['age']); ?> yrs, <?php echo e($appointment['gender']); ?></div>
                <div class="small text-muted tabular-nums">Phone: <?php echo e($appointment['patient_phone']); ?></div>
            </div>
            <div class="col-6 text-end">
                <div class="small text-muted">Consulting specialist</div>
                <div class="fw-semibold"><?php echo e($appointment['doctor_name']); ?></div>
                <div class="small text-muted"><?php echo e($appointment['specialization']); ?></div>
                <div class="small text-muted">Date: <?php echo formatDate($appointment['appointment_date']); ?> &middot; Rx #RX-<?php echo str_pad((string)$aptId, 5, '0', STR_PAD_LEFT); ?></div>
            </div>
        </div>

        <div class="fw-semibold mb-2">Prescribed medications</div>

        <!-- Itemized Medicine Table -->
        <div class="table-responsive mb-4">
            <table class="table print-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Medicine & Form</th>
                        <th>Dosage & Instructions</th>
                        <th style="width: 100px;">Duration</th>
                        <th class="text-end" style="width: 110px;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">No medicines recorded for this consultation.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $idx => $p): ?>
                            <tr>
                                <td class="tabular-nums text-muted"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="fw-medium"><?php echo e($p['medicine_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($p['type']); ?></div>
                                </td>
                                <td><?php echo e($p['dosage']); ?></td>
                                <td><?php echo e((string)$p['duration_days']); ?> days</td>
                                <td class="text-end tabular-nums"><?php echo formatCurrency((float)$p['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end fw-semibold">Total:</th>
                        <th class="text-end tabular-nums fw-semibold"><?php echo formatCurrency($totalCost); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Clinical Instructions -->
        <div class="p-3 mb-5 border rounded" style="background-color: var(--bg);">
            <div class="small fw-semibold mb-1">General instructions</div>
            <div class="small text-muted">Take prescribed medications as directed with meals unless instructed otherwise. Store in a cool, dry location away from moisture and direct sunlight. Complete the entire course.</div>
        </div>

        <!-- Doctor Signature Area -->
        <div class="signature-area d-flex justify-content-between align-items-end pt-4">
            <div class="small text-muted">
                <div>MediCare Hub Digital Clinical Record</div>
                <div>System generated verification slip</div>
            </div>
            <div class="signature-box text-center" style="width: 220px;">
                <div class="signature-rule" style="border-top: 1px solid #1B2430; margin-bottom: 6px;"></div>
                <div class="fw-semibold"><?php echo e($appointment['doctor_name']); ?></div>
                <div class="small text-muted"><?php echo e($appointment['specialization']); ?></div>
                <div class="small text-muted">Authorized Medical Practitioner</div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'profile'): ?>
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

                <form method="POST" action="/medicarehub/patient.php?view=profile" novalidate>
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
                        <a href="/medicarehub/patient.php?view=dashboard" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>