<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $patientId = (int)($_POST['id'] ?? 0);
    if ($patientId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Patient WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        setFlash('success', 'Patient record successfully deleted.');
    }
    header('Location: /medicarehub/admin/patients.php');
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = '';

if (!empty($search)) {
    $whereClause = ' WHERE name LIKE ? OR phone LIKE ? ';
    $params = ["%$search%", "%$search%"];
}

$countSql = "SELECT COUNT(*) FROM Patient $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT * FROM Patient $whereClause ORDER BY patient_id DESC LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Patients Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Patients', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Patients Directory</h3>
        <p class="text-muted small mb-0">Manage registered patient records and clinical history</p>
    </div>
    <a href="/medicarehub/admin/patient_form.php" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-2"></i>Add Patient
    </a>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/patients.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by patient name or phone..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin/patients.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Total Patients: <strong><?php echo $pagination['total_records']; ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">ID</th>
                    <th>Patient Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Phone</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No patients found matching your query.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$p['patient_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($p['name']); ?></div>
                            </td>
                            <td><?php echo e((string)$p['age']); ?> yrs</td>
                            <td>
                                <span class="badge <?php echo $p['gender'] === 'Male' ? 'bg-info-subtle text-info-emphasis' : ($p['gender'] === 'Female' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'); ?> border">
                                    <?php echo e($p['gender']); ?>
                                </span>
                            </td>
                            <td>
                                <i class="bi bi-telephone text-muted me-1 small"></i>
                                <?php echo e($p['phone']); ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="/medicarehub/admin/patient_form.php?id=<?php echo $p['patient_id']; ?>" class="btn btn-sm btn-action" title="Edit Patient" data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-action delete" title="Delete Patient" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/patients.php" data-id="<?php echo $p['patient_id']; ?>" data-name="<?php echo e($p['name']); ?>">
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
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin/patients.php' . (!empty($search) ? '?search=' . urlencode($search) : '')); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Specific delete button hook
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteModal"]');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const deleteForm = document.getElementById('deleteForm');
                
                // Add or update hidden inputs
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
