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
        $errors['name'] = 'Medicine name is required.';
    }

    if (empty($type)) {
        $errors['type'] = 'Dosage form or type is required.';
    }

    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        $errors['price'] = 'Price must be a valid non-negative number.';
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
                setFlash('success', 'Medicine added to inventory.');
            }
            header('Location: /medicarehub/admin/medicines.php');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit medicine' : 'Add medicine';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Medicines', 'url' => '/medicarehub/admin/medicines.php'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add medicine', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit medicine' : 'Add medicine'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update formulation, dosage form, and unit price' : 'Add a new medication to the pharmacy catalog'; ?></p>
    </div>
    <a href="/medicarehub/admin/medicines.php" class="btn btn-secondary">Back to medicines</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger mb-4">
                        <span><?php echo e($errors['db']); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-section">
                        <div class="form-section-title">Medication details</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="medicine_name" class="form-label">Medicine name & strength</label>
                                <input type="text" class="form-control" id="medicine_name" name="medicine_name" value="<?php echo e($medicine['medicine_name']); ?>" placeholder="e.g. Amoxicillin 500mg, Paracetamol 650mg" required autofocus>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="form-error"><?php echo e($errors['name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="type" class="form-label">Dosage form</label>
                                <input type="text" class="form-control" id="type" name="type" value="<?php echo e($medicine['type']); ?>" placeholder="e.g. Tablet, Capsule, Syrup, Topical" list="typeList" required>
                                <datalist id="typeList">
                                    <option value="Tablet">
                                    <option value="Capsule">
                                    <option value="Syrup">
                                    <option value="Injection">
                                    <option value="Topical Gel">
                                    <option value="Drops">
                                    <option value="Inhaler">
                                </datalist>
                                <?php if (isset($errors['type'])): ?>
                                    <span class="form-error"><?php echo e($errors['type']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="price" class="form-label">Unit price (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo e((string)$medicine['price']); ?>" min="0" placeholder="0.00" required>
                                <?php if (isset($errors['price'])): ?>
                                    <span class="form-error"><?php echo e($errors['price']); ?></span>
                                <?php else: ?>
                                    <span class="form-text">Per-unit dispensing price in INR.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin/medicines.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save medicine' : 'Add medicine'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
