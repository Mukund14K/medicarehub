<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('patient');

$patientId = (int)($_SESSION['patient_id'] ?? 0);
if ($patientId <= 0) {
    setFlash('error', 'Patient profile not associated with this account.');
    header('Location: /medicarehub/login.php');
    exit;
}

// Fetch stats
$upcomingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = "Scheduled"');
$upcomingCountStmt->execute([$patientId]);
$upcomingCount = (int)$upcomingCountStmt->fetchColumn();

$totalCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Appointment WHERE patient_id = ?');
$totalCountStmt->execute([$patientId]);
$totalCount = (int)$totalCountStmt->fetchColumn();

$prescriptionsCountStmt = $pdo->prepare('
    SELECT COUNT(DISTINCT pr.prescription_id)
    FROM Prescription pr
    JOIN Appointment a ON pr.appointment_id = a.appointment_id
    WHERE a.patient_id = ?
');
$prescriptionsCountStmt->execute([$patientId]);
$prescriptionsCount = (int)$prescriptionsCountStmt->fetchColumn();

// Fetch upcoming scheduled appointments
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

// Fetch recent consultation history
$historyStmt = $pdo->prepare('
    SELECT a.*, d.name AS doctor_name, d.specialization,
           (SELECT COUNT(*) FROM Prescription pr WHERE pr.appointment_id = a.appointment_id) AS rx_count
    FROM Appointment a
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ? AND (a.status = "Completed" OR a.appointment_date < CURDATE())
    ORDER BY a.appointment_date DESC
    LIMIT 5
');
$historyStmt->execute([$patientId]);
$recentHistory = $historyStmt->fetchAll();

$pageTitle = 'Patient Portal';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/medicarehub/patient/dashboard.php'],
    ['label' => 'Dashboard', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Hello, <?php echo e($_SESSION['user_name'] ?? 'Patient'); ?></h3>
        <p class="text-muted small mb-0">Manage your medical appointments, treatments, and prescriptions</p>
    </div>
    <a href="/medicarehub/patient/book_appointment.php" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-2"></i>Book New Appointment
    </a>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-primary"><?php echo $upcomingCount; ?></div>
                <div class="stat-label">Upcoming Appointments</div>
                <small class="text-muted">Confirmed consultations</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-teal" style="color: var(--accent);"><?php echo $totalCount; ?></div>
                <div class="stat-label">Total Appointments</div>
                <small class="text-muted">Lifetime consultation visits</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-4">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-success"><?php echo $prescriptionsCount; ?></div>
                <div class="stat-label">Active Prescriptions</div>
                <small class="text-muted">Prescribed medications</small>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Appointments Card -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-semibold mb-0">Next Scheduled Visits</h5>
            <small class="text-muted">Upcoming consultations awaiting your clinic visit</small>
        </div>
        <a href="/medicarehub/patient/my_appointments.php" class="btn btn-sm btn-outline-primary">
            View All My Appointments <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Appt ID</th>
                        <th>Specialist</th>
                        <th>Department</th>
                        <th>Scheduled Date</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingAppointments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-plus fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                You have no upcoming scheduled appointments.<br>
                                <a href="/medicarehub/patient/book_appointment.php" class="btn btn-sm btn-primary mt-2">
                                    Book an Appointment Now
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcomingAppointments as $a): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo e($a['doctor_name']); ?></div>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?php echo e($a['doctor_phone']); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($a['specialization']); ?></span></td>
                                <td>
                                    <div class="fw-medium"><?php echo formatDate($a['appointment_date']); ?></div>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                        <i class="<?php echo getStatusIcon($a['status']); ?> me-1"></i><?php echo e($a['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="/medicarehub/patient/my_appointments.php" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Consultations & Prescription History -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-semibold mb-0">Recent Consultations History</h5>
            <small class="text-muted">Completed or past clinical consultations</small>
        </div>
        <a href="/medicarehub/patient/prescriptions.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-file-medical me-1"></i>All Prescriptions
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Appt ID</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Prescription</th>
                        <th class="text-end pe-4">Slip</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentHistory)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No past consultation history recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentHistory as $h): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$h['appointment_id']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo e($h['doctor_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($h['specialization']); ?></span></td>
                                <td><?php echo formatDate($h['appointment_date']); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($h['status']); ?>">
                                        <i class="<?php echo getStatusIcon($h['status']); ?> me-1"></i><?php echo e($h['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($h['rx_count'] > 0): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis border">
                                            <i class="bi bi-check-circle me-1"></i><?php echo $h['rx_count']; ?> meds
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($h['rx_count'] > 0): ?>
                                        <a href="/medicarehub/patient/prescription_print.php?appointment_id=<?php echo $h['appointment_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-printer me-1"></i>Print Slip
                                        </a>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
