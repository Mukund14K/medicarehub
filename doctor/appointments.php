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

// Handle Status Change to Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    checkCSRF();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    if ($aptId > 0) {
        $stmt = $pdo->prepare('UPDATE Appointment SET status = "Completed" WHERE appointment_id = ? AND doctor_id = ?');
        $stmt->execute([$aptId, $doctorId]);
        setFlash('success', 'Appointment #' . $aptId . ' status changed to Completed.');
    }
    header('Location: /medicarehub/doctor/appointments.php');
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

$pageTitle = 'My Appointments';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/doctor/dashboard.php'],
    ['label' => 'Appointments', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Patient Consultations Schedule</h3>
        <p class="text-muted small mb-0">Manage and fulfill booked appointments assigned to your clinic schedule</p>
    </div>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/doctor/appointments.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search patient name..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Scheduled" <?php echo ($statusFilter === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="/medicarehub/doctor/appointments.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Total Consultations: <strong><?php echo $pagination['total_records']; ?></strong>
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
                    <th>Patient Name</th>
                    <th>Demographics</th>
                    <th>Contact Phone</th>
                    <th>Consultation Date</th>
                    <th>Status</th>
                    <th>Prescriptions</th>
                    <th class="text-end pe-4">Clinical Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No appointments found matching your search.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($a['patient_name']); ?></div>
                            </td>
                            <td><?php echo e((string)$a['age']); ?> yrs, <?php echo e($a['gender']); ?></td>
                            <td>
                                <i class="bi bi-telephone text-muted me-1 small"></i>
                                <?php echo e($a['phone']); ?>
                            </td>
                            <td>
                                <div><?php echo e(formatDate($a['appointment_date'])); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                    <i class="<?php echo getStatusIcon($a['status']); ?> me-1"></i><?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($a['prescription_count'] > 0): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border">
                                        <i class="bi bi-file-medical me-1"></i><?php echo $a['prescription_count']; ?> meds
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">None added</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $a['appointment_id']; ?>" class="btn btn-sm btn-outline-primary" title="Manage Prescriptions">
                                        <i class="bi bi-capsule me-1"></i>Prescribe
                                    </a>

                                    <?php if ($a['status'] === 'Scheduled'): ?>
                                        <form method="POST" action="/medicarehub/doctor/appointments.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed" onclick="return confirm('Mark this appointment as Completed?');">
                                                <i class="bi bi-check2"></i> Complete
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($a['prescription_count'] > 0): ?>
                                        <a href="/medicarehub/doctor/prescription_print.php?appointment_id=<?php echo $a['appointment_id']; ?>" target="_blank" class="btn btn-sm btn-action" title="Print Prescription" data-bs-toggle="tooltip">
                                            <i class="bi bi-printer"></i>
                                        </a>
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
            $queryString = http_build_query(array_filter(['search' => $search, 'status' => $statusFilter]));
            echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/doctor/appointments.php' . (!empty($queryString) ? '?' . $queryString : '')); 
            ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
