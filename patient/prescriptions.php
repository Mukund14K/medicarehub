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

$pageTitle = 'Prescriptions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Prescriptions</h1>
        <p class="page-description">Archived medication records and printable consultation slips</p>
    </div>
</div>

<?php if (empty($prescriptionsByAppointment)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">No prescription records found for this patient account.</div>
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
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-3">
                <div>
                    <div class="fw-semibold text-dark fs-6">Consultation with <?php echo e($apt['doctor_name']); ?> <span class="text-secondary fw-normal">(<?php echo e($apt['specialization']); ?>)</span></div>
                    <div class="small text-muted mt-1">Date: <?php echo formatDate($apt['appointment_date']); ?> &middot; Rx #RX-<?php echo str_pad((string)$apt['appointment_id'], 5, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-secondary">Estimated total: <strong class="text-dark tabular-nums"><?php echo formatCurrency($total); ?></strong></span>
                    <a href="/medicarehub/patient/prescription_print.php?appointment_id=<?php echo $apt['appointment_id']; ?>" target="_blank" class="btn btn-secondary btn-sm">Print slip</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 25%;">Medicine name</th>
                            <th style="width: 15%;">Form</th>
                            <th>Dosage instructions</th>
                            <th style="width: 15%;">Duration</th>
                            <th class="text-end" style="width: 120px;">Unit price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $idx => $it): ?>
                            <tr>
                                <td class="text-muted tabular-nums">#<?php echo $idx + 1; ?></td>
                                <td class="fw-medium"><?php echo e($it['medicine_name']); ?></td>
                                <td><?php echo e($it['type']); ?></td>
                                <td><?php echo e($it['dosage']); ?></td>
                                <td class="tabular-nums"><?php echo e((string)$it['duration_days']); ?> days</td>
                                <td class="text-end tabular-nums fw-medium"><?php echo formatCurrency((float)$it['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
