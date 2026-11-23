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
        setFlash('success', 'Patient record deleted.');
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

$pageTitle = 'Patients';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Patients', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Patients</h1>
        <p class="page-description">Registered patients directory and demographics</p>
    </div>
    <a href="/medicarehub/admin/patient_form.php" class="btn btn-primary">Add patient</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/patients.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or phone" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin/patients.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total patients: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Patient name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Phone</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No patient records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$p['patient_id']); ?></td>
                            <td class="fw-medium"><?php echo e($p['name']); ?></td>
                            <td class="tabular-nums"><?php echo e((string)$p['age']); ?> yrs</td>
                            <td>
                                <?php echo e($p['gender']); ?>
                            </td>
                            <td class="tabular-nums"><?php echo e($p['phone']); ?></td>
                            <td class="text-end">
                                <a href="/medicarehub/admin/patient_form.php?id=<?php echo $p['patient_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/patients.php" data-id="<?php echo $p['patient_id']; ?>" data-name="<?php echo e($p['name']); ?>">Delete</button>
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
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin/patients.php' . (!empty($search) ? '?search=' . urlencode($search) : '')); ?>
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
