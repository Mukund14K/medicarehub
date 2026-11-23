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
        setFlash('success', 'Appointment #' . $aptId . ' marked as completed.');
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

$pageTitle = 'Appointments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Assigned appointments</h2>
        <p class="page-description">Consultation schedule and clinical records assigned to you</p>
    </div>
</div>

<!-- Search & Filter Toolbar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/medicarehub/doctor/appointments.php" class="row g-2 align-items-center">
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
                    <a href="/medicarehub/doctor/appointments.php" class="btn btn-secondary">Reset</a>
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
                            <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
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
                            <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Prescription</a>
                            <?php if ($a['status'] === 'Scheduled'): ?>
                                <form method="POST" action="/medicarehub/doctor/appointments.php" class="d-inline m-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                    <button type="submit" class="table-action-link border-0 bg-transparent p-0" onclick="return confirm('Mark this appointment as Completed?');">Complete</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($a['prescription_count'] > 0): ?>
                                <a href="/medicarehub/doctor/prescription_print.php?appointment_id=<?php echo $a['appointment_id']; ?>" target="_blank" class="table-action-link">Slip</a>
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
        echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/doctor/appointments.php' . (!empty($queryString) ? '?' . $queryString : '')); 
        ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
