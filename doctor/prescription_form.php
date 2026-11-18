<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('doctor');

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$aptId = (int)($_GET['appointment_id'] ?? 0);

if ($aptId <= 0) {
    setFlash('error', 'Invalid appointment reference.');
    header('Location: /medicarehub/doctor/appointments.php');
    exit;
}

// Verify appointment belongs to this doctor
$stmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone, d.name AS doctor_name, d.specialization
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.doctor_id = ?
');
$stmt->execute([$aptId, $doctorId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    setFlash('error', 'Appointment not found or not assigned to your account.');
    header('Location: /medicarehub/doctor/appointments.php');
    exit;
}

$errors = [];
$editPrescriptionId = (int)($_GET['edit'] ?? 0);
$editPrescription = null;

// Handle POST actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $medicineId = (int)($_POST['medicine_id'] ?? 0);
        $dosage = trim($_POST['dosage'] ?? '');
        $durationDays = (int)($_POST['duration_days'] ?? 0);
        $prescriptionId = (int)($_POST['prescription_id'] ?? 0);

        if ($medicineId <= 0) {
            $errors[] = 'Please select a medicine from the list.';
        }
        if (empty($dosage)) {
            $errors[] = 'Dosage instructions are required.';
        }
        if ($durationDays <= 0) {
            $errors[] = 'Duration must be at least 1 day.';
        }

        if (empty($errors)) {
            try {
                if ($action === 'edit' && $prescriptionId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE Prescription 
                        SET medicine_id = ?, dosage = ?, duration_days = ? 
                        WHERE prescription_id = ? AND appointment_id = ?
                    ');
                    $stmt->execute([$medicineId, $dosage, $durationDays, $prescriptionId, $aptId]);
                    setFlash('success', 'Prescription line item updated.');
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO Prescription (appointment_id, medicine_id, dosage, duration_days)
                        VALUES (?, ?, ?, ?)
                    ');
                    $stmt->execute([$aptId, $medicineId, $dosage, $durationDays]);
                    setFlash('success', 'Medicine added to prescription.');
                }
                header('Location: /medicarehub/doctor/prescription_form.php?appointment_id=' . $aptId);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $prescriptionId = (int)($_POST['prescription_id'] ?? 0);
        if ($prescriptionId > 0) {
            $stmt = $pdo->prepare('DELETE FROM Prescription WHERE prescription_id = ? AND appointment_id = ?');
            $stmt->execute([$prescriptionId, $aptId]);
            setFlash('success', 'Medicine removed from prescription.');
        }
        header('Location: /medicarehub/doctor/prescription_form.php?appointment_id=' . $aptId);
        exit;
    }
}

// Fetch all existing prescriptions for this appointment
$pStmt = $pdo->prepare('
    SELECT pr.*, m.medicine_name, m.type, m.price
    FROM Prescription pr
    JOIN Medicine m ON pr.medicine_id = m.medicine_id
    WHERE pr.appointment_id = ?
    ORDER BY pr.prescription_id ASC
');
$pStmt->execute([$aptId]);
$prescriptions = $pStmt->fetchAll();

// Calculate total cost
$totalCost = 0.0;
foreach ($prescriptions as $p) {
    $totalCost += (float)$p['price'];
}

// If edit mode requested, fetch existing prescription item
if ($editPrescriptionId > 0) {
    $eStmt = $pdo->prepare('SELECT * FROM Prescription WHERE prescription_id = ? AND appointment_id = ?');
    $eStmt->execute([$editPrescriptionId, $aptId]);
    $editPrescription = $eStmt->fetch();
}

// Fetch all available medicines for dropdown
$medicines = $pdo->query('SELECT medicine_id, medicine_name, type, price FROM Medicine ORDER BY medicine_name ASC')->fetchAll();

$pageTitle = 'Prescription Editor';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Prescription editor</h2>
        <p class="page-description">Appointment #<?php echo $aptId; ?> &middot; <?php echo e($appointment['patient_name']); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/medicarehub/doctor/appointments.php" class="btn btn-secondary">Appointments</a>
        <?php if (!empty($prescriptions)): ?>
            <a href="/medicarehub/doctor/prescription_print.php?appointment_id=<?php echo $aptId; ?>" target="_blank" class="btn btn-primary">Print slip</a>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Overview Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Patient</div>
                <div class="fw-medium"><?php echo e($appointment['patient_name']); ?></div>
                <div class="text-muted small"><?php echo e((string)$appointment['age']); ?> yrs, <?php echo e($appointment['gender']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Phone</div>
                <div class="tabular-nums"><?php echo e($appointment['phone']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Date</div>
                <div><?php echo formatDate($appointment['appointment_date']); ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Status</div>
                <div>
                    <span class="badge <?php echo getStatusBadgeClass($appointment['status']); ?>">
                        <?php echo e($appointment['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Prescribed Medicines List -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div>Prescribed medications</div>
                <span class="small text-muted"><?php echo count($prescriptions); ?> items</span>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Duration</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prescriptions)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">No medicines prescribed yet for this consultation.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $idx => $p): ?>
                                <tr>
                                    <td class="text-muted tabular-nums"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <div class="fw-medium"><?php echo e($p['medicine_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($p['type']); ?></div>
                                    </td>
                                    <td><?php echo e($p['dosage']); ?></td>
                                    <td><?php echo e((string)$p['duration_days']); ?> days</td>
                                    <td class="text-end tabular-nums"><?php echo formatCurrency((float)$p['price']); ?></td>
                                    <td class="text-end">
                                        <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $aptId; ?>&edit=<?php echo $p['prescription_id']; ?>" class="table-action-link">Edit</a>
                                        <form method="POST" action="" class="d-inline m-0" onsubmit="return confirm('Remove this medicine from prescription?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="prescription_id" value="<?php echo $p['prescription_id']; ?>">
                                            <button type="submit" class="table-action-link text-danger border-0 bg-transparent p-0">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($prescriptions)): ?>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end fw-medium">Total estimated cost:</th>
                                <th colspan="2" class="text-end fw-semibold tabular-nums"><?php echo formatCurrency($totalCost); ?></th>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Add / Edit Medicine Form -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <div><?php echo $editPrescription ? 'Edit medication' : 'Add medication'; ?></div>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-3" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $aptId; ?>" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editPrescription ? 'edit' : 'add'; ?>">
                    <?php if ($editPrescription): ?>
                        <input type="hidden" name="prescription_id" value="<?php echo $editPrescription['prescription_id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="medicine_id" class="form-label">Medicine</label>
                        <select class="form-select" id="medicine_id" name="medicine_id" required>
                            <option value="">Select medicine</option>
                            <?php foreach ($medicines as $m): ?>
                                <option value="<?php echo $m['medicine_id']; ?>" <?php echo ($editPrescription && (int)$editPrescription['medicine_id'] === (int)$m['medicine_id']) ? 'selected' : ''; ?>>
                                    <?php echo e($m['medicine_name']); ?> (<?php echo e($m['type']); ?>) - <?php echo formatCurrency((float)$m['price']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="dosage" class="form-label">Dosage instructions</label>
                        <input type="text" class="form-control" id="dosage" name="dosage" value="<?php echo e($editPrescription['dosage'] ?? ''); ?>" placeholder="e.g. 500mg twice daily after meals" required>
                        <div class="form-text">Specify strength, frequency, and timing.</div>
                    </div>

                    <div class="mb-4">
                        <label for="duration_days" class="form-label">Duration in days</label>
                        <input type="number" class="form-control" id="duration_days" name="duration_days" value="<?php echo e((string)($editPrescription['duration_days'] ?? '7')); ?>" min="1" max="365" required>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if ($editPrescription): ?>
                            <a href="/medicarehub/doctor/prescription_form.php?appointment_id=<?php echo $aptId; ?>" class="btn btn-secondary w-50">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary <?php echo $editPrescription ? 'w-50' : 'w-100'; ?>">
                            <?php echo $editPrescription ? 'Update medicine' : 'Add medicine'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
