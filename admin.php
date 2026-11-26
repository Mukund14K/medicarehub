<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$view = $_GET['view'] ?? 'patients';
if (!in_array($view, ['patients', 'patient_form', 'doctors', 'doctor_form', 'medicines', 'medicine_form', 'appointments', 'appointment_form', 'reports', 'sql_runner', 'export_csv'], true)) {
    $view = 'patients';
}

if ($view === 'export_csv') {
$report = $_GET['report'] ?? 'a';
$data = [];
$filename = 'report_' . $report . '_' . date('Ymd_His') . '.csv';

switch ($report) {
    case 'a':
        $filename = 'patient_doctor_appointments_' . date('Ymd') . '.csv';
        $sql = "SELECT p.name AS patient_name, p.phone AS patient_phone, d.name AS doctor_name, d.specialization, a.appointment_date, a.status
                FROM Appointment a
                JOIN Patient p ON a.patient_id = p.patient_id
                JOIN Doctor d ON a.doctor_id = d.doctor_id
                ORDER BY a.appointment_date DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'b':
        $specialization = $_GET['specialization'] ?? '';
        $filename = 'consultations_' . preg_replace('/[^a-zA-Z0-9]/', '_', $specialization) . '_' . date('Ymd') . '.csv';
        $sql = "SELECT DISTINCT p.name AS patient_name, p.age, p.gender, p.phone, d.name AS doctor_name, d.specialization
                FROM Appointment a
                JOIN Patient p ON a.patient_id = p.patient_id
                JOIN Doctor d ON a.doctor_id = d.doctor_id
                WHERE d.specialization = ?
                ORDER BY p.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$specialization]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'c':
        $fromDate = $_GET['from_date'] ?? date('Y-m-01');
        $toDate = $_GET['to_date'] ?? date('Y-m-d');
        $filename = 'appointments_between_' . $fromDate . '_to_' . $toDate . '.csv';
        $sql = "SELECT a.appointment_id, p.name AS patient_name, d.name AS doctor_name, d.specialization, a.appointment_date, a.status
                FROM Appointment a
                JOIN Patient p ON a.patient_id = p.patient_id
                JOIN Doctor d ON a.doctor_id = d.doctor_id
                WHERE a.appointment_date BETWEEN ? AND ?
                ORDER BY a.appointment_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fromDate, $toDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'd':
        $filename = 'appointments_per_doctor_' . date('Ymd') . '.csv';
        $sql = "SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count
                FROM Appointment a
                JOIN Doctor d ON a.doctor_id = d.doctor_id
                GROUP BY a.doctor_id, d.name, d.specialization
                ORDER BY appointment_count DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'e':
        $minCount = isset($_GET['min_count']) ? (int)$_GET['min_count'] : 10;
        $filename = 'doctors_having_more_than_' . $minCount . '_appointments.csv';
        $sql = "SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count
                FROM Appointment a
                JOIN Doctor d ON a.doctor_id = d.doctor_id
                GROUP BY a.doctor_id, d.name, d.specialization
                HAVING COUNT(*) > ?
                ORDER BY appointment_count DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$minCount]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    default:
        die("Invalid report identifier.");
}

exportCSV($data, $filename);
    exit;
}

switch ($view) {
    case 'patients':
// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $patientId = (int)($_POST['id'] ?? 0);
    if ($patientId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Patient WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        setFlash('success', 'Patient record deleted.');
    }
    header('Location: /medicarehub/admin.php?view=patients');
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = '';

if (!empty($search)) {
    $whereClause = ' WHERE name LIKE ? OR phone LIKE ? ';
    $params = ["%$search%", "%$search%"];
}

$countSql = "SELECT COUNT(*) FROM Patient $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT * FROM Patient $whereClause ORDER BY patient_id DESC LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Patients';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Patients', 'url' => null],
];
        break;

    case 'patient_form':
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$patient = [
    'name' => '',
    'age' => '',
    'gender' => '',
    'phone' => ''
];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Patient WHERE patient_id = ?');
    $stmt->execute([$id]);
    $patientData = $stmt->fetch();
    if (!$patientData) {
        setFlash('error', 'Patient record not found.');
        header('Location: /medicarehub/admin.php?view=patients');
        exit;
    }
    $patient = $patientData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $name = trim($_POST['name'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors['name'] = 'Full name is required.';
    }

    if ($age === '' || !is_numeric($age) || (int)$age < 0 || (int)$age > 150) {
        $errors['age'] = 'Age must be a valid number between 0 and 150.';
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors['gender'] = 'Please select a valid gender option.';
    }

    if (!validatePhone($phone)) {
        $errors['phone'] = 'Phone number must be exactly 10 digits.';
    }

    $patient['name'] = $name;
    $patient['age'] = $age;
    $patient['gender'] = $gender;
    $patient['phone'] = $phone;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Patient SET name = ?, age = ?, gender = ?, phone = ? WHERE patient_id = ?');
                $stmt->execute([$name, (int)$age, $gender, $phone, $id]);
                setFlash('success', 'Patient record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Patient (name, age, gender, phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, (int)$age, $gender, $phone]);
                setFlash('success', 'Patient record created.');
            }
            header('Location: /medicarehub/admin.php?view=patients');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit patient' : 'Add patient';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Patients', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add patient', 'url' => null],
];
        break;

    case 'doctors':
// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $doctorId = (int)($_POST['id'] ?? 0);
    if ($doctorId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Doctor WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        setFlash('success', 'Doctor record deleted.');
    }
    header('Location: /medicarehub/admin.php?view=doctors');
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = '';

if (!empty($search)) {
    $whereClause = ' WHERE name LIKE ? OR specialization LIKE ? OR phone LIKE ? ';
    $params = ["%$search%", "%$search%", "%$search%"];
}

$countSql = "SELECT COUNT(*) FROM Doctor $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT d.*, COUNT(a.appointment_id) AS total_appointments 
        FROM Doctor d 
        LEFT JOIN Appointment a ON d.doctor_id = a.doctor_id 
        $whereClause 
        GROUP BY d.doctor_id 
        ORDER BY d.doctor_id ASC 
        LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$pageTitle = 'Doctors';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Doctors', 'url' => null],
];
        break;

    case 'doctor_form':
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$doctor = [
    'name' => '',
    'specialization' => '',
    'phone' => ''
];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Doctor WHERE doctor_id = ?');
    $stmt->execute([$id]);
    $doctorData = $stmt->fetch();
    if (!$doctorData) {
        setFlash('error', 'Doctor record not found.');
        header('Location: /medicarehub/admin.php?view=doctors');
        exit;
    }
    $doctor = $doctorData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $name = trim($_POST['name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name)) {
        $errors['name'] = 'Doctor full name is required.';
    }

    if (empty($specialization)) {
        $errors['specialization'] = 'Clinical specialization is required.';
    }

    if (!validatePhone($phone)) {
        $errors['phone'] = 'Phone number must be exactly 10 digits.';
    }

    $doctor['name'] = $name;
    $doctor['specialization'] = $specialization;
    $doctor['phone'] = $phone;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Doctor SET name = ?, specialization = ?, phone = ? WHERE doctor_id = ?');
                $stmt->execute([$name, $specialization, $phone, $id]);
                setFlash('success', 'Doctor record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Doctor (name, specialization, phone) VALUES (?, ?, ?)');
                $stmt->execute([$name, $specialization, $phone]);
                setFlash('success', 'Doctor record created.');
            }
            header('Location: /medicarehub/admin.php?view=doctors');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit doctor' : 'Add doctor';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Doctors', 'url' => '/medicarehub/admin.php?view=doctors'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add doctor', 'url' => null],
];
        break;

    case 'medicines':
// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $medId = (int)($_POST['id'] ?? 0);
    if ($medId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Medicine WHERE medicine_id = ?');
        $stmt->execute([$medId]);
        setFlash('success', 'Medicine removed from inventory.');
    }
    header('Location: /medicarehub/admin.php?view=medicines');
    exit;
}

// Search and Pagination
$search = trim($_GET['search'] ?? '');
$params = [];
$whereClause = '';

if (!empty($search)) {
    $whereClause = ' WHERE medicine_name LIKE ? OR type LIKE ? ';
    $params = ["%$search%", "%$search%"];
}

$countSql = "SELECT COUNT(*) FROM Medicine $whereClause";
$pagination = paginate($pdo, $countSql, $params, 8);

$sql = "SELECT * FROM Medicine $whereClause ORDER BY medicine_name ASC LIMIT " . $pagination['offset'] . ", 8";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$pageTitle = 'Medicines';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Medicines', 'url' => null],
];
        break;

    case 'medicine_form':
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
        header('Location: /medicarehub/admin.php?view=medicines');
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
            header('Location: /medicarehub/admin.php?view=medicines');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit medicine' : 'Add medicine';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Medicines', 'url' => '/medicarehub/admin.php?view=medicines'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Add medicine', 'url' => null],
];
        break;

    case 'appointments':
// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    checkCSRF();
    $aptId = (int)($_POST['id'] ?? 0);
    if ($aptId > 0) {
        $stmt = $pdo->prepare('DELETE FROM Appointment WHERE appointment_id = ?');
        $stmt->execute([$aptId]);
        setFlash('success', 'Appointment record deleted.');
    }
    header('Location: /medicarehub/admin.php?view=appointments');
    exit;
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(p.name LIKE ? OR d.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['Scheduled', 'Completed', 'Cancelled'], true)) {
    $whereConditions[] = 'a.status = ?';
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? ' WHERE ' . implode(' AND ', $whereConditions) : '';

// Count query for pagination
$countSql = "SELECT COUNT(*) 
             FROM Appointment a 
             JOIN Patient p ON a.patient_id = p.patient_id 
             JOIN Doctor d ON a.doctor_id = d.doctor_id 
             $whereClause";
$pagination = paginate($pdo, $countSql, $params, 10);

// Fetch page records
$sql = "SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, d.name AS doctor_name, d.specialization 
        FROM Appointment a 
        JOIN Patient p ON a.patient_id = p.patient_id 
        JOIN Doctor d ON a.doctor_id = d.doctor_id 
        $whereClause 
        ORDER BY a.appointment_date DESC, a.appointment_id DESC 
        LIMIT " . $pagination['offset'] . ", 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Appointments';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Appointments', 'url' => null],
];
        break;

    case 'appointment_form':
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$appointment = [
    'patient_id' => '',
    'doctor_id' => '',
    'appointment_date' => date('Y-m-d'),
    'status' => 'Scheduled'
];
$errors = [];

// Fetch patients for dropdown
$patients = $pdo->query('SELECT patient_id, name, phone FROM Patient ORDER BY name ASC')->fetchAll();

// Fetch doctors for dropdown
$doctors = $pdo->query('SELECT doctor_id, name, specialization FROM Doctor ORDER BY name ASC')->fetchAll();

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM Appointment WHERE appointment_id = ?');
    $stmt->execute([$id]);
    $aptData = $stmt->fetch();
    if (!$aptData) {
        setFlash('error', 'Appointment record not found.');
        header('Location: /medicarehub/admin.php?view=appointments');
        exit;
    }
    $appointment = $aptData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();

    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $status = trim($_POST['status'] ?? 'Scheduled');

    if ($patientId <= 0) {
        $errors['patient_id'] = 'Please select a registered patient.';
    }

    if ($doctorId <= 0) {
        $errors['doctor_id'] = 'Please select a consulting doctor.';
    }

    if (empty($appointmentDate) || !strtotime($appointmentDate)) {
        $errors['appointment_date'] = 'Please provide a valid consultation date.';
    }

    if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'], true)) {
        $errors['status'] = 'Please select a valid appointment status.';
    }

    $appointment['patient_id'] = $patientId;
    $appointment['doctor_id'] = $doctorId;
    $appointment['appointment_date'] = $appointmentDate;
    $appointment['status'] = $status;

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('UPDATE Appointment SET patient_id = ?, doctor_id = ?, appointment_date = ?, status = ? WHERE appointment_id = ?');
                $stmt->execute([$patientId, $doctorId, $appointmentDate, $status, $id]);
                setFlash('success', 'Appointment record updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO Appointment (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$patientId, $doctorId, $appointmentDate, $status]);
                setFlash('success', 'Appointment scheduled.');
            }
            header('Location: /medicarehub/admin.php?view=appointments');
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Database operation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit appointment' : 'Create appointment';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/medicarehub/admin.php?view=patients'],
    ['label' => 'Appointments', 'url' => '/medicarehub/admin.php?view=appointments'],
    ['label' => $isEdit ? 'Edit #' . $id : 'Create appointment', 'url' => null],
];
        break;

    case 'reports':
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
        break;

    case 'sql_runner':
$sqlQuery = '';
$results = null;
$columns = [];
$rowCount = 0;
$affectedRows = null;
$execTimeMs = null;
$errorMsg = '';
$queryType = '';

