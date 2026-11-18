<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

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
