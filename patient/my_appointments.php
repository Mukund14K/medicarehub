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
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">My appointments</h2>
        <p class="page-description">Complete consultation history and scheduled visits</p>
    </div>
    <a href="/medicarehub/patient/book_appointment.php" class="btn btn-primary">Book appointment</a>
</div>

<!-- Filter Toolbar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/medicarehub/patient/my_appointments.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="Scheduled" <?php echo ($statusFilter === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <?php if (!empty($statusFilter)): ?>
                <div class="col-auto">
                    <a href="/medicarehub/patient/my_appointments.php" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            <?php endif; ?>
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
                <th>Doctor</th>
                <th>Specialization</th>
                <th>Phone</th>
                <th>Appointment date</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="7" class="empty-state">No appointments found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td class="text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                        <td class="fw-medium"><?php echo e($a['doctor_name']); ?></td>
                        <td><?php echo e($a['specialization']); ?></td>
                        <td class="tabular-nums"><?php echo e($a['doctor_phone']); ?></td>
                        <td><?php echo formatDate($a['appointment_date']); ?></td>
                        <td>
                            <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                <?php echo e($a['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($a['rx_count'] > 0): ?>
                                <a href="/medicarehub/patient/prescription_print.php?appointment_id=<?php echo $a['appointment_id']; ?>" target="_blank" class="table-action-link">Slip</a>
                            <?php endif; ?>

                            <?php if ($a['status'] === 'Scheduled'): ?>
                                <form method="POST" action="/medicarehub/patient/my_appointments.php" class="d-inline m-0" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['appointment_id']; ?>">
                                    <button type="submit" class="table-action-link text-danger border-0 bg-transparent p-0">Cancel</button>
                                </form>
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
        <span>Showing <?php echo $pagination['offset'] + 1; ?> to <?php echo min($pagination['offset'] + 8, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
        <?php 
        $queryString = http_build_query(array_filter(['status' => $statusFilter]));
        echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/patient/my_appointments.php' . (!empty($queryString) ? '?' . $queryString : '')); 
        ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
