<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('doctor');

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    header('Location: /medicarehub/login.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$params = [$doctorId];
$searchClause = '';

if (!empty($search)) {
    $searchClause = ' AND (p.name LIKE ? OR p.phone LIKE ?) ';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT p.*, 
        COUNT(a.appointment_id) AS visit_count, 
        MAX(a.appointment_date) AS last_visit
        FROM Patient p
        JOIN Appointment a ON p.patient_id = a.patient_id
        WHERE a.doctor_id = ? $searchClause
        GROUP BY p.patient_id, p.name, p.age, p.gender, p.phone
        ORDER BY last_visit DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'My Patients';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/doctor/dashboard.php'],
    ['label' => 'My Patients', 'url' => null],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h3 class="page-title">Assigned Patients</h3>
        <p class="text-muted small mb-0">List of patients who have consulted with you</p>
    </div>
</div>

<!-- Toolbar -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/doctor/patients.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search patient name or phone..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/doctor/patients.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Unique Patients: <strong><?php echo count($patients); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Patient ID</th>
                    <th>Patient Name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Contact Phone</th>
                    <th>Total Visits</th>
                    <th class="pe-4">Last Visit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No patients found in your consultation records.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td class="ps-4 font-monospace text-muted">#<?php echo e((string)$p['patient_id']); ?></td>
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e($p['name']); ?></div>
                            </td>
                            <td><?php echo e((string)$p['age']); ?> yrs</td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo e($p['gender']); ?></span>
                            </td>
                            <td>
                                <i class="bi bi-telephone text-muted me-1 small"></i>
                                <?php echo e($p['phone']); ?>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border">
                                    <?php echo e((string)$p['visit_count']); ?> consultations
                                </span>
                            </td>
                            <td class="pe-4">
                                <?php echo $p['last_visit'] ? formatDate($p['last_visit']) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
