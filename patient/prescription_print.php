<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('patient');

$patientId = (int)($_SESSION['patient_id'] ?? 0);
$aptId = (int)($_GET['appointment_id'] ?? 0);

if ($patientId <= 0 || $aptId <= 0) {
    header('Location: /medicarehub/patient/prescriptions.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT a.*, p.name AS patient_name, p.age, p.gender, p.phone AS patient_phone,
           d.name AS doctor_name, d.specialization, d.phone AS doctor_phone
    FROM Appointment a
    JOIN Patient p ON a.patient_id = p.patient_id
    JOIN Doctor d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = ? AND a.patient_id = ?
');
$stmt->execute([$aptId, $patientId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header('Location: /medicarehub/patient/prescriptions.php');
    exit;
}

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

$pageTitle = 'Prescription Slip - RX-' . str_pad((string)$aptId, 5, '0', STR_PAD_LEFT);
$isPrintPage = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4" style="max-width: 800px;">
    <!-- Print toolbar (hidden during print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print card p-3">
        <div>
            <div class="fw-semibold">Prescription Slip</div>
            <div class="small text-muted">Consultation #<?php echo $aptId; ?> &middot; <?php echo e($appointment['patient_name']); ?></div>
        </div>
        <div class="d-flex gap-2">
            <a href="/medicarehub/patient/prescriptions.php" class="btn btn-secondary btn-sm">Back</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">Print</button>
        </div>
    </div>

    <!-- Official Prescription Paper Container -->
    <div class="card p-5" id="printableArea">
        <!-- Clinic Header -->
        <div class="print-header d-flex justify-content-between align-items-start pb-3 mb-4 border-bottom">
            <div>
                <h2 class="h4 fw-bold mb-1">MediCare Hub</h2>
                <div class="small text-muted">Multi-Specialty Clinic & Diagnostic Centre</div>
            </div>
            <div class="text-end small text-muted">
                <div>123 Healthcare Avenue, Medical District</div>
                <div>Bangalore, Karnataka 560001</div>
                <div>Phone: 1800-633-4227 &middot; care@medicarehub.local</div>
            </div>
        </div>

        <!-- Ruled Metadata Section -->
        <div class="print-meta-grid row g-3 pb-3 mb-4 border-bottom">
            <div class="col-6">
                <div class="small text-muted">Patient details</div>
                <div class="fw-semibold"><?php echo e($appointment['patient_name']); ?></div>
                <div class="small text-muted">ID: #<?php echo e((string)$appointment['patient_id']); ?> &middot; <?php echo e((string)$appointment['age']); ?> yrs, <?php echo e($appointment['gender']); ?></div>
                <div class="small text-muted tabular-nums">Phone: <?php echo e($appointment['patient_phone']); ?></div>
            </div>
            <div class="col-6 text-end">
                <div class="small text-muted">Consulting specialist</div>
                <div class="fw-semibold"><?php echo e($appointment['doctor_name']); ?></div>
                <div class="small text-muted"><?php echo e($appointment['specialization']); ?></div>
                <div class="small text-muted">Date: <?php echo formatDate($appointment['appointment_date']); ?> &middot; Rx #RX-<?php echo str_pad((string)$aptId, 5, '0', STR_PAD_LEFT); ?></div>
            </div>
        </div>

        <div class="fw-semibold mb-2">Prescribed medications</div>

        <!-- Itemized Medicine Table -->
        <div class="table-responsive mb-4">
            <table class="table print-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Medicine & Form</th>
                        <th>Dosage & Instructions</th>
                        <th style="width: 100px;">Duration</th>
                        <th class="text-end" style="width: 110px;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">No medicines recorded for this consultation.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $idx => $p): ?>
                            <tr>
                                <td class="tabular-nums text-muted"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="fw-medium"><?php echo e($p['medicine_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($p['type']); ?></div>
                                </td>
                                <td><?php echo e($p['dosage']); ?></td>
                                <td><?php echo e((string)$p['duration_days']); ?> days</td>
                                <td class="text-end tabular-nums"><?php echo formatCurrency((float)$p['price']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end fw-semibold">Total:</th>
                        <th class="text-end tabular-nums fw-semibold"><?php echo formatCurrency($totalCost); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Clinical Instructions -->
        <div class="p-3 mb-5 border rounded" style="background-color: var(--bg);">
            <div class="small fw-semibold mb-1">General instructions</div>
            <div class="small text-muted">Take prescribed medications as directed with meals unless instructed otherwise. Store in a cool, dry location away from moisture and direct sunlight. Complete the entire course.</div>
        </div>

        <!-- Doctor Signature Area -->
        <div class="signature-area d-flex justify-content-between align-items-end pt-4">
            <div class="small text-muted">
                <div>MediCare Hub Digital Clinical Record</div>
                <div>System generated verification slip</div>
            </div>
            <div class="signature-box text-center" style="width: 220px;">
                <div class="signature-rule" style="border-top: 1px solid #1B2430; margin-bottom: 6px;"></div>
                <div class="fw-semibold"><?php echo e($appointment['doctor_name']); ?></div>
                <div class="small text-muted"><?php echo e($appointment['specialization']); ?></div>
                <div class="small text-muted">Authorized Medical Practitioner</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
