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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Patient portal</h2>
        <p class="page-description">Medical appointments, consultation queue, and prescriptions</p>
    </div>
    <a href="/medicarehub/patient/book_appointment.php" class="btn btn-primary">Book appointment</a>
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
        <a href="/medicarehub/patient/my_appointments.php" class="small">View all</a>
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
                                <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="/medicarehub/patient/my_appointments.php" class="d-inline m-0" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Cancel</button>
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
        <a href="/medicarehub/patient/prescriptions.php" class="small">All prescriptions</a>
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
                                <span class="badge <?php echo getStatusBadgeClass($h['status']); ?>">
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
                                    <a href="/medicarehub/patient/prescription_print.php?appointment_id=<?php echo $h['appointment_id']; ?>" target="_blank" class="table-action-link">View slip</a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
