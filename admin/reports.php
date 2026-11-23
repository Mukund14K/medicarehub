<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$activeTab = $_GET['tab'] ?? 'a';

// Fetch specializations for Report B
$specializations = $pdo->query('SELECT DISTINCT specialization FROM Doctor ORDER BY specialization ASC')->fetchAll(PDO::FETCH_COLUMN);

// Report A: Patient-Doctor Appointments (JOIN)
$sqlA = "SELECT p.name AS patient_name, p.phone AS patient_phone, d.name AS doctor_name, d.specialization, a.appointment_date, a.status
FROM Appointment a
JOIN Patient p ON a.patient_id = p.patient_id
JOIN Doctor d ON a.doctor_id = d.doctor_id
ORDER BY a.appointment_date DESC";
$stmtA = $pdo->query($sqlA);
$resultsA = $stmtA->fetchAll();

// Report B: Patients by Specialization
$selectedSpec = $_GET['specialization'] ?? ($specializations[0] ?? '');
$sqlB = "SELECT DISTINCT p.name AS patient_name, p.age, p.gender, p.phone, d.name AS doctor_name, d.specialization
FROM Appointment a
JOIN Patient p ON a.patient_id = p.patient_id
JOIN Doctor d ON a.doctor_id = d.doctor_id
WHERE d.specialization = ?
ORDER BY p.name ASC";
$stmtB = $pdo->prepare($sqlB);
$stmtB->execute([$selectedSpec]);
$resultsB = $stmtB->fetchAll();

// Report C: Appointments Between Dates
$fromDate = $_GET['from_date'] ?? '2026-11-01';
$toDate = $_GET['to_date'] ?? '2026-11-30';
$sqlC = "SELECT a.appointment_id, p.name AS patient_name, d.name AS doctor_name, d.specialization, a.appointment_date, a.status
FROM Appointment a
JOIN Patient p ON a.patient_id = p.patient_id
JOIN Doctor d ON a.doctor_id = d.doctor_id
WHERE a.appointment_date BETWEEN ? AND ?
ORDER BY a.appointment_date ASC";
$stmtC = $pdo->prepare($sqlC);
$stmtC->execute([$fromDate, $toDate]);
$resultsC = $stmtC->fetchAll();

// Report D: Appointments Per Doctor (GROUP BY & COUNT)
$sqlD = "SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count
FROM Appointment a
JOIN Doctor d ON a.doctor_id = d.doctor_id
GROUP BY a.doctor_id, d.name, d.specialization
ORDER BY appointment_count DESC";
$stmtD = $pdo->query($sqlD);
$resultsD = $stmtD->fetchAll();

// Report E: Doctors with More Than N Appointments (HAVING)
$minAppointments = isset($_GET['min_count']) ? (int)$_GET['min_count'] : 10;
if ($minAppointments < 0) {
    $minAppointments = 10;
}
$sqlE = "SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count
FROM Appointment a
JOIN Doctor d ON a.doctor_id = d.doctor_id
GROUP BY a.doctor_id, d.name, d.specialization
HAVING COUNT(*) > ?
ORDER BY appointment_count DESC";
$stmtE = $pdo->prepare($sqlE);
$stmtE->execute([$minAppointments]);
$resultsE = $stmtE->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Analytical reports</h2>
        <p class="page-description">Relational database query inspection and parameterized data exports</p>
    </div>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-pills mb-4 gap-2 flex-wrap" id="reportTabs" role="tablist">
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'a') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="?tab=a">Patient-Doctor consults</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'b') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="?tab=b">Specialization consults</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'c') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="?tab=c">Date range analysis</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'd') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="?tab=d">Doctor appointment counts</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'e') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="?tab=e">High volume specialists</a>
    </li>
</ul>

