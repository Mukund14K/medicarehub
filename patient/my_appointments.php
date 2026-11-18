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
    header('Location: /medicarehub/patient/my_appointments.php');
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
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/patient/dashboard.php'],
    ['label' => 'My Appointments', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">My Appointment History</h3>
        <p class="text-muted small mb-0">Track scheduled visits, past consults, and cancellations</p>
    </div>
    <a href="/medicarehub/patient/book_appointment.php" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-2"></i>Book New Appointment
    </a>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/patient/my_appointments.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Appointments</option>
                    <option value="Scheduled" <?php echo ($statusFilter === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <?php if (!empty($statusFilter)): ?>
                    <a href="/medicarehub/patient/my_appointments.php" class="btn btn-outline-secondary">Reset Filter</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Total Bookings: <strong><?php echo $pagination['total_records']; ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Appt ID</th>
                    <th>Consulting Specialist</th>
                    <th>Department</th>
                    <th>Doctor Contact</th>
                    <th>Appointment Date</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No appointments found in this category.<br>
                            <a href="/medicarehub/patient/book_appointment.php" class="btn btn-sm btn-outline-primary mt-2">Book an Appointment</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($a['doctor_name']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo e($a['specialization']); ?></span></td>
                            <td>
                                <i class="bi bi-telephone text-muted me-1 small"></i>
                                <?php echo e($a['doctor_phone']); ?>
                            </td>
                            <td>
                                <div class="fw-medium"><?php echo formatDate($a['appointment_date']); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                    <i class="<?php echo getStatusIcon($a['status']); ?> me-1"></i><?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <?php if ($a['rx_count'] > 0): ?>
                                        <a href="/medicarehub/patient/prescription_print.php?appointment_id=<?php echo $a['appointment_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print Prescription Slip">
                                            <i class="bi bi-printer me-1"></i>Prescription
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($a['status'] === 'Scheduled'): ?>
                                        <form method="POST" action="/medicarehub/patient/my_appointments.php" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Appointment">
                                                <i class="bi bi-x-circle me-1"></i>Cancel
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

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-transparent py-3">
            <?php 
            $queryString = http_build_query(array_filter(['status' => $statusFilter]));
            echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/patient/my_appointments.php' . (!empty($queryString) ? '?' . $queryString : '')); 
            ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
