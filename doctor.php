<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('doctor');

$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, ['dashboard', 'appointments', 'patients', 'prescription_form', 'prescription_print'], true)) {
    $view = 'dashboard';
}

switch ($view) {
    case 'dashboard':
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// Handle Mark Completed action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    checkCSRF();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    if ($aptId > 0) {
        $stmt = $pdo->prepare('UPDATE Appointment SET status = "Completed" WHERE appointment_id = ? AND doctor_id = ?');
        $stmt->execute([$aptId, $doctorId]);
        setFlash('success', 'Appointment marked as completed.');
        header('Location: /medicarehub/doctor.php?view=dashboard');
        exit;
    }
}

// 1. Stat Counts
$todayCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE doctor_id = ? AND appointment_date = CURDATE() AND status != "Cancelled"');
$todayCountStmt->execute([$doctorId]);
$todayCount = (int)$todayCountStmt->fetchColumn();

$upcomingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE doctor_id = ? AND appointment_date > CURDATE() AND status = "Scheduled"');
$upcomingCountStmt->execute([$doctorId]);
$upcomingCount = (int)$upcomingCountStmt->fetchColumn();

$uniquePatientsStmt = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) FROM Appointment WHERE doctor_id = ?');
$uniquePatientsStmt->execute([$doctorId]);
$uniquePatientsCount = (int)$uniquePatientsStmt->fetchColumn();

$completedStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE doctor_id = ? AND status = "Completed"');
$completedStmt->execute([$doctorId]);
$completedCount = (int)$completedStmt->fetchColumn();

// 2. Today's Appointments Queue
$todayStmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
    ORDER BY a.appointment_id ASC
');
$todayStmt->execute([$doctorId]);
$todayAppointments = $todayStmt->fetchAll();

// 3. Upcoming 7-day bookings
$upcomingStmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date > CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND a.status = "Scheduled"
    ORDER BY a.appointment_date ASC, a.appointment_id ASC
