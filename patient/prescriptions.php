<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('patient');

$patientId = (int)($_SESSION['patient_id'] ?? 0);
if ($patientId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

// Fetch all appointments that have prescriptions for this patient
$stmt = $pdo->prepare('
    SELECT a.appointment_id, a.appointment_date, a.status, d.name AS doctor_name, d.specialization
    FROM Appointment a
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ?
      AND EXISTS (SELECT 1 FROM Prescription pr WHERE pr.appointment_id = a.appointment_id)
    ORDER BY a.appointment_date DESC, a.appointment_id DESC
');
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

// For each appointment, fetch the prescribed medicines
$prescriptionsByAppointment = [];
foreach ($appointments as $apt) {
    $pStmt = $pdo->prepare('
        SELECT pr.*, m.medicine_name, m.type, m.price
        FROM Prescription pr
        JOIN Medicine m ON pr.medicine_id = m.medicine_id
        WHERE pr.appointment_id = ?
        ORDER BY pr.prescription_id ASC
    ');
    $pStmt->execute([$apt['appointment_id']]);
    $items = $pStmt->fetchAll();

    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += (float)$it['price'];
    }

    $prescriptionsByAppointment[] = [
        'appointment' => $apt,
        'items' => $items,
        'total' => $subtotal
    ];
}

$pageTitle = 'My Prescriptions';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/patient/dashboard.php'],
    ['label' => 'Prescriptions', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Prescription Archive</h3>
        <p class="text-muted small mb-0">Review past medication regimens, dosing guidelines, and print official slips</p>
    </div>
</div>

<?php if (empty($prescriptionsByAppointment)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-file-earmark-medical fs-1 d-block mb-3 text-secondary opacity-50"></i>
            <h5 class="fw-semibold text-dark">No Prescriptions Issued Yet</h5>
            <p class="small text-muted mb-0">When your doctor prescribes medications during an appointment, they will be archived here.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($prescriptionsByAppointment as $block): ?>
        <?php 
        $apt = $block['appointment'];
        $items = $block['items'];
        $total = $block['total'];
        ?>
        <div class="card mb-4">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-semibold mb-0">
                        Consultation with <?php echo e($apt['doctor_name']); ?>
                    </h5>
                    <small class="text-muted">
                        <span class="badge bg-light text-dark border me-2"><?php echo e($apt['specialization']); ?></span>
                        <i class="bi bi-calendar3 me-1"></i><?php echo formatDate($apt['appointment_date']); ?>
                        &middot; Slip #RX-<?php echo str_pad((string)$apt['appointment_id'], 5, '0', STR_PAD_LEFT); ?>
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success-emphasis border px-3 py-2">
                        Total: <?php echo formatCurrency($total); ?>
                    </span>
                    <a href="/medicarehub/patient/prescription_print.php?appointment_id=<?php echo $apt['appointment_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-printer me-1"></i>Print Slip
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Medicine Name</th>
                            <th>Form</th>
                            <th>Dosage Instructions</th>
                            <th>Duration</th>
                            <th class="text-end pe-4">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $it): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted"><?php echo $idx + 1; ?></td>
                                <td class="fw-semibold text-dark"><?php echo e($it['medicine_name']); ?></td>
                                <td><span class="badge bg-light text-muted border"><?php echo e($it['type']); ?></span></td>
                                <td><?php echo e($it['dosage']); ?></td>
                                <td><?php echo e((string)$it['duration_days']); ?> days</td>
                                <td class="text-end pe-4 fw-medium text-success"><?php echo formatCurrency((float)$it['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
