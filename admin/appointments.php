<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $aptId = (int)($_POST['id'] ?? 0);
    if ($aptId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Appointment WHERE appointment_id = ?');
        $stmt->execute([$aptId]);
        setFlash('success', 'Appointment record successfully removed.');
    }
    header('Location: /medicarehub/admin/appointments.php');
    exit;
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(p.name LIKE ? OR d.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['Scheduled', 'Completed', 'Cancelled'], true)) {
    $whereConditions[] = 'a.status = ?';
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

// Count query for pagination
$countSql = "SELECT COUNT(*) 
             FROM Appointment a 
             JOIN Patient p ON a.patient_id = p.patient_id 
             JOIN Doctor d ON a.doctor_id = d.doctor_id 
             $whereClause";
$pagination = paginate($pdo, $countSql, $params, 10);

// Fetch page records
$sql = "SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, d.name AS doctor_name, d.specialization 
        FROM Appointment a 
        JOIN Patient p ON a.patient_id = p.patient_id 
        JOIN Doctor d ON a.doctor_id = d.doctor_id 
        $whereClause 
        ORDER BY a.appointment_date DESC, a.appointment_id DESC 
        LIMIT " . $pagination['offset'] . ", 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Appointments Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Appointments', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Appointment Schedule</h3>
        <p class="text-muted small mb-0">Overview and booking management across all clinic doctors</p>
    </div>
    <a href="/medicarehub/admin/appointment_form.php" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-2"></i>New Appointment
    </a>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/appointments.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search patient or doctor name..." value="<?php echo e($search); ?>">
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
                    <a href="/medicarehub/admin/appointments.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Records: <strong><?php echo $pagination['total_records']; ?></strong>
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
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No appointments found matching current filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($a['patient_name']); ?></div>
                                <small class="text-muted"><?php echo e($a['patient_phone']); ?></small>
                            </td>
                            <td>
                                <div class="text-dark fw-medium"><?php echo e($a['doctor_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo e($a['specialization']); ?></span>
                            </td>
                            <td>
                                <div><?php echo e(formatDate($a['appointment_date'])); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($a['status']); ?>">
                                    <i class="<?php echo getStatusIcon($a['status']); ?> me-1"></i><?php echo e($a['status']); ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="/medicarehub/admin/appointment_form.php?id=<?php echo $a['appointment_id']; ?>" class="btn btn-sm btn-action" title="Edit Appointment" data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-action delete" title="Delete Appointment" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/appointments.php" data-id="<?php echo $a['appointment_id']; ?>" data-name="Appt #<?php echo $a['appointment_id']; ?> (<?php echo e($a['patient_name']); ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
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
            echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin/appointments.php' . (!empty($queryString) ? '?' . $queryString : '')); 
            ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteModal"]');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const deleteForm = document.getElementById('deleteForm');
                
                let idInput = deleteForm.querySelector('input[name="id"]');
                if (!idInput) {
                    idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    deleteForm.appendChild(idInput);
                }
                idInput.value = id;

                let actionInput = deleteForm.querySelector('input[name="action"]');
                if (!actionInput) {
                    actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    deleteForm.appendChild(actionInput);
                }
                actionInput.value = 'delete';

                const nameDisplay = document.getElementById('deleteModalItemName');
                if (nameDisplay && name) {
                    nameDisplay.textContent = name;
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