');
$upcomingStmt->execute([$doctorId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

$pageTitle = 'Doctor Dashboard';
        break;

    case 'appointments':
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// Handle Status Change to Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    checkCSRF();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    if ($aptId > 0) {
        $stmt = $pdo->prepare('UPDATE Appointment SET status = "Completed" WHERE appointment_id = ? AND doctor_id = ?');
        $stmt->execute([$aptId, $doctorId]);
        setFlash('success', 'Appointment #' . $aptId . ' marked as completed.');
    }
    header('Location: /medicarehub/doctor.php?view=appointments');
    exit;
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$whereConditions = ['a.doctor_id = ?'];
$params = [$doctorId];

if (!empty($search)) {
    $whereConditions[] = 'p.name LIKE ?';
    $params[] = "%$search%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['Scheduled', 'Completed', 'Cancelled'], true)) {
    $whereConditions[] = 'a.status = ?';
    $params[] = $statusFilter;
}

$whereClause = ' WHERE ' . implode(' AND ', $whereConditions);

// Pagination
$countSql = "SELECT COUNT(*) FROM Appointment a JOIN Patient p ON a.patient_id = p.patient_id $whereClause";
$pagination = paginate($pdo, $countSql, $params, 10);

$sql = "SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone,
        (SELECT COUNT(*) FROM Prescription pr WHERE pr.appointment_id = a.appointment_id) AS prescription_count
        FROM Appointment a
        JOIN Patient p ON a.patient_id = p.patient_id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_id DESC
        LIMIT " . $pagination['offset'] . ", 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Appointments';
        break;

    case 'patients':
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$params = [$doctorId];
$searchClause = '';

if (!empty($search)) {
    $searchClause = ' AND (p.name LIKE ? OR p.phone LIKE ?) ';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT p.*, 
        COUNT(a.appointment_id) AS visit_count, 
        MAX(a.appointment_date) AS last_visit
        FROM Patient p
        JOIN Appointment a ON p.patient_id = a.patient_id
        WHERE a.doctor_id = ? $searchClause
        GROUP BY p.patient_id, p.name, p.age, p.gender, p.phone
        ORDER BY last_visit DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Patients';
        break;

    case 'prescription_form':
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$aptId = (int)($_GET['appointment_id'] ?? 0);

if ($aptId <= 0) {
    setFlash('error', 'Invalid appointment reference.');
    header('Location: /medicarehub/doctor.php?view=appointments');
    exit;
}

// Verify appointment belongs to this doctor
$stmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone, d.name AS doctor_name, d.specialization
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.doctor_id = ?
');
$stmt->execute([$aptId, $doctorId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    setFlash('error', 'Appointment not found or not assigned to your account.');
    header('Location: /medicarehub/doctor.php?view=appointments');
    exit;
}

$errors = [];
$editPrescriptionId = (int)($_GET['edit'] ?? 0);
$editPrescription = null;

// Handle POST actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $medicineId = (int)($_POST['medicine_id'] ?? 0);
        $dosage = trim($_POST['dosage'] ?? '');
        $durationDays = (int)($_POST['duration_days'] ?? 0);
        $prescriptionId = (int)($_POST['prescription_id'] ?? 0);

        if ($medicineId <= 0) {
            $errors[] = 'Please select a medicine from the list.';
        }
        if (empty($dosage)) {
            $errors[] = 'Dosage instructions are required.';
        }
        if ($durationDays <= 0) {
            $errors[] = 'Duration must be at least 1 day.';
        }

        if (empty($errors)) {
            try {
                if ($action === 'edit' && $prescriptionId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE Prescription 
                        SET medicine_id = ?, dosage = ?, duration_days = ? 
                        WHERE prescription_id = ? AND appointment_id = ?
                    ');
                    $stmt->execute([$medicineId, $dosage, $durationDays, $prescriptionId, $aptId]);
                    setFlash('success', 'Prescription line item updated.');
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO Prescription (appointment_id, medicine_id, dosage, duration_days)
                        VALUES (?, ?, ?, ?)
                    ');
                    $stmt->execute([$aptId, $medicineId, $dosage, $durationDays]);
                    setFlash('success', 'Medicine added to prescription.');
                }
                header('Location: /medicarehub/doctor.php?view=prescription_form&appointment_id=' . $aptId);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $prescriptionId = (int)($_POST['prescription_id'] ?? 0);
        if ($prescriptionId > 0) {
            $stmt = $pdo->prepare('DELETE FROM Prescription WHERE prescription_id = ? AND appointment_id = ?');
            $stmt->execute([$prescriptionId, $aptId]);
            setFlash('success', 'Medicine removed from prescription.');
        }
        header('Location: /medicarehub/doctor.php?view=prescription_form&appointment_id=' . $aptId);
        exit;
    }
}

// Fetch all existing prescriptions for this appointment
$pStmt = $pdo->prepare('
    SELECT pr.*, m.medicine_name, m.type, m.price
    FROM Prescription pr
    JOIN Medicine m ON pr.medicine_id = m.medicine_id
    WHERE pr.appointment_id = ?
    ORDER BY pr.prescription_id ASC
');
$pStmt->execute([$aptId]);
$prescriptions = $pStmt->fetchAll();

// Calculate total cost
$totalCost = 0.0;
foreach ($prescriptions as $p) {
    $totalCost += (float)$p['price'];
}

// If edit mode requested, fetch existing prescription item
if ($editPrescriptionId > 0) {
    $eStmt = $pdo->prepare('SELECT * FROM Prescription WHERE prescription_id = ? AND appointment_id = ?');
    $eStmt->execute([$editPrescriptionId, $aptId]);
    $editPrescription = $eStmt->fetch();
}

// Fetch all available medicines for dropdown
$medicines = $pdo->query('SELECT medicine_id, medicine_name, type, price FROM Medicine ORDER BY medicine_name ASC')->fetchAll();

$pageTitle = 'Prescription Editor';
        break;

    case 'prescription_print':
$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$aptId = (int)($_GET['appointment_id'] ?? 0);

if ($aptId <= 0) {
    header('Location: /medicarehub/doctor.php?view=appointments');
    exit;
}

$stmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone AS patient_phone,
           d.name AS doctor_name, d.specialization, d.phone AS doctor_phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.doctor_id = ?
');
$stmt->execute([$aptId, $doctorId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header('Location: /medicarehub/doctor.php?view=appointments');
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

}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($view === 'dashboard'): ?>
<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Today's consultations</div>
            <div class="stat-value"><?php echo $todayCount; ?></div>
            <div class="stat-caption"><?php echo date('d M Y'); ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Upcoming bookings</div>
            <div class="stat-value"><?php echo $upcomingCount; ?></div>
            <div class="stat-caption">Future scheduled visits</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Assigned patients</div>
            <div class="stat-value"><?php echo $uniquePatientsCount; ?></div>
            <div class="stat-caption">Total unique records</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Completed consultations</div>
            <div class="stat-value"><?php echo $completedCount; ?></div>
            <div class="stat-caption">Serviced patient visits</div>
        </div>
    </div>
</div>

<!-- Today's Patient Queue -->
<div class="card mb-4">
    <div class="card-header">
        <div>Today's consultation queue</div>
        <span class="text-secondary small">Today (<?php echo count($todayAppointments); ?>)</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient name</th>
                    <th>Demographics</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todayAppointments)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">No consultations scheduled for today.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($todayAppointments as $a): ?>
                        <tr>
                            <td class="text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td class="fw-medium"><?php echo e($a['patient_name']); ?></td>
                            <td><?php echo e((string)$a['age']); ?> yrs, <?php echo e($a['gender']); ?></td>
                            <td><?php echo e($a['phone']); ?></td>
                            <td>
                                <span class="<?php echo getStatusBadgeClass($a['status']); ?>">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Prescription</a>
                                <?php if ($a['status'] === 'Scheduled'): ?>
                                    <form method="POST" action="/medicarehub/doctor.php?view=dashboard" class="d-inline m-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                        <button type="submit" class="table-action-link text-success border-0 bg-transparent p-0" onclick="return confirm('Mark this appointment as Completed?');">Complete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Upcoming 7-day bookings -->
<div class="card">
    <div class="card-header">
        <div>Upcoming consultations (next 7 days)</div>
        <a href="/medicarehub/doctor.php?view=appointments" class="small">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient name</th>
                    <th>Date</th>
                    <th>Demographics</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($upcomingAppointments)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">No upcoming consultations in the next 7 days.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($upcomingAppointments as $a): ?>
                        <tr>
                            <td class="text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td class="fw-medium"><?php echo e($a['patient_name']); ?></td>
                            <td><?php echo e(formatDate($a['appointment_date'])); ?></td>
                            <td><?php echo e((string)$a['age']); ?> yrs, <?php echo e($a['gender']); ?></td>
                            <td><?php echo e($a['phone']); ?></td>
                            <td>
                                <span class="<?php echo getStatusBadgeClass($a['status']); ?>">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Prescription</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($view === 'appointments'): ?>
<div class="page-header">
    <div>
        <h2 class="page-title">Assigned appointments</h2>
        <p class="page-description">Consultation schedule and clinical records assigned to you</p>
    </div>
</div>

<!-- Search & Filter Toolbar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/medicarehub/doctor.php?view=appointments" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="appointments">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search" style="display: inline-block !important;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search patient name..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="Scheduled" <?php echo ($statusFilter === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="/medicarehub/doctor.php?view=appointments" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
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
                <th>Patient name</th>
                <th>Demographics</th>
                <th>Phone</th>
                <th>Date</th>
                <th>Status</th>
                <th>Prescriptions</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="8" class="empty-state">No appointments found matching your criteria.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td class="text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                        <td class="fw-medium"><?php echo e($a['patient_name']); ?></td>
                        <td><?php echo e((string)$a['age']); ?> yrs, <?php echo e($a['gender']); ?></td>
                        <td class="tabular-nums"><?php echo e($a['phone']); ?></td>
                        <td><?php echo formatDate($a['appointment_date']); ?></td>
                        <td>
                            <span class="<?php echo getStatusBadgeClass($a['status']); ?>">
                                <?php echo e($a['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['prescription_count'] > 0): ?>
                                <span class="text-secondary"><?php echo $a['prescription_count']; ?> meds</span>
                            <?php else: ?>
                                <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Prescription</a>
                            <?php if ($a['status'] === 'Scheduled'): ?>
                                <form method="POST" action="/medicarehub/doctor.php?view=appointments" class="d-inline m-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                    <button type="submit" class="table-action-link text-success border-0 bg-transparent p-0" onclick="return confirm('Mark this appointment as Completed?');">Complete</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($a['prescription_count'] > 0): ?>
                                <a href="/medicarehub/doctor.php?view=prescription_print&appointment_id=<?php echo $a['appointment_id']; ?>" target="_blank" class="table-action-link">Slip</a>
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
        <span>Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + 10, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
        <?php 
        $queryString = http_build_query(array_filter(['search' => $search, 'status' => $statusFilter]));
        echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/doctor.php?view=appointments' . (!empty($queryString) ? '?' . $queryString : '')); 
        ?>
    </div>
<?php endif; ?>

<?php elseif ($view === 'patients'): ?>
<div class="page-header">
    <div>
        <h2 class="page-title">Assigned patients</h2>
        <p class="page-description">Patients who have consulted with you in the clinic</p>
    </div>
</div>

<!-- Search Toolbar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/medicarehub/doctor.php?view=patients" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="patients">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search" style="display: inline-block !important;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search patient name or phone..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/doctor.php?view=patients" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Showing <?php echo count($patients); ?> patient records
            </div>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient name</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Phone</th>
                <th>Visits</th>
                <th>Last visit</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="7" class="empty-state">No patient records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td class="text-muted">#<?php echo e((string)$p['patient_id']); ?></td>
                        <td class="fw-medium"><?php echo e($p['name']); ?></td>
                        <td><?php echo e((string)$p['age']); ?></td>
                        <td><?php echo e($p['gender']); ?></td>
                        <td class="tabular-nums"><?php echo e($p['phone']); ?></td>
                        <td class="tabular-nums"><?php echo e((string)$p['visit_count']); ?></td>
                        <td><?php echo formatDate($p['last_visit']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($view === 'prescription_form'): ?>
<div class="page-header">
    <div>
        <h2 class="page-title">Prescription editor</h2>
        <p class="page-description">Appointment #<?php echo $aptId; ?> &middot; <?php echo e($appointment['patient_name']); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/medicarehub/doctor.php?view=appointments" class="btn btn-secondary">Appointments</a>
        <?php if (!empty($prescriptions)): ?>
            <a href="/medicarehub/doctor.php?view=prescription_print&appointment_id=<?php echo $aptId; ?>" target="_blank" class="btn btn-primary">Print slip</a>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Overview Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Patient</div>
                <div class="fw-medium"><?php echo e($appointment['patient_name']); ?></div>
                <div class="text-muted small"><?php echo e((string)$appointment['age']); ?> yrs, <?php echo e($appointment['gender']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Phone</div>
                <div class="tabular-nums"><?php echo e($appointment['phone']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Date</div>
                <div><?php echo formatDate($appointment['appointment_date']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <div>
                    <span class="<?php echo getStatusBadgeClass($appointment['status']); ?>">
                        <?php echo e($appointment['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Prescribed Medicines List -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div>Prescribed medications</div>
                <span class="small text-muted"><?php echo count($prescriptions); ?> items</span>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Duration</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prescriptions)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No medicines prescribed yet for this consultation.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $idx => $p): ?>
                                <tr>
                                    <td class="text-muted tabular-nums"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <div class="fw-medium"><?php echo e($p['medicine_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($p['type']); ?></div>
                                    </td>
                                    <td><?php echo e($p['dosage']); ?></td>
                                    <td><?php echo e((string)$p['duration_days']); ?> days</td>
                                    <td class="text-end tabular-nums"><?php echo formatCurrency((float)$p['price']); ?></td>
                                    <td class="text-end">
                                        <a href="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $aptId; ?>&edit=<?php echo $p['prescription_id']; ?>" class="table-action-link">Edit</a>
                                        <form method="POST" action="" class="d-inline m-0" onsubmit="return confirm('Remove this medicine from prescription?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="prescription_id" value="<?php echo $p['prescription_id']; ?>">
                                            <button type="submit" class="table-action-link text-danger border-0 bg-transparent p-0">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($prescriptions)): ?>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end fw-medium">Total estimated cost:</th>
                                <th colspan="2" class="text-end fw-semibold tabular-nums"><?php echo formatCurrency($totalCost); ?></th>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Add / Edit Medicine Form -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <div><?php echo $editPrescription ? 'Edit medication' : 'Add medication'; ?></div>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-3" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $aptId; ?>" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editPrescription ? 'edit' : 'add'; ?>">
                    <?php if ($editPrescription): ?>
                        <input type="hidden" name="prescription_id" value="<?php echo $editPrescription['prescription_id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="medicine_id" class="form-label">Medicine</label>
                        <select class="form-select" id="medicine_id" name="medicine_id" required>
                            <option value="">Select medicine</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?php echo $m['medicine_id']; ?>" <?php echo ($editPrescription && (int)$editPrescription['medicine_id'] === (int)$m['medicine_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($m['medicine_name']); ?> (<?php echo e($m['type']); ?>) - <?php echo formatCurrency((float)$m['price']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="dosage" class="form-label">Dosage instructions</label>
                        <input type="text" class="form-control" id="dosage" name="dosage" value="<?php echo e($editPrescription['dosage'] ?? ''); ?>" placeholder="e.g. 500mg twice daily after meals" required>
                        <div class="form-text">Specify strength, frequency, and timing.</div>
                    </div>

                    <div class="mb-4">
                        <label for="duration_days" class="form-label">Duration in days</label>
                        <input type="number" class="form-control" id="duration_days" name="duration_days" value="<?php echo e((string)($editPrescription['duration_days'] ?? '7')); ?>" min="1" max="365" required>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($editPrescription): ?>
                            <a href="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $aptId; ?>" class="btn btn-secondary w-50">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary <?php echo $editPrescription ? 'w-50' : 'w-100'; ?>">
                            <?php echo $editPrescription ? 'Update medicine' : 'Add medicine'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'prescription_print'): ?>
<div class="container my-4" style="max-width: 800px;">
    <!-- Print toolbar (hidden during print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print card p-3">
        <div>
            <div class="fw-semibold">Prescription Slip</div>
            <div class="small text-muted">Consultation #<?php echo $aptId; ?> &middot; <?php echo e($appointment['patient_name']); ?></div>
        </div>
        <div class="d-flex gap-2">
            <a href="/medicarehub/doctor.php?view=prescription_form&appointment_id=<?php echo $aptId; ?>" class="btn btn-secondary btn-sm">Back</a>
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

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>