$sampleQueries = [
    'All patients' => 'SELECT patient_id, name, age, gender, phone FROM Patient ORDER BY patient_id DESC;',
    'All doctors' => 'SELECT doctor_id, name, specialization, phone FROM Doctor ORDER BY name ASC;',
    'Scheduled appointments' => "SELECT a.appointment_id, p.name AS patient, d.name AS doctor, d.specialization, a.appointment_date, a.status\nFROM Appointment a\nJOIN Patient p ON a.patient_id = p.patient_id\nJOIN Doctor d ON a.doctor_id = d.doctor_id\nWHERE a.status = 'Scheduled'\nORDER BY a.appointment_date ASC;",
    'Prescription details' => "SELECT pr.prescription_id, a.appointment_id, p.name AS patient, m.medicine_name, pr.dosage, pr.duration_days, m.price\nFROM Prescription pr\nJOIN Appointment a ON pr.appointment_id = a.appointment_id\nJOIN Patient p ON a.patient_id = p.patient_id\nJOIN Medicine m ON pr.medicine_id = m.medicine_id\nORDER BY pr.prescription_id DESC;",
    'Appointments per doctor' => "SELECT d.name AS doctor_name, d.specialization, COUNT(a.appointment_id) AS total_appointments\nFROM Doctor d\nLEFT JOIN Appointment a ON d.doctor_id = a.doctor_id\nGROUP BY d.doctor_id, d.name, d.specialization\nORDER BY total_appointments DESC;",
    'Show tables' => 'SHOW TABLES;'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF();
    $sqlQuery = trim($_POST['sql_query'] ?? '');

    if ($sqlQuery === '') {
        $errorMsg = 'Please enter an SQL query to execute.';
    } else {
        // Strip trailing semicolon for clean single-statement check
        $trimmedForCheck = rtrim($sqlQuery, "; \t\n\r\0\x0B");

        // Block destructive DDL statements
        if (preg_match('/^\s*(DROP|TRUNCATE|ALTER|CREATE|GRANT|REVOKE)\b/i', $trimmedForCheck)) {
            $errorMsg = 'DDL and administrative statements (DROP, TRUNCATE, ALTER, CREATE, GRANT, REVOKE) are disabled in the query runner.';
        } else {
            $isReadQuery = (bool) preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $trimmedForCheck);
            $startTime = microtime(true);

            try {
                if ($isReadQuery) {
                    $queryType = 'SELECT';
                    $stmt = $pdo->query($sqlQuery);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $rowCount = count($results);
                    if ($rowCount > 0) {
                        $columns = array_keys($results[0]);
                    } else {
                        $colCount = $stmt->columnCount();
                        for ($i = 0; $i < $colCount; $i++) {
                            $meta = $stmt->getColumnMeta($i);
                            if ($meta && isset($meta['name'])) {
                                $columns[] = $meta['name'];
                            }
                        }
                    }
                } else {
                    $queryType = 'WRITE';
                    $affectedRows = $pdo->exec($sqlQuery);
                }
                $execTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();
            }
        }
    }
}

$pageTitle = 'SQL Query Runner';
        break;

}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($view === 'patients'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Patients</h1>
        <p class="page-description">Registered patients directory and demographics</p>
    </div>
    <a href="/medicarehub/admin.php?view=patient_form" class="btn btn-primary">Add patient</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin.php?view=patients" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="patients">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or phone" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin.php?view=patients" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total patients: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Patient name</th>
                    <th>Age</th>
                    <th>Gender</th>
                    <th>Phone</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No patient records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$p['patient_id']); ?></td>
                            <td class="fw-medium"><?php echo e($p['name']); ?></td>
                            <td class="tabular-nums"><?php echo e((string)$p['age']); ?> yrs</td>
                            <td>
                                <?php echo e($p['gender']); ?>
                            </td>
                            <td class="tabular-nums"><?php echo e($p['phone']); ?></td>
                            <td class="text-end">
                                <a href="/medicarehub/admin.php?view=patient_form&id=<?php echo $p['patient_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin.php?view=patients" data-id="<?php echo $p['patient_id']; ?>" data-name="<?php echo e($p['name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small tabular-nums">Showing <?php echo ($pagination['offset'] + 1); ?> to <?php echo min($pagination['offset'] + 8, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin.php?view=patients' . (!empty($search) ? '&search=' . urlencode($search) : '')); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteModal"]');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const deleteForm = document.getElementById('deleteForm');
                const deleteItemName = document.getElementById('deleteItemName');
                const deleteIdInput = document.getElementById('deleteIdInput');

                if (deleteForm) deleteForm.action = this.getAttribute('data-action') || '';
                if (deleteIdInput) deleteIdInput.value = id;
                if (deleteItemName) deleteItemName.textContent = name ? '"' + name + '"' : 'this record';
            });
        });
    });
</script>

