<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('doctor');

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    setFlash('error', 'Doctor profile not associated with this account.');
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
        setFlash('success', 'Appointment #' . $aptId . ' marked as Completed.');
    }
    header('Location: /medicarehub/doctor/dashboard.php');
    exit;
}

// Stats
$todayCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE doctor_id = ? AND appointment_date = CURDATE() AND status != "Cancelled"');
$todayCountStmt->execute([$doctorId]);
$todayCount = (int)$todayCountStmt->fetchColumn();

$upcomingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE doctor_id = ? AND appointment_date > CURDATE() AND status = "Scheduled"');
$upcomingCountStmt->execute([$doctorId]);
$upcomingCount = (int)$upcomingCountStmt->fetchColumn();

$uniquePatientsStmt = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) FROM Appointment WHERE doctor_id = ?');
$uniquePatientsStmt->execute([$doctorId]);
$uniquePatientsCount = (int)$uniquePatientsStmt->fetchColumn();

$completedCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE doctor_id = ? AND status = "Completed"');
$completedCountStmt->execute([$doctorId]);
$completedCount = (int)$completedCountStmt->fetchColumn();

// Today's Appointments
$todayStmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
    ORDER BY a.status ASC, a.appointment_id ASC
');
$todayStmt->execute([$doctorId]);
$todayAppointments = $todayStmt->fetchAll();

// Upcoming Appointments (Next 7 days)
$upcomingStmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date > CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.appointment_date ASC, a.appointment_id ASC
');
$upcomingStmt->execute([$doctorId]);
$upcomingAppointments = $upcomingStmt->fetchAll();

$pageTitle = 'Doctor Dashboard';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/medicarehub/doctor/dashboard.php'],
    ['label' => 'Dashboard', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Welcome, <?php echo e($_SESSION['user_name'] ?? 'Doctor'); ?></h3>
        <p class="text-muted small mb-0">Today's clinical schedule and upcoming consultations</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-primary"><?php echo $todayCount; ?></div>
                <div class="stat-label">Today's Consultations</div>
                <small class="text-muted"><?php echo date('d M Y'); ?></small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-teal" style="color: var(--accent);"><?php echo $upcomingCount; ?></div>
                <div class="stat-label">Upcoming (Scheduled)</div>
                <small class="text-muted">Future bookings</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-success"><?php echo $uniquePatientsCount; ?></div>
                <div class="stat-label">My Total Patients</div>
                <small class="text-muted">Assigned records</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-warning"><?php echo $completedCount; ?></div>
                <div class="stat-label">Completed Consults</div>
                <small class="text-muted">Fully serviced</small>
            </div>
        </div>
    </div>
</div>

<!-- Today's Appointments -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-semibold mb-0">Today's Patient Schedule</h5>
            <small class="text-muted">Direct patient queue for today</small>
        </div>
        <span class="badge bg-primary-subtle text-primary border">Today (<?php echo count($todayAppointments); ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Appt ID</th>
                        <th>Patient Name</th>
                        <th>Demographics</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Clinical Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayAppointments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-check fs-2 d-block mb-1 text-secondary opacity-50"></i>
                                No appointments scheduled for today. Enjoy your day!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todayAppointments as $a): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo e($a['patient_name']); ?></td>
                                <td><?php echo e((string)$a['age']); ?> yrs, <?php echo e($a['gender']); ?></td>
                                <td><?php echo e($a['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                        <i class="<?php echo getStatusIcon($a['status']); ?> me-1"></i><?php echo e($a['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $a['appointment_id']; ?>" class="btn btn-sm btn-outline-primary" title="Prescriptions">
                                            <i class="bi bi-file-earmark-medical me-1"></i>Prescribe
                                        </a>

                                        <?php if ($a['status'] === 'Scheduled'): ?>
                                            <form method="POST" action="/medicarehub/doctor/dashboard.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed">
                                                    <i class="bi bi-check2"></i> Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upcoming Appointments (Next 7 Days) -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-semibold mb-0">Upcoming Schedule (Next 7 Days)</h5>
            <small class="text-muted">Advance bookings awaiting consultation</small>
        </div>
        <a href="/medicarehub/doctor/appointments.php" class="btn btn-sm btn-outline-secondary">
            View All <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Appt ID</th>
                        <th>Patient Name</th>
                        <th>Date</th>
                        <th>Demographics</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingAppointments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                No upcoming appointments in the next 7 days.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $a): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo e($a['patient_name']); ?></td>
                                <td><?php echo e(formatDate($a['appointment_date'])); ?></td>
                                <td><?php echo e((string)$a['age']); ?> yrs, <?php echo e($a['gender']); ?></td>
                                <td><?php echo e($a['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                        <i class="<?php echo getStatusIcon($a['status']); ?> me-1"></i><?php echo e($a['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $a['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-medical me-1"></i>Prescriptions
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
