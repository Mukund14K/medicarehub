<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $doctorId = (int)($_POST['id'] ?? 0);
    if ($doctorId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Doctor WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        setFlash('success', 'Doctor record deleted.');
    }
    header('Location: /medicarehub/admin/doctors.php');
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = '';

if (!empty($search)) {
    $whereClause = ' WHERE name LIKE ? OR specialization LIKE ? OR phone LIKE ? ';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$countSql = "SELECT COUNT(*) FROM Doctor $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT d.*, COUNT(a.appointment_id) AS total_appointments 
        FROM Doctor d 
        LEFT JOIN Appointment a ON d.doctor_id = a.doctor_id 
        $whereClause 
        GROUP BY d.doctor_id 
        ORDER BY d.doctor_id ASC 
        LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$pageTitle = 'Doctors';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Doctors', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Doctors</h1>
        <p class="page-description">Medical staff directory, clinical specialties and schedules</p>
    </div>
    <a href="/medicarehub/admin/doctor_form.php" class="btn btn-primary">Add doctor</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/doctors.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, specialization, or phone" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin/doctors.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total doctors: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Doctors Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Doctor name</th>
                    <th>Specialization</th>
                    <th>Phone</th>
                    <th>Consultations</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No doctor records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $d): ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$d['doctor_id']); ?></td>
                            <td class="fw-medium"><?php echo e($d['name']); ?></td>
                            <td>
                                <span class="badge badge-status badge-status-neutral"><?php echo e($d['specialization']); ?></span>
                            </td>
                            <td class="tabular-nums"><?php echo e($d['phone']); ?></td>
                            <td class="tabular-nums"><?php echo e((string)$d['total_appointments']); ?></td>
                            <td class="text-end">
                                <a href="/medicarehub/admin/doctor_form.php?id=<?php echo $d['doctor_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/doctors.php" data-id="<?php echo $d['doctor_id']; ?>" data-name="<?php echo e($d['name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small tabular-nums">Showing <?php echo ($pagination['offset'] + 1); ?> to <?php echo min($pagination['offset'] + 8, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin/doctors.php' . (!empty($search) ? '?search=' . urlencode($search) : '')); ?>
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