<?php elseif ($view === 'patient_form'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit patient' : 'Add patient'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update demographic and contact details' : 'Register a new patient record'; ?></p>
    </div>
    <a href="/medicarehub/admin.php?view=patients" class="btn btn-secondary">Back to patients</a>
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
                        <div class="form-section-title">Demographics</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($patient['name']); ?>" placeholder="e.g. Ramesh Kumar" required autofocus>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="form-error"><?php echo e($errors['name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($patient['phone']); ?>" placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <span class="form-error"><?php echo e($errors['phone']); ?></span>
                                <?php else: ?>
                                    <span class="form-text">Enter 10 digits without country code or spaces.</span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="age" class="form-label">Age (years)</label>
                                <input type="number" class="form-control" id="age" name="age" value="<?php echo e((string)$patient['age']); ?>" min="0" max="150" placeholder="e.g. 35" required>
                                <?php if (isset($errors['age'])): ?>
                                    <span class="form-error"><?php echo e($errors['age']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo ($patient['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($patient['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($patient['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($errors['gender'])): ?>
                                    <span class="form-error"><?php echo e($errors['gender']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin.php?view=patients" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save patient' : 'Add patient'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'doctors'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Doctors</h1>
        <p class="page-description">Medical staff directory, clinical specialties and schedules</p>
    </div>
    <a href="/medicarehub/admin.php?view=doctor_form" class="btn btn-primary">Add doctor</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin.php?view=doctors" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="doctors">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, specialization, or phone" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin.php?view=doctors" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total doctors: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Doctors Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Doctor name</th>
                    <th>Specialization</th>
                    <th>Phone</th>
                    <th>Consultations</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No doctor records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($doctors as $d): ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$d['doctor_id']); ?></td>
                            <td class="fw-medium"><?php echo e($d['name']); ?></td>
                            <td>
                                <?php echo e($d['specialization']); ?>
                            </td>
                            <td class="tabular-nums"><?php echo e($d['phone']); ?></td>
                            <td class="tabular-nums"><?php echo e((string)$d['total_appointments']); ?></td>
                            <td class="text-end">
                                <a href="/medicarehub/admin.php?view=doctor_form&id=<?php echo $d['doctor_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin.php?view=doctors" data-id="<?php echo $d['doctor_id']; ?>" data-name="<?php echo e($d['name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small tabular-nums">Showing <?php echo ($pagination['offset'] + 1); ?> to <?php echo min($pagination['offset'] + 8, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin.php?view=doctors' . (!empty($search) ? '&search=' . urlencode($search) : '')); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteModal"]');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const deleteForm = document.getElementById('deleteForm');
                const deleteItemName = document.getElementById('deleteItemName');
                const deleteIdInput = document.getElementById('deleteIdInput');

                if (deleteForm) deleteForm.action = this.getAttribute('data-action') || '';
                if (deleteIdInput) deleteIdInput.value = id;
                if (deleteItemName) deleteItemName.textContent = name ? '"' + name + '"' : 'this record';
            });
        });
    });
</script>

<?php elseif ($view === 'doctor_form'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit doctor' : 'Add doctor'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update clinical specialty and contact details' : 'Register a new clinical specialist'; ?></p>
    </div>
    <a href="/medicarehub/admin.php?view=doctors" class="btn btn-secondary">Back to doctors</a>
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
                        <div class="form-section-title">Specialist details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Doctor name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($doctor['name']); ?>" placeholder="e.g. Dr. Rajesh Mehta" required autofocus>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="form-error"><?php echo e($errors['name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo e($doctor['specialization']); ?>" placeholder="e.g. Cardiology" list="specialtyList" required>
                                <datalist id="specialtyList">
                                    <option value="Cardiology">
                                    <option value="Dermatology">
                                    <option value="Orthopedics">
                                    <option value="Pediatrics">
                                    <option value="Neurology">
                                    <option value="General Medicine">
                                </datalist>
                                <?php if (isset($errors['specialization'])): ?>
                                    <span class="form-error"><?php echo e($errors['specialization']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($doctor['phone']); ?>" placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <span class="form-error"><?php echo e($errors['phone']); ?></span>
                                <?php else: ?>
                                    <span class="form-text">Direct clinic or mobile contact line.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin.php?view=doctors" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save doctor' : 'Add doctor'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'medicines'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Medicines</h1>
        <p class="page-description">Pharmacy inventory, dosage forms and unit prices</p>
    </div>
    <a href="/medicarehub/admin.php?view=medicine_form" class="btn btn-primary">Add medicine</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin.php?view=medicines" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="medicines">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or form (e.g. Tablet, Syrup)" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="/medicarehub/admin.php?view=medicines" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total items: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Medicines Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Medicine name</th>
                    <th>Dosage form</th>
                    <th>Unit price</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No medicines found in catalog.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($medicines as $m): ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$m['medicine_id']); ?></td>
                            <td class="fw-medium"><?php echo e($m['medicine_name']); ?></td>
                            <td>
                                <?php echo e($m['type']); ?>
                            </td>
                            <td class="tabular-nums fw-medium">
                                <?php echo formatCurrency((float)$m['price']); ?>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/admin.php?view=medicine_form&id=<?php echo $m['medicine_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin.php?view=medicines" data-id="<?php echo $m['medicine_id']; ?>" data-name="<?php echo e($m['medicine_name']); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small tabular-nums">Showing <?php echo ($pagination['offset'] + 1); ?> to <?php echo min($pagination['offset'] + 8, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], '/medicarehub/admin.php?view=medicines' . (!empty($search) ? '&search=' . urlencode($search) : '')); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteModal"]');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const deleteForm = document.getElementById('deleteForm');
                const deleteItemName = document.getElementById('deleteItemName');
                const deleteIdInput = document.getElementById('deleteIdInput');

                if (deleteForm) deleteForm.action = this.getAttribute('data-action') || '';
                if (deleteIdInput) deleteIdInput.value = id;
                if (deleteItemName) deleteItemName.textContent = name ? '"' + name + '"' : 'this record';
            });
        });
    });
