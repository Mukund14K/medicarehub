<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// 1. Stat card counts
$totalPatients = (int) $pdo->query('SELECT COUNT(*) FROM Patient')->fetchColumn();
$totalDoctors = (int) $pdo->query('SELECT COUNT(*) FROM Doctor')->fetchColumn();
$todayAppointments = (int) $pdo->query('SELECT COUNT(*) FROM Appointment WHERE appointment_date = CURDATE()')->fetchColumn();
$totalMedicines = (int) $pdo->query('SELECT COUNT(*) FROM Medicine')->fetchColumn();

// 2. Chart 1: Appointments per Doctor
$doctorStmt = $pdo->query('
    SELECT d.name, COUNT(a.appointment_id) AS total_count
    FROM Doctor d
    LEFT JOIN Appointment a ON d.doctor_id = a.doctor_id
    GROUP BY d.doctor_id, d.name
    ORDER BY total_count DESC
');
$doctorRows = $doctorStmt->fetchAll();
$doctorNames = array_column($doctorRows, 'name');
$doctorCounts = array_map('intval', array_column($doctorRows, 'total_count'));

// 3. Chart 2: Appointments by Status
$statusStmt = $pdo->query('
    SELECT 
        SUM(CASE WHEN status = "Scheduled" THEN 1 ELSE 0 END) AS scheduled_cnt,
        SUM(CASE WHEN status = "Completed" THEN 1 ELSE 0 END) AS completed_cnt,
        SUM(CASE WHEN status = "Cancelled" THEN 1 ELSE 0 END) AS cancelled_cnt
    FROM Appointment
');
$statusRow = $statusStmt->fetch();
$statusCounts = [
    (int) ($statusRow['scheduled_cnt'] ?? 0),
    (int) ($statusRow['completed_cnt'] ?? 0),
    (int) ($statusRow['cancelled_cnt'] ?? 0),
];

// Recent Appointments
$recentStmt = $pdo->query('
    SELECT a.appointment_id, a.appointment_date, a.status, p.name AS patient_name, d.name AS doctor_name, d.specialization
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    ORDER BY a.appointment_date DESC, a.appointment_id DESC
    LIMIT 6
');
$recentAppointments = $recentStmt->fetchAll();

$pageTitle = 'Dashboard';
$loadCharts = true;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat Cards (4 in a row, strictly no icons, 1px border) -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Total patients</div>
            <div class="stat-value"><?php echo number_format($totalPatients); ?></div>
            <div class="stat-caption">Registered individuals</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Active doctors</div>
            <div class="stat-value"><?php echo number_format($totalDoctors); ?></div>
            <div class="stat-caption">In-house medical staff</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Appointments today</div>
            <div class="stat-value"><?php echo number_format($todayAppointments); ?></div>
            <div class="stat-caption"><?php echo date('d M Y'); ?></div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-label">Pharmacy catalog</div>
            <div class="stat-value"><?php echo number_format($totalMedicines); ?></div>
            <div class="stat-caption">Approved medicines</div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
    <!-- Bar Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <div>Appointments by specialist</div>
            </div>
            <div class="card-body">
                <div style="height: 280px; position: relative;">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Chart -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>Status breakdown</div>
            </div>
            <div class="card-body">
                <div style="height: 220px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="d-flex justify-content-between pt-3 border-top mt-3 small text-muted">
                    <div>Scheduled: <strong class="text-dark"><?php echo $statusCounts[0]; ?></strong></div>
                    <div>Completed: <strong class="text-dark"><?php echo $statusCounts[1]; ?></strong></div>
                    <div>Cancelled: <strong class="text-dark"><?php echo $statusCounts[2]; ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Appointments Table -->
<div class="card">
    <div class="card-header">
        <div>Recent appointments</div>
        <a href="/medicarehub/admin/appointments.php" class="small">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAppointments)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">No appointments recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentAppointments as $apt): ?>
                        <tr>
                            <td class="text-muted">#<?php echo e((string)$apt['appointment_id']); ?></td>
                            <td class="fw-medium"><?php echo e($apt['patient_name']); ?></td>
                            <td><?php echo e($apt['doctor_name']); ?></td>
                            <td><?php echo e($apt['specialization']); ?></td>
                            <td><?php echo e(formatDate($apt['appointment_date'])); ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($apt['status']); ?>">
                                    <?php echo e($apt['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/admin/appointment_form.php?id=<?php echo $apt['appointment_id']; ?>" class="table-action-link">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    window.chartData = {
        doctors: <?php echo json_encode($doctorNames); ?>,
        doctorCounts: <?php echo json_encode($doctorCounts); ?>,
        statusCounts: <?php echo json_encode($statusCounts); ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
