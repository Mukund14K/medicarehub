<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$medicine = [
    'medicine_name' => '',
    'type' => '',
    'price' => ''
];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Medicine WHERE medicine_id = ?');
    $stmt->execute([$id]);
    $medData = $stmt->fetch();
    if (!$medData) {
        setFlash('error', 'Medicine item not found.');
        header('Location: /medicarehub/admin/medicines.php');
        exit;
    }
    $medicine = $medData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $name = trim($_POST['medicine_name'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $price = trim($_POST['price'] ?? '');

    if (empty($name)) {
        $errors[] = 'Medicine brand/generic name is required.';
    }

    if (empty($type)) {
        $errors[] = 'Dosage form or type is required.';
    }

    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        $errors[] = 'Price must be a valid non-negative number.';
    }

    $medicine['medicine_name'] = $name;
    $medicine['type'] = $type;
    $medicine['price'] = $price;

    if (empty($errors)) {
        try {
            $formattedPrice = number_format((float)$price, 2, '.', '');
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Medicine SET medicine_name = ?, type = ?, price = ? WHERE medicine_id = ?');
                $stmt->execute([$name, $type, $formattedPrice, $id]);
                setFlash('success', 'Medicine catalog entry updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Medicine (medicine_name, type, price) VALUES (?, ?, ?)');
                $stmt->execute([$name, $type, $formattedPrice]);
                setFlash('success', 'New medicine added to pharmacy inventory.');
            }
            header('Location: /medicarehub/admin/medicines.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit Medicine' : 'Add New Medicine';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Medicines', 'url' => '/medicarehub/admin/medicines.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add New', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title"><?php echo $isEdit ? 'Edit Medicine' : 'Add Medication'; ?></h3>
        <p class="text-muted small mb-0">Define formulation, administration type, and unit dispensing cost</p>
    </div>
    <a href="/medicarehub/admin/medicines.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Inventory
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 px-3 mb-4 rounded-3" role="alert">
                        <div class="fw-semibold small mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Please correct the following:</div>
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label for="medicine_name" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-capsule"></i></span>
                            <input type="text" class="form-control border-start-0" id="medicine_name" name="medicine_name" value="<?php echo e($medicine['medicine_name']); ?>" placeholder="e.g. Amoxicillin, Paracetamol" required autofocus>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="type" class="form-label">Dosage Type / Form <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="type" name="type" value="<?php echo e($medicine['type']); ?>" placeholder="e.g. Tablet, Capsule, Syrup, Topical" list="typeList" required>
                            <datalist id="typeList">
                                <option value="Tablet">
                                <option value="Capsule">
                                <option value="Syrup">
                                <option value="Injection">
                                <option value="Topical">
                                <option value="Drops">
                                <option value="Inhaler">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="price" class="form-label">Unit Price (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₹</span>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo e((string)$medicine['price']); ?>" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/medicines.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i><?php echo $isEdit ? 'Save Changes' : 'Save Medicine'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