</script>

<?php elseif ($view === 'medicine_form'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit medicine' : 'Add medicine'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update formulation, dosage form, and unit price' : 'Add a new medication to the pharmacy catalog'; ?></p>
    </div>
    <a href="/medicarehub/admin.php?view=medicines" class="btn btn-secondary">Back to medicines</a>
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
                        <a href="/medicarehub/admin.php?view=medicines" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save medicine' : 'Add medicine'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'appointments'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Appointments</h1>
        <p class="page-description">Clinic-wide appointment scheduling and consultation log</p>
    </div>
    <a href="/medicarehub/admin.php?view=appointment_form" class="btn btn-primary">Create appointment</a>
</div>

<!-- Toolbar -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="/medicarehub/admin.php?view=appointments" class="row g-2 align-items-center">
        <input type="hidden" name="view" value="appointments">
            <div class="col-md-5">
                <div class="search-input-group">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search patient or doctor name" value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="Scheduled" <?php echo ($statusFilter === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="/medicarehub/admin.php?view=appointments" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto text-secondary small tabular-nums">
                Total appointments: <?php echo $pagination['total_records']; ?>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No appointments found matching current criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a): ?>
                        <?php
                        $statusClass = match ($a['status']) {
                            'Completed' => 'status-completed',
                            'Cancelled' => 'status-cancelled',
                            default     => 'status-scheduled',
                        };
                        ?>
                        <tr>
                            <td class="text-secondary tabular-nums">#<?php echo e((string)$a['appointment_id']); ?></td>
                            <td>
                                <div class="fw-medium"><?php echo e($a['patient_name']); ?></div>
                                <div class="text-muted small tabular-nums"><?php echo e($a['patient_phone']); ?></div>
                            </td>
                            <td class="fw-medium"><?php echo e($a['doctor_name']); ?></td>
                            <td>
                                <?php echo e($a['specialization']); ?>
                            </td>
                            <td class="tabular-nums"><?php echo formatDate($a['appointment_date']); ?></td>
                            <td>
                                <span class="<?php echo $statusClass; ?>"><?php echo e($a['status']); ?></span>
                            </td>
                            <td class="text-end">
                                <a href="/medicarehub/admin.php?view=appointment_form&id=<?php echo $a['appointment_id']; ?>" class="table-action-link">Edit</a>
                                <button type="button" class="table-action-link text-danger border-0 bg-transparent p-0" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="/medicarehub/admin.php?view=appointments" data-id="<?php echo $a['appointment_id']; ?>" data-name="Appointment #<?php echo $a['appointment_id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <?php
        $queryParams = [];
        if (!empty($search)) $queryParams['search'] = $search;
        if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
        $pageUrl = '/medicarehub/admin.php?view=appointments' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
        ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small tabular-nums">Showing <?php echo ($pagination['offset'] + 1); ?> to <?php echo min($pagination['offset'] + 10, $pagination['total_records']); ?> of <?php echo $pagination['total_records']; ?> records</span>
            <?php echo renderPagination($pagination['total_pages'], $pagination['current_page'], $pageUrl); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteModal"]');
        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const deleteForm = document.getElementById('deleteForm');
                const deleteItemName = document.getElementById('deleteItemName');
                const deleteIdInput = document.getElementById('deleteIdInput');

                if (deleteForm) deleteForm.action = this.getAttribute('data-action') || '';
                if (deleteIdInput) deleteIdInput.value = id;
                if (deleteItemName) deleteItemName.textContent = name ? '"' + name + '"' : 'this record';
            });
        });
    });
