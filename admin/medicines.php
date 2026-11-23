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
        setFlash('success', 'Medicine removed from inventory.');
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

$pageTitle = 'Medicines';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Medicines', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Medicines</h1>
        <p class="page-description">Pharmacy inventory, dosage forms and unit prices</p>
    </div>
    <a href="/medicarehub/admin/medicine_form.php" class="btn btn-primary">Add medicine</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin/medicines.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or form (e.g. Tablet, Syrup)" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin/medicines.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total items: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Medicines Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Medicine name</th>
                    <th>Dosage form</th>
                    <th>Unit price</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No medicines found in catalog.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicines as $m): ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$m['medicine_id']); ?></td>
                            <td class="fw-medium"><?php echo e($m['medicine_name']); ?></td>
                            <td>
                                <?php echo e($m['type']); ?>
                            </td>
                            <td class="tabular-nums fw-medium">
                                <?php echo formatCurrency((float)$m['price']); ?>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/admin/medicine_form.php?id=<?php echo $m['medicine_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin/medicines.php" data-id="<?php echo $m['medicine_id']; ?>" data-name="<?php echo e($m['medicine_name']); ?>">Delete</button>
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
