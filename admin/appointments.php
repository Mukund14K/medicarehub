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
        setFlash('success', 'Appointment record deleted.');
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

$pageTitle = 'Appointments';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Appointments', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Appointments</h1>
        <p class="page-description">Clinic-wide appointment scheduling and consultation log</p>
    </div>
    <a href="/medicarehub/admin/appointment_form.php" class="btn btn-primary">Create appointment</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/appointments.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search patient or doctor name" value="<?php echo e($search); ?>">
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
                    <a href="/medicarehub/admin/appointments.php" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total appointments: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No appointments found matching current criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <?php
                        $badgeClass = match ($a['status']) {
                            'Completed' => 'badge-status-completed',
                            'Cancelled' => 'badge-status-cancelled',
                            default     => 'badge-status-scheduled',
                        };
                        ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td>
                                <div class="fw-medium"><?php echo e($a['patient_name']); ?></div>
                                <div class="text-muted small tabular-nums"><?php echo e($a['patient_phone']); ?></div>
                            </td>
                            <td class="fw-medium"><?php echo e($a['doctor_name']); ?></td>
                            <td>
                                <?php echo e($a['specialization']); ?>
                            </td>
                            <td class="tabular-nums"><?php echo formatDate($a['appointment_date']); ?></td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo e($a['status']); ?></span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/admin/appointment_form.php?id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/appointments.php" data-id="<?php echo $a['appointment_id']; ?>" data-name="Appointment #<?php echo $a['appointment_id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <?php
        $queryParams = [];
        if (!empty($search)) $queryParams['search'] = $search;
        if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
        $pageUrl = '/medicarehub/admin/appointments.php' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small tabular-nums">Showing <?php echo ($pagination['offset'] + 1); ?> to <?php echo min($pagination['offset'] + 10, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], $pageUrl); ?>
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
                const deleteItemName = document.getElementById('deleteItemName');
                const deleteIdInput = document.getElementById('deleteIdInput');

                if (deleteForm) deleteForm.action = this.getAttribute('data-action') || '';
                if (deleteIdInput) deleteIdInput.value = id;
                if (deleteItemName) deleteItemName.textContent = name ? '"' + name + '"' : 'this record';
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