</script>

<?php elseif ($view === 'appointment_form'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo $isEdit ? 'Edit appointment' : 'Create appointment'; ?></h1>
        <p class="page-description"><?php echo $isEdit ? 'Update appointment assignment or consultation status' : 'Schedule a clinical consultation for a registered patient'; ?></p>
    </div>
    <a href="/medicarehub/admin.php?view=appointments" class="btn btn-secondary">Back to appointments</a>
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
                        <div class="form-section-title">Consultation details</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="patient_id" class="form-label">Patient</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo $p['patient_id']; ?>" <?php echo ((int)$appointment['patient_id'] === (int)$p['patient_id']) ? 'selected' : ''; ?>>
                                            <?php echo e($p['name']); ?> (<?php echo e($p['phone']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['patient_id'])): ?>
                                    <span class="form-error"><?php echo e($errors['patient_id']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="doctor_id" class="form-label">Consulting doctor</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                    <option value="">Select doctor</option>
                                    <?php foreach ($doctors as $d): ?>
                                        <option value="<?php echo $d['doctor_id']; ?>" <?php echo ((int)$appointment['doctor_id'] === (int)$d['doctor_id']) ? 'selected' : ''; ?>>
                                            <?php echo e($d['name']); ?> — <?php echo e($d['specialization']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['doctor_id'])): ?>
                                    <span class="form-error"><?php echo e($errors['doctor_id']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="appointment_date" class="form-label">Consultation date</label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" value="<?php echo e($appointment['appointment_date']); ?>" required>
                                <?php if (isset($errors['appointment_date'])): ?>
                                    <span class="form-error"><?php echo e($errors['appointment_date']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Scheduled" <?php echo ($appointment['status'] === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="Completed" <?php echo ($appointment['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo ($appointment['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                    <span class="form-error"><?php echo e($errors['status']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="/medicarehub/admin.php?view=appointments" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save appointment' : 'Create appointment'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($view === 'reports'): ?>
<div class="page-header">
    <div>
        <h2 class="page-title">Analytical reports</h2>
        <p class="page-description">Relational database query inspection and parameterized data exports</p>
    </div>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-pills mb-4 gap-2 flex-wrap" id="reportTabs" role="tablist">
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'a') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="/medicarehub/admin.php?view=reports&tab=a">Patient-Doctor consults</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'b') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="/medicarehub/admin.php?view=reports&tab=b">Specialization consults</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'c') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="/medicarehub/admin.php?view=reports&tab=c">Date range analysis</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'd') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="/medicarehub/admin.php?view=reports&tab=d">Doctor appointment counts</a>
    </li>
    <li class="nav-item">
        <a class="btn <?php echo ($activeTab === 'e') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm" href="/medicarehub/admin.php?view=reports&tab=e">High volume specialists</a>
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
                <a href="/medicarehub/admin.php?view=export_csv&report=a" class="btn btn-secondary btn-sm">Export CSV</a>
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
                <a href="/medicarehub/admin.php?view=export_csv&report=b&specialization=<?php echo urlencode($selectedSpec); ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/medicarehub/admin.php?view=reports" class="row g-2 align-items-center mb-2">
        <input type="hidden" name="view" value="reports">
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
                <a href="/medicarehub/admin.php?view=export_csv&report=c&from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/medicarehub/admin.php?view=reports" class="row g-2 align-items-center mb-2">
        <input type="hidden" name="view" value="reports">
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
                <a href="/medicarehub/admin.php?view=export_csv&report=d" class="btn btn-secondary btn-sm">Export CSV</a>
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
                <a href="/medicarehub/admin.php?view=export_csv&report=e&min_count=<?php echo $minAppointments; ?>" class="btn btn-secondary btn-sm">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="/medicarehub/admin.php?view=reports" class="row g-2 align-items-center mb-2">
        <input type="hidden" name="view" value="reports">
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

<?php elseif ($view === 'sql_runner'): ?>
<div class="page-header">
    <div>
        <h1 class="page-title">SQL Query Runner</h1>
        <p class="page-description">Execute SQL queries directly against the clinic_db database</p>
    </div>
</div>

