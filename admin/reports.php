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

$pageTitle = 'Clinical Reports & SQL Queries';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin/dashboard.php'],
    ['label' => 'Reports', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Advanced Analytical Reports</h3>
        <p class="text-muted small mb-0">Live relational database queries with exportable metrics and query inspection</p>
    </div>
</div>

<!-- Nav Tabs for Reports -->
<ul class="nav nav-pills mb-4 gap-2 flex-wrap" id="reportTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'a') ? 'active' : ''; ?>" id="tab-a-btn" data-bs-toggle="pill" data-bs-target="#tab-a" type="button" role="tab">
            <i class="bi bi-link-45deg me-1"></i>(a) Patient-Doctor (JOIN)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'b') ? 'active' : ''; ?>" id="tab-b-btn" data-bs-toggle="pill" data-bs-target="#tab-b" type="button" role="tab">
            <i class="bi bi-filter me-1"></i>(b) Specialization Consults
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'c') ? 'active' : ''; ?>" id="tab-c-btn" data-bs-toggle="pill" data-bs-target="#tab-c" type="button" role="tab">
            <i class="bi bi-calendar-range me-1"></i>(c) Date Range (BETWEEN)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'd') ? 'active' : ''; ?>" id="tab-d-btn" data-bs-toggle="pill" data-bs-target="#tab-d" type="button" role="tab">
            <i class="bi bi-bar-chart me-1"></i>(d) Appts per Doctor (GROUP BY)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($activeTab === 'e') ? 'active' : ''; ?>" id="tab-e-btn" data-bs-toggle="pill" data-bs-target="#tab-e" type="button" role="tab">
            <i class="bi bi-funnel me-1"></i>(e) Doctors with &gt; N Appts (HAVING)
        </button>
    </li>
</ul>

