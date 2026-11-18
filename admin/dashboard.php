<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// 1. Stat cards counts
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

// 3. Chart 2: Appointments by Status (Scheduled, Completed, Cancelled)
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

$pageTitle = 'Admin Dashboard';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Dashboard', 'url' => null],
];
$loadCharts = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4 mb-4">
    <!-- Stat 1: Total Patients -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-primary"><?php echo number_format($totalPatients); ?></div>
                <div class="stat-label">Total Patients</div>
                <small class="text-muted"><i class="bi bi-people me-1"></i>Registered individuals</small>
            </div>
        </div>
    </div>

    <!-- Stat 2: Total Doctors -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-teal" style="color: var(--accent);"><?php echo number_format($totalDoctors); ?></div>
                <div class="stat-label">Active Specialists</div>
                <small class="text-muted"><i class="bi bi-person-badge me-1"></i>In-house doctors</small>
            </div>
        </div>
    </div>

    <!-- Stat 3: Today's Appointments -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-success"><?php echo number_format($todayAppointments); ?></div>
                <div class="stat-label">Today's Appointments</div>
                <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?php echo date('d M Y'); ?></small>
            </div>
        </div>
    </div>

    <!-- Stat 4: Total Medicines -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div>
                <div class="stat-value text-warning"><?php echo number_format($totalMedicines); ?></div>
                <div class="stat-label">Pharmacy Catalog</div>
                <small class="text-muted"><i class="bi bi-capsule me-1"></i>Approved medicines</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
    <!-- Bar Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-semibold text-dark mb-0">Appointments by Specialist</h5>
                    <small class="text-muted">Total appointment distribution across clinic doctors</small>
                </div>
                <span class="badge bg-light text-muted border"><i class="bi bi-bar-chart-fill me-1"></i>Bar Chart</span>
            </div>
            <div class="card-body p-4">
                <div style="height: 300px; position: relative;">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Doughnut Chart -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-semibold text-dark mb-0">Status Breakdown</h5>
                    <small class="text-muted">Current booking statuses</small>
                </div>
                <span class="badge bg-light text-muted border"><i class="bi bi-pie-chart-fill me-1"></i>Ratio</span>
            </div>
            <div class="card-body p-4">
                <div style="height: 250px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="d-flex justify-content-around mt-3 text-center small">
                    <div>
                        <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background: #0F6CBD;"></span>
                        <span class="text-muted">Scheduled</span>
                        <div class="fw-bold"><?php echo $statusCounts[0]; ?></div>
                    </div>
                    <div>
                        <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background: #16A34A;"></span>
                        <span class="text-muted">Completed</span>
                        <div class="fw-bold"><?php echo $statusCounts[1]; ?></div>
                    </div>
                    <div>
                        <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background: #DC2626;"></span>
                        <span class="text-muted">Cancelled</span>
                        <div class="fw-bold"><?php echo $statusCounts[2]; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Appointments Table -->
<div class="card mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-semibold text-dark mb-0">Recent Appointments</h5>
            <small class="text-muted">Latest scheduled and finished patient visits</small>
        </div>
        <a href="/medicarehub/admin/appointments.php" class="btn btn-sm btn-outline-primary">
            View All <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0 mt-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAppointments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No appointments recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentAppointments as $apt): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$apt['appointment_id']); ?></td>
                                <td class="fw-medium text-dark"><?php echo e($apt['patient_name']); ?></td>
                                <td><?php echo e($apt['doctor_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($apt['specialization']); ?></span></td>
                                <td><?php echo e(formatDate($apt['appointment_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($apt['status']); ?>">
                                        <i class="<?php echo getStatusIcon($apt['status']); ?> me-1"></i><?php echo e($apt['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="/medicarehub/admin/appointment_form.php?id=<?php echo $apt['appointment_id']; ?>" class="btn btn-sm btn-action" title="Edit Appointment" data-bs-toggle="tooltip">
                                        <i class="bi bi-pencil"></i>
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

<script>
    window.chartData = {
        doctors: <?php echo json_encode($doctorNames); ?>,
        doctorCounts: <?php echo json_encode($doctorCounts); ?>,
        statusCounts: <?php echo json_encode($statusCounts); ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