<!-- Query Editor Card -->
<div class="card mb-4">
    <div class="card-header">
        <div>Query editor</div>
        <span class="text-muted small">Database: clinic_db</span>
    </div>
    <div class="card-body">
        <form method="POST" action="/medicarehub/admin.php?view=sql_runner" id="sqlRunnerForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="mb-3">
                <label for="sql_query" class="form-label">SQL statement</label>
                <textarea
                    name="sql_query"
                    id="sql_query"
                    rows="5"
                    class="form-control font-monospace"
                    style="font-size: 13px; line-height: 1.5; height: auto;"
                    placeholder="SELECT * FROM Patient LIMIT 10;"
                    required
                ><?php echo e($sqlQuery); ?></textarea>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-primary">Run query</button>
                    <button type="button" class="btn btn-secondary" id="clearSqlBtn">Clear</button>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">Presets:</span>
                    <?php foreach ($sampleQueries as $label => $queryText): ?>
                        <button
                            type="button"
                            class="table-action-link border-0 bg-transparent p-0 sample-query-btn"
                            style="margin-right: 8px; font-size: 12px;"
                            data-query="<?php echo e($queryText); ?>"
                        ><?php echo e($label); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Error Output -->
<?php if ($errorMsg !== ''): ?>
    <div class="alert alert-danger mb-4" role="alert">
        <div>
            <strong>SQL Error:</strong> <span class="font-monospace"><?php echo e($errorMsg); ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Write Query Success Output -->
<?php if ($queryType === 'WRITE' && $errorMsg === ''): ?>
    <div class="alert alert-success mb-4" role="alert">
        <div>
            Query executed. Affected rows: <strong class="tabular-nums"><?php echo (int)$affectedRows; ?></strong>
            <?php if ($execTimeMs !== null): ?>
                <span class="text-muted ms-2 tabular-nums">(<?php echo e((string)$execTimeMs); ?> ms)</span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Read Query Results -->
<?php if ($queryType === 'SELECT' && $errorMsg === ''): ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex align-items-center gap-3">
                <span>Query results</span>
                <span class="text-muted small fw-normal tabular-nums">
                    <?php echo $rowCount; ?> <?php echo $rowCount === 1 ? 'row' : 'rows'; ?> returned
                    <?php if ($execTimeMs !== null): ?>
                        &middot; <?php echo e((string)$execTimeMs); ?> ms
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($rowCount > 0): ?>
                <button type="button" class="btn btn-secondary btn-sm" id="exportResultsCsvBtn">Export CSV</button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table align-middle" id="sqlResultsTable">
                <?php if (!empty($columns)): ?>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <th><?php echo e($col); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                <?php endif; ?>
                <tbody>
                    <?php if ($rowCount === 0): ?>
                        <tr>
                            <td colspan="<?php echo max(1, count($columns)); ?>" class="text-center py-4 text-muted">
                                Query returned 0 rows.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <td>
                                        <?php if ($row[$col] === null): ?>
                                            <span class="text-muted fst-italic">NULL</span>
                                        <?php else: ?>
                                            <?php echo e((string)$row[$col]); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Schema Reference -->
<div class="card">
    <div class="card-body py-3 px-4">
        <details>
            <summary class="fw-medium text-dark" style="cursor: pointer; font-size: 13px;">Database schema reference (6 tables)</summary>
            <div class="mt-3 row g-3 font-monospace" style="font-size: 12px;">
                <div class="col-md-4">
                    <div class="fw-semibold text-dark mb-1">Patient</div>
                    <div class="text-secondary">patient_id, name, age, gender, phone</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold text-dark mb-1">Doctor</div>
                    <div class="text-secondary">doctor_id, name, specialization, phone</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold text-dark mb-1">Medicine</div>
                    <div class="text-secondary">medicine_id, medicine_name, type, price</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold text-dark mb-1">Appointment</div>
                    <div class="text-secondary">appointment_id, patient_id, doctor_id, appointment_date, status</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold text-dark mb-1">Prescription</div>
                    <div class="text-secondary">prescription_id, appointment_id, medicine_id, dosage, duration_days</div>
                </div>
                <div class="col-md-4">
                    <div class="fw-semibold text-dark mb-1">Users</div>
                    <div class="text-secondary">user_id, username, password_hash, role, patient_id, doctor_id</div>
                </div>
            </div>
        </details>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('sql_query');
    const clearBtn = document.getElementById('clearSqlBtn');
    const presetBtns = document.querySelectorAll('.sample-query-btn');
    const exportBtn = document.getElementById('exportResultsCsvBtn');

    presetBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            textarea.value = this.getAttribute('data-query') || '';
            textarea.focus();
        });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            textarea.value = '';
            textarea.focus();
        });
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            const table = document.getElementById('sqlResultsTable');
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tr'));
            const csv = rows.map(function (row) {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(function (cell) {
                    const text = cell.innerText.replace(/"/g, '""');
                    return '"' + text + '"';
                }).join(',');
            }).join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', 'sql_query_results.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    }
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>