<div class="tab-content" id="reportTabsContent">
    <!-- ──────────────────────────────────────────────
         REPORT A: Patient-Doctor Consultations (JOIN)
    ────────────────────────────────────────────── -->
    <div class="tab-pane fade <?php echo ($activeTab === 'a') ? 'show active' : ''; ?>" id="tab-a" role="tabpanel">
        <div class="card mb-4">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-semibold mb-1">Patient-Doctor Consultation Log</h5>
                    <p class="text-muted small mb-0">Multi-table relational JOIN combining Patient, Doctor, and Appointment tables</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-muted border px-3 py-2">Records: <?php echo count($resultsA); ?></span>
                    <a href="/medicarehub/admin/export_csv.php?report=a" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sqlCollapseA">
                        <i class="bi bi-code-slash me-1"></i>Show SQL
                    </button>
                </div>
            </div>

            <div class="collapse" id="sqlCollapseA">
                <div class="card-body bg-light border-bottom p-3">
                    <div class="sql-box"><?php echo e($sqlA); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Patient Name</th>
                            <th>Phone</th>
                            <th>Consulting Doctor</th>
                            <th>Specialization</th>
                            <th>Appointment Date</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultsA)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No appointments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resultsA as $idx => $r): ?>
                                <tr>
                                    <td class="ps-4 font-monospace text-muted"><?php echo $idx + 1; ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e($r['patient_name']); ?></td>
                                    <td><?php echo e($r['patient_phone']); ?></td>
                                    <td><?php echo e($r['doctor_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo e($r['specialization']); ?></span></td>
                                    <td><?php echo e(formatDate($r['appointment_date'])); ?></td>
                                    <td class="pe-4">
                                        <span class="badge <?php echo getStatusBadgeClass($r['status']); ?>">
                                            <i class="<?php echo getStatusIcon($r['status']); ?> me-1"></i><?php echo e($r['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────
         REPORT B: Patients by Doctor Specialization
    ────────────────────────────────────────────── -->
    <div class="tab-pane fade <?php echo ($activeTab === 'b') ? 'show active' : ''; ?>" id="tab-b" role="tabpanel">
        <div class="card mb-4">
            <div class="card-header bg-white py-3 px-4">
                <form method="GET" action="/medicarehub/admin/reports.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="b">
                    <div class="col-auto">
                        <label for="specSelect" class="form-label mb-0 fw-medium small">Choose Specialization:</label>
                    </div>
                    <div class="col-md-4">
                        <select id="specSelect" name="specialization" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($specializations as $s): ?>
                                <option value="<?php echo e($s); ?>" <?php echo ($selectedSpec === $s) ? 'selected' : ''; ?>>
                                    <?php echo e($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto ms-auto d-flex gap-2">
                        <span class="badge bg-light text-muted border px-3 py-2">Records: <?php echo count($resultsB); ?></span>
                        <a href="/medicarehub/admin/export_csv.php?report=b&specialization=<?php echo urlencode($selectedSpec); ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sqlCollapseB">
                            <i class="bi bi-code-slash me-1"></i>Show SQL
                        </button>
                    </div>
                </form>
            </div>

            <div class="collapse" id="sqlCollapseB">
                <div class="card-body bg-light border-bottom p-3">
                    <div class="sql-box"><?php echo e(str_replace('?', "'" . addslashes($selectedSpec) . "'", $sqlB)); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Patient Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Doctor Consulted</th>
                            <th class="pe-4">Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultsB)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No patient consultations found for <?php echo e($selectedSpec); ?>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resultsB as $idx => $r): ?>
                                <tr>
                                    <td class="ps-4 font-monospace text-muted"><?php echo $idx + 1; ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e($r['patient_name']); ?></td>
                                    <td><?php echo e((string)$r['age']); ?> yrs</td>
                                    <td><?php echo e($r['gender']); ?></td>
                                    <td><?php echo e($r['phone']); ?></td>
                                    <td><?php echo e($r['doctor_name']); ?></td>
                                    <td class="pe-4"><span class="badge bg-primary-subtle text-primary border"><?php echo e($r['specialization']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────
         REPORT C: Appointments Between Dates
    ────────────────────────────────────────────── -->
    <div class="tab-pane fade <?php echo ($activeTab === 'c') ? 'show active' : ''; ?>" id="tab-c" role="tabpanel">
        <div class="card mb-4">
            <div class="card-header bg-white py-3 px-4">
                <form method="GET" action="/medicarehub/admin/reports.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="c">
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-medium small">From:</label>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo e($fromDate); ?>" required>
                    </div>
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-medium small">To:</label>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo e($toDate); ?>" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    </div>
                    <div class="col-auto ms-auto d-flex gap-2">
                        <span class="badge bg-light text-muted border px-3 py-2">Records: <?php echo count($resultsC); ?></span>
                        <a href="/medicarehub/admin/export_csv.php?report=c&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sqlCollapseC">
                            <i class="bi bi-code-slash me-1"></i>Show SQL
                        </button>
                    </div>
                </form>
            </div>

            <div class="collapse" id="sqlCollapseC">
                <div class="card-body bg-light border-bottom p-3">
                    <div class="sql-box"><?php echo e("SELECT a.appointment_id, p.name AS patient_name, d.name AS doctor_name, d.specialization, a.appointment_date, a.status\nFROM Appointment a\nJOIN Patient p ON a.patient_id = p.patient_id\nJOIN Doctor d ON a.doctor_id = d.doctor_id\nWHERE a.appointment_date BETWEEN '$fromDate' AND '$toDate'\nORDER BY a.appointment_date ASC"); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Appt ID</th>
                            <th>Patient Name</th>
                            <th>Doctor Name</th>
                            <th>Specialization</th>
                            <th>Date</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultsC)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No appointments found in date interval <?php echo e($fromDate); ?> to <?php echo e($toDate); ?>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resultsC as $r): ?>
                                <tr>
                                    <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$r['appointment_id']); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e($r['patient_name']); ?></td>
                                    <td><?php echo e($r['doctor_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo e($r['specialization']); ?></span></td>
                                    <td><?php echo e(formatDate($r['appointment_date'])); ?></td>
                                    <td class="pe-4">
                                        <span class="badge <?php echo getStatusBadgeClass($r['status']); ?>">
                                            <i class="<?php echo getStatusIcon($r['status']); ?> me-1"></i><?php echo e($r['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────
         REPORT D: Number of Appointments Per Doctor (GROUP BY)
    ────────────────────────────────────────────── -->
    <div class="tab-pane fade <?php echo ($activeTab === 'd') ? 'show active' : ''; ?>" id="tab-d" role="tabpanel">
        <div class="card mb-4">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-semibold mb-1">Appointments Volume per Doctor</h5>
                    <p class="text-muted small mb-0">Aggregated appointment counts using SQL <code>GROUP BY</code> and <code>COUNT()</code></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-muted border px-3 py-2">Doctors: <?php echo count($resultsD); ?></span>
                    <a href="/medicarehub/admin/export_csv.php?report=d" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sqlCollapseD">
                        <i class="bi bi-code-slash me-1"></i>Show SQL
                    </button>
                </div>
            </div>

            <div class="collapse" id="sqlCollapseD">
                <div class="card-body bg-light border-bottom p-3">
                    <div class="sql-box"><?php echo e($sqlD); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Doctor Name</th>
                            <th>Department Specialization</th>
                            <th class="pe-4 text-end">Total Appointments Logged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultsD as $idx => $r): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted"><?php echo $idx + 1; ?></td>
                                <td class="fw-semibold text-dark"><?php echo e($r['doctor_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($r['specialization']); ?></span></td>
                                <td class="pe-4 text-end">
                                    <span class="badge bg-primary fs-6 px-3 py-1"><?php echo e((string)$r['appointment_count']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ──────────────────────────────────────────────
         REPORT E: Doctors with More Than N Appointments (HAVING)
    ────────────────────────────────────────────── -->
    <div class="tab-pane fade <?php echo ($activeTab === 'e') ? 'show active' : ''; ?>" id="tab-e" role="tabpanel">
        <div class="card mb-4">
            <div class="card-header bg-white py-3 px-4">
                <form method="GET" action="/medicarehub/admin/reports.php" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="e">
                    <div class="col-auto">
                        <label class="form-label mb-0 fw-medium small">Doctors Having Appointments &gt;</label>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="min_count" class="form-control form-control-sm" value="<?php echo e((string)$minAppointments); ?>" min="0" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter (HAVING)</button>
                    </div>
                    <div class="col-auto ms-auto d-flex gap-2">
                        <span class="badge bg-light text-muted border px-3 py-2">Qualifying Doctors: <?php echo count($resultsE); ?></span>
                        <a href="/medicarehub/admin/export_csv.php?report=e&min_count=<?php echo $minAppointments; ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#sqlCollapseE">
                            <i class="bi bi-code-slash me-1"></i>Show SQL
                        </button>
                    </div>
                </form>
            </div>

            <div class="collapse" id="sqlCollapseE">
                <div class="card-body bg-light border-bottom p-3">
                    <div class="sql-box"><?php echo e("SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count\nFROM Appointment a\nJOIN Doctor d ON a.doctor_id = d.doctor_id\nGROUP BY a.doctor_id, d.name, d.specialization\nHAVING COUNT(*) > $minAppointments\nORDER BY appointment_count DESC"); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Doctor Name</th>
                            <th>Specialization</th>
                            <th class="pe-4 text-end">Appointments Count (&gt; <?php echo $minAppointments; ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultsE)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    No doctors found exceeding <?php echo $minAppointments; ?> appointments. Try lowering the threshold N.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resultsE as $idx => $r): ?>
                                <tr>
                                    <td class="ps-4 font-monospace text-muted"><?php echo $idx + 1; ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e($r['doctor_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo e($r['specialization']); ?></span></td>
                                    <td class="pe-4 text-end">
                                        <span class="badge bg-success fs-6 px-3 py-1"><?php echo e((string)$r['appointment_count']); ?> appointments</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
