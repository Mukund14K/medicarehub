<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('doctor');

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
        header('Location: /medicarehub/doctor/dashboard.php');
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
require_once __DIR__ . '/../includes/header.php';
?>

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
                                <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Prescription</a>
                                <?php if ($a['status'] === 'Scheduled'): ?>
                                    <form method="POST" action="/medicarehub/doctor/dashboard.php" class="d-inline m-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Complete</button>
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
        <a href="/medicarehub/doctor/appointments.php" class="small">View all</a>
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
                                <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Prescription</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