<?php if ($activeTab === 'a'): ?>
    <!-- REPORT A -->
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <div>Patient-doctor consultation log</div>
                <div class="small text-muted font-normal">Relational JOIN combining patient, doctor, and appointment records</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Showing <?php echo count($resultsA); ?> records</span>
                <a href="/medicarehub/admin/export_csv.php?report=a" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body py-2">
            <details>
                <summary class="query-toggle-btn">View query</summary>
                <pre class="report-query-block"><?php echo e($sqlA); ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Patient name</th>
                        <th>Phone</th>
                        <th>Consulting doctor</th>
                        <th>Specialization</th>
                        <th>Appointment date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultsA)): ?>
                        <tr><td colspan="7" class="empty-state">No appointments recorded.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultsA as $idx => $r): ?>
                            <tr>
                                <td class="text-muted tabular-nums"><?php echo $idx + 1; ?></td>
                                <td class="fw-medium"><?php echo e($r['patient_name']); ?></td>
                                <td class="tabular-nums"><?php echo e($r['patient_phone']); ?></td>
                                <td><?php echo e($r['doctor_name']); ?></td>
                                <td><?php echo e($r['specialization']); ?></td>
                                <td><?php echo formatDate($r['appointment_date']); ?></td>
                                <td>
                                    <span class="<?php echo getStatusBadgeClass($r['status']); ?>">
                                        <?php echo e($r['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'b'): ?>
    <!-- REPORT B -->
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <div>Consultations by medical specialization</div>
                <div class="small text-muted font-normal">Filter distinct patient consultations by medical department</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Showing <?php echo count($resultsB); ?> records</span>
                <a href="/medicarehub/admin/export_csv.php?report=b&specialization=<?php echo urlencode($selectedSpec); ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/medicarehub/admin/reports.php" class="row g-2 align-items-center mb-2">
                <input type="hidden" name="tab" value="b">
                <div class="col-auto">
                    <label for="specSelect" class="form-label mb-0">Specialization</label>
                </div>
                <div class="col-md-4">
                    <select id="specSelect" name="specialization" class="form-select">
                        <?php foreach ($specializations as $s): ?>
                            <option value="<?php echo e($s); ?>" <?php echo ($selectedSpec === $s) ? 'selected' : ''; ?>>
                                <?php echo e($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Run</button>
                </div>
            </form>
            <details class="mt-2">
                <summary class="query-toggle-btn">View query</summary>
                <pre class="report-query-block"><?php echo e(str_replace('?', "'" . addslashes($selectedSpec) . "'", $sqlB)); ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Patient name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Doctor name</th>
                        <th>Specialization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultsB)): ?>
                        <tr><td colspan="7" class="empty-state">No patients found for this specialization.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultsB as $idx => $r): ?>
                            <tr>
                                <td class="text-muted tabular-nums"><?php echo $idx + 1; ?></td>
                                <td class="fw-medium"><?php echo e($r['patient_name']); ?></td>
                                <td><?php echo e((string)$r['age']); ?></td>
                                <td><?php echo e($r['gender']); ?></td>
                                <td class="tabular-nums"><?php echo e($r['phone']); ?></td>
                                <td><?php echo e($r['doctor_name']); ?></td>
                                <td><?php echo e($r['specialization']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'c'): ?>
    <!-- REPORT C -->
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <div>Consultations across date range</div>
                <div class="small text-muted font-normal">Filter appointments between specific consultation dates</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Showing <?php echo count($resultsC); ?> records</span>
                <a href="/medicarehub/admin/export_csv.php?report=c&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/medicarehub/admin/reports.php" class="row g-2 align-items-center mb-2">
                <input type="hidden" name="tab" value="c">
                <div class="col-auto">
                    <label for="fromDate" class="form-label mb-0">From</label>
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control" id="fromDate" name="from_date" value="<?php echo e($fromDate); ?>">
                </div>
                <div class="col-auto">
                    <label for="toDate" class="form-label mb-0">To</label>
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control" id="toDate" name="to_date" value="<?php echo e($toDate); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Run</button>
                </div>
            </form>
            <details class="mt-2">
                <summary class="query-toggle-btn">View query</summary>
                <pre class="report-query-block"><?php echo e("SELECT a.appointment_id, p.name AS patient_name, d.name AS doctor_name, d.specialization, a.appointment_date, a.status\nFROM Appointment a\nJOIN Patient p ON a.patient_id = p.patient_id\nJOIN Doctor d ON a.doctor_id = d.doctor_id\nWHERE a.appointment_date BETWEEN '{$fromDate}' AND '{$toDate}'\nORDER BY a.appointment_date ASC"); ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultsC)): ?>
                        <tr><td colspan="6" class="empty-state">No appointments recorded within this date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultsC as $r): ?>
                            <tr>
                                <td class="text-muted">#<?php echo e((string)$r['appointment_id']); ?></td>
                                <td class="fw-medium"><?php echo e($r['patient_name']); ?></td>
                                <td><?php echo e($r['doctor_name']); ?></td>
                                <td><?php echo e($r['specialization']); ?></td>
                                <td><?php echo formatDate($r['appointment_date']); ?></td>
                                <td>
                                    <span class="<?php echo getStatusBadgeClass($r['status']); ?>">
                                        <?php echo e($r['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'd'): ?>
    <!-- REPORT D -->
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <div>Total appointments per doctor</div>
                <div class="small text-muted font-normal">Aggregated consultation counts grouped by doctor</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Showing <?php echo count($resultsD); ?> records</span>
                <a href="/medicarehub/admin/export_csv.php?report=d" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body py-2">
            <details>
                <summary class="query-toggle-btn">View query</summary>
                <pre class="report-query-block"><?php echo e($sqlD); ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Doctor name</th>
                        <th>Specialization</th>
                        <th class="text-end">Total consultations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultsD)): ?>
                        <tr><td colspan="4" class="empty-state">No doctor consultation data found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultsD as $idx => $r): ?>
                            <tr>
                                <td class="text-muted tabular-nums"><?php echo $idx + 1; ?></td>
                                <td class="fw-medium"><?php echo e($r['doctor_name']); ?></td>
                                <td><?php echo e($r['specialization']); ?></td>
                                <td class="text-end tabular-nums fw-medium"><?php echo e((string)$r['appointment_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'e'): ?>
    <!-- REPORT E -->
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <div>High volume specialists</div>
                <div class="small text-muted font-normal">Doctors whose total appointment count strictly exceeds threshold N</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Showing <?php echo count($resultsE); ?> records</span>
                <a href="/medicarehub/admin/export_csv.php?report=e&min_count=<?php echo $minAppointments; ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/medicarehub/admin/reports.php" class="row g-2 align-items-center mb-2">
                <input type="hidden" name="tab" value="e">
                <div class="col-auto">
                    <label for="minCount" class="form-label mb-0">Minimum consultations threshold</label>
                </div>
                <div class="col-auto">
                    <input type="number" class="form-control" id="minCount" name="min_count" value="<?php echo $minAppointments; ?>" min="0" style="width: 100px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Run</button>
                </div>
            </form>
            <details class="mt-2">
                <summary class="query-toggle-btn">View query</summary>
                <pre class="report-query-block"><?php echo e("SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count\nFROM Appointment a\nJOIN Doctor d ON a.doctor_id = d.doctor_id\nGROUP BY a.doctor_id, d.name, d.specialization\nHAVING COUNT(*) > {$minAppointments}\nORDER BY appointment_count DESC"); ?></pre>
            </details>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Doctor name</th>
                        <th>Specialization</th>
                        <th class="text-end">Total consultations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultsE)): ?>
                        <tr><td colspan="4" class="empty-state">No doctors exceeded the threshold of <?php echo $minAppointments; ?> consultations.</td></tr>
                    <?php else: ?>
                        <?php foreach ($resultsE as $idx => $r): ?>
                            <tr>
                                <td class="text-muted tabular-nums"><?php echo $idx + 1; ?></td>
                                <td class="fw-medium"><?php echo e($r['doctor_name']); ?></td>
                                <td><?php echo e($r['specialization']); ?></td>
                                <td class="text-end tabular-nums fw-medium"><?php echo e((string)$r['appointment_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
