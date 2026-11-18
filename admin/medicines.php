<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $medId = (int)($_POST['id'] ?? 0);
    if ($medId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Medicine WHERE medicine_id = ?');
        $stmt->execute([$medId]);
        setFlash('success', 'Medicine removed from pharmacy inventory.');
    }
    header('Location: /medicarehub/admin/medicines.php');
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = '';

if (!empty($search)) {
    $whereClause = ' WHERE medicine_name LIKE ? OR type LIKE ? ';
    $params = ["%$search%", "%$search%"];
}

$countSql = "SELECT COUNT(*) FROM Medicine $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT * FROM Medicine $whereClause ORDER BY medicine_name ASC LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$pageTitle = 'Pharmacy & Medicines';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Medicines', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Pharmacy Catalog</h3>
        <p class="text-muted small mb-0">Manage inventory of medications, forms, and dispensing prices</p>
    </div>
    <a href="/medicarehub/admin/medicine_form.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Medicine
    </a>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/medicines.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name or type (e.g. Tablet, Capsule)..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin/medicines.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Total Products: <strong><?php echo $pagination['total_records']; ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Medicines Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">ID</th>
                    <th>Medicine Name</th>
                    <th>Dosage Form / Type</th>
                    <th>Unit Price</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-capsule fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No medications found in pharmacy catalog.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicines as $m): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$m['medicine_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($m['medicine_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-tag me-1"></i><?php echo e($m['type']); ?>
                                </span>
                            </td>
                            <td class="fw-medium text-success">
                                <?php echo formatCurrency((float)$m['price']); ?>
                            </td>
                            <td class="text-end pe-4">
                                <a href="/medicarehub/admin/medicine_form.php?id=<?php echo $m['medicine_id']; ?>" class="btn btn-sm btn-action" title="Edit Medicine" data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-action delete" title="Delete Medicine" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/medicines.php" data-id="<?php echo $m['medicine_id']; ?>" data-name="<?php echo e($m['medicine_name']); ?>">
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
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin/medicines.php' . (!empty($search) ? '?search=' . urlencode($search) : '')); ?>
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
