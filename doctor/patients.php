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

$pageTitle = 'Patients';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title">Assigned patients</h2>
        <p class="page-description">Patients who have consulted with you in the clinic</p>
    </div>
</div>

<!-- Search Toolbar -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="/medicarehub/doctor/patients.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search" style="display: inline-block !important;"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search patient name or phone..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/doctor/patients.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-muted small">
                Showing <?php echo count($patients); ?> patient records
            </div>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient name</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Phone</th>
                <th>Visits</th>
                <th>Last visit</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="7" class="empty-state">No patient records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td class="text-muted">#<?php echo e((string)$p['patient_id']); ?></td>
                        <td class="fw-medium"><?php echo e($p['name']); ?></td>
                        <td><?php echo e((string)$p['age']); ?></td>
                        <td><?php echo e($p['gender']); ?></td>
                        <td class="tabular-nums"><?php echo e($p['phone']); ?></td>
                        <td class="tabular-nums"><?php echo e((string)$p['visit_count']); ?></td>
                        <td><?php echo formatDate($p['last_visit']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
