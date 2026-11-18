<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('patient');

$patientId = (int)($_SESSION['patient_id'] ?? 0);
$aptId = (int)($_GET['appointment_id'] ?? 0);

if ($aptId <= 0) {
    die("Invalid appointment reference.");
}

// Verify appointment belongs to this patient
$stmt = $pdo->prepare('
    SELECT a.*, p.patient_id, p.name AS patient_name, p.age, p.gender, p.phone AS patient_phone,
           d.name AS doctor_name, d.specialization, d.phone AS doctor_phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.patient_id = ?
');
$stmt->execute([$aptId, $patientId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    die("Prescription record not found or access denied.");
}

// Fetch prescribed medicines
$pStmt = $pdo->prepare('
    SELECT pr.*, m.medicine_name, m.type, m.price
    FROM Prescription pr
    JOIN Medicine m ON pr.medicine_id = m.medicine_id
    WHERE pr.appointment_id = ?
    ORDER BY pr.prescription_id ASC
');
$pStmt->execute([$aptId]);
$prescriptions = $pStmt->fetchAll();

$totalCost = 0.0;
foreach ($prescriptions as $p) {
    $totalCost += (float)$p['price'];
}

$pageTitle = 'Prescription Slip #RX-' . str_pad((string)$aptId, 4, '0', STR_PAD_LEFT);
$isPrintPage = true;
$noSidebar = true;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4" style="max-width: 850px;">
    <!-- Print action bar (hidden during print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print p-3 bg-white rounded-3 border">
        <div>
            <h5 class="mb-0 fw-semibold">Patient Prescription Slip</h5>
            <small class="text-muted">Appointment #<?php echo $aptId; ?> &middot; <?php echo e($appointment['doctor_name']); ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="/medicarehub/patient/prescriptions.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Prescriptions
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i>Print Slip
            </button>
        </div>
    </div>

    <!-- Official Prescription Paper Container -->
    <div class="card p-5 shadow-sm bg-white border" id="printableArea">
        <!-- Clinic Letterhead Header -->
        <div class="d-flex justify-content-between align-items-start pb-4 mb-4 border-bottom border-2 border-primary">
            <div class="d-flex align-items-center gap-3">
                <img src="/medicarehub/assets/img/logo.svg" alt="MediCare Hub" width="48" height="48">
                <div>
                    <h3 class="fw-bold mb-0 text-primary">MediCare Hub</h3>
                    <small class="text-uppercase tracking-wider fw-semibold text-muted" style="letter-spacing: 0.1em;">Multi-Specialty Polyclinic & Diagnostic Centre</small>
                </div>
            </div>
            <div class="text-end text-muted small">
                <div>123 Healthcare Avenue, Medical District</div>
                <div>Tech City, Karnataka 560001</div>
                <div><i class="bi bi-telephone me-1"></i>1800-MEDICARE | care@medicarehub.local</div>
            </div>
        </div>

        <!-- Two-column Patient & Doctor metadata block -->
        <div class="row g-4 mb-4 p-3 bg-light rounded-3">
            <div class="col-6">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Patient Details</div>
                <div class="fw-bold text-dark fs-5"><?php echo e($appointment['patient_name']); ?></div>
                <div class="text-muted small">Patient ID: <strong>#<?php echo e((string)$appointment['patient_id']); ?></strong></div>
                <div class="text-muted small">Demographics: <strong><?php echo e((string)$appointment['age']); ?> yrs, <?php echo e($appointment['gender']); ?></strong></div>
                <div class="text-muted small">Phone: <strong><?php echo e($appointment['patient_phone']); ?></strong></div>
            </div>
            <div class="col-6 text-end">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Consultation Reference</div>
                <div class="fw-bold text-dark fs-5"><?php echo e($appointment['doctor_name']); ?></div>
                <div class="text-primary small fw-semibold"><?php echo e($appointment['specialization']); ?></div>
                <div class="text-muted small">Date: <strong><?php echo formatDate($appointment['appointment_date']); ?></strong></div>
                <div class="text-muted small">Rx Number: <strong>RX-<?php echo str_pad((string)$aptId, 5, '0', STR_PAD_LEFT); ?></strong></div>
            </div>
        </div>

        <!-- Medical Rx Symbol -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="fs-3 fw-bold text-primary font-monospace" style="font-family: serif;">&#8478;</span>
            <span class="text-muted small text-uppercase fw-semibold tracking-wider">Prescribed Medications</span>
        </div>

        <!-- Itemized Medicines Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th>Medicine Name & Form</th>
                        <th>Dosage & Instructions</th>
                        <th style="width: 15%;">Duration</th>
                        <th class="text-end" style="width: 15%;">Unit Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No medicines prescribed for this consultation.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $idx => $p): ?>
                            <tr>
                                <td class="text-muted font-monospace"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($p['medicine_name']); ?></div>
                                    <small class="text-muted"><?php echo e($p['type']); ?></small>
                                </td>
                                <td><?php echo e($p['dosage']); ?></td>
                                <td><?php echo e((string)$p['duration_days']); ?> days</td>
                                <td class="text-end fw-medium"><?php echo formatCurrency((float)$p['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end fw-bold">Total Estimated Medication Cost:</th>
                        <th class="text-end fw-bold text-success fs-6"><?php echo formatCurrency($totalCost); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Instructions -->
        <div class="p-3 mb-5 rounded-2 bg-light border">
            <div class="fw-semibold small text-muted mb-1"><i class="bi bi-info-circle me-1"></i>Patient Instructions:</div>
            <p class="small text-muted mb-0">
                Please follow the dosage schedule strictly. Keep medicines out of reach of children. Store in a cool, dry place away from direct sunlight. Complete the entire antimicrobial course if prescribed.
            </p>
        </div>

        <!-- Doctor Signature Block -->
        <div class="d-flex justify-content-between align-items-end mt-5 pt-4">
            <div class="text-muted small">
                <div>Generated digitally via <strong>MediCare Hub</strong></div>
                <div>Valid without physical seal when verified online</div>
            </div>
            <div class="text-center" style="width: 240px;">
                <div class="signature-line mb-2" style="border-top: 1.5px solid #1e293b;"></div>
                <div class="fw-bold text-dark"><?php echo e($appointment['doctor_name']); ?></div>
                <div class="text-muted small"><?php echo e($appointment['specialization']); ?></div>
                <small class="text-muted" style="font-size: 0.75rem;">Authorized Medical Practitioner</small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
