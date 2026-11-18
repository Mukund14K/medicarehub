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
        setFlash('success', 'Doctor record successfully removed.');
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

$pageTitle = 'Doctors Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Doctors', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Medical Specialists</h3>
        <p class="text-muted small mb-0">Manage clinic medical staff, specialties and schedules</p>
    </div>
    <a href="/medicarehub/admin/doctor_form.php" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-2"></i>Add Doctor
    </a>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/doctors.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, specialization, phone..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin/doctors.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Total Doctors: <strong><?php echo $pagination['total_records']; ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Doctors Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">ID</th>
                    <th>Doctor Name</th>
                    <th>Specialization</th>
                    <th>Phone</th>
                    <th>Total Consultations</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-person-badge fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No doctors found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $d): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$d['doctor_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($d['name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border">
                                    <i class="bi bi-award me-1"></i><?php echo e($d['specialization']); ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-telephone text-muted me-1 small"></i>
                                <?php echo e($d['phone']); ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo e((string)$d['total_appointments']); ?> visits</span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="/medicarehub/admin/doctor_form.php?id=<?php echo $d['doctor_id']; ?>" class="btn btn-sm btn-action" title="Edit Doctor" data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-action delete" title="Delete Doctor" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/doctors.php" data-id="<?php echo $d['doctor_id']; ?>" data-name="<?php echo e($d['name']); ?>">
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
                    nameDisplay.textContent = 'Record: ' + name;
                }
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
