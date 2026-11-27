-- ============================================================
-- MediCare Hub - Clinic Management System
-- Database Schema and Seed Data
-- ============================================================

DROP DATABASE IF EXISTS clinic_db;
CREATE DATABASE clinic_db;
USE clinic_db;

-- ============================================================
-- 1. Patient Table
-- ============================================================
CREATE TABLE Patient (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    phone VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. Doctor Table
-- ============================================================
CREATE TABLE Doctor (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. Medicine Table
-- ============================================================
CREATE TABLE Medicine (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price DECIMAL(8,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. Appointment Table
-- ============================================================
CREATE TABLE Appointment (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    status ENUM('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES Doctor(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. Prescription Table
-- ============================================================
CREATE TABLE Prescription (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    duration_days INT NOT NULL,
    FOREIGN KEY (appointment_id) REFERENCES Appointment(appointment_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES Medicine(medicine_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. Users Table
-- ============================================================
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','doctor','patient') NOT NULL,
    patient_id INT NULL,
    doctor_id INT NULL,
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES Doctor(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed Data: Doctors (3)
-- ============================================================
INSERT INTO Doctor (name, specialization, phone) VALUES
('Dr. Rajesh Mehta', 'Cardiology', '9876543210'),
('Dr. Priya Sharma', 'Dermatology', '9876543211'),
('Dr. Amit Patel', 'Orthopedics', '9876543212');

-- ============================================================
-- Seed Data: Patients (8)
-- ============================================================
INSERT INTO Patient (name, age, gender, phone) VALUES
('Rahul Verma', 28, 'Male', '9123456780'),
('Sneha Kapoor', 34, 'Female', '9123456781'),
('Vikram Singh', 45, 'Male', '9123456782'),
('Anjali Desai', 29, 'Female', '9123456783'),
('Karan Malhotra', 52, 'Male', '9123456784'),
('Pooja Nair', 38, 'Female', '9123456785'),
('Arjun Reddy', 22, 'Male', '9123456786'),
('Meera Joshi', 65, 'Female', '9123456787');

-- ============================================================
-- Seed Data: Medicines (10)
-- ============================================================
INSERT INTO Medicine (medicine_name, type, price) VALUES
('Aspirin 75mg', 'Tablet', 15.00),
('Amoxicillin 500mg', 'Capsule', 45.00),
('Ibuprofen 400mg', 'Tablet', 20.00),
('Cetirizine 10mg', 'Tablet', 12.00),
('Metformin 500mg', 'Tablet', 35.00),
('Omeprazole 20mg', 'Capsule', 50.00),
('Diclofenac Gel 1%', 'Topical', 85.00),
('Calcium Carbonate 500mg', 'Tablet', 60.00),
('Vitamin D3 60000 IU', 'Capsule', 40.00),
('Paracetamol 650mg', 'Tablet', 10.00);

-- ============================================================
-- Seed Data: Appointments (28 total)
-- Dr. Rajesh Mehta (doctor_id=1) has 12 appointments
-- Dr. Priya Sharma (doctor_id=2) has 9 appointments
-- Dr. Amit Patel  (doctor_id=3) has 7 appointments
-- ============================================================
INSERT INTO Appointment (patient_id, doctor_id, appointment_date, status) VALUES
-- Dr. Rajesh Mehta (doctor_id=1) — 12 appointments
(1, 1, '2026-11-02', 'Completed'),    -- 1
(2, 1, '2026-11-05', 'Completed'),    -- 2
(3, 1, '2026-11-08', 'Completed'),    -- 3
(4, 1, '2026-11-10', 'Completed'),    -- 4
(5, 1, '2026-11-14', 'Completed'),    -- 5
(6, 1, '2026-11-18', 'Scheduled'),    -- 6
(7, 1, '2026-11-22', 'Scheduled'),    -- 7
(8, 1, '2026-11-25', 'Cancelled'),    -- 8
(1, 1, '2026-12-01', 'Scheduled'),    -- 9
(3, 1, '2026-12-05', 'Scheduled'),    -- 10
(5, 1, '2026-12-10', 'Scheduled'),    -- 11
(2, 1, '2026-12-15', 'Scheduled'),    -- 12

-- Dr. Priya Sharma (doctor_id=2) — 9 appointments
(1, 2, '2026-11-03', 'Completed'),    -- 13
(4, 2, '2026-11-07', 'Completed'),    -- 14
(6, 2, '2026-11-12', 'Completed'),    -- 15
(7, 2, '2026-11-16', 'Cancelled'),    -- 16
(2, 2, '2026-11-20', 'Scheduled'),    -- 17
(8, 2, '2026-11-28', 'Scheduled'),    -- 18
(3, 2, '2026-12-03', 'Scheduled'),    -- 19
(5, 2, '2026-12-08', 'Scheduled'),    -- 20
(4, 2, '2026-12-18', 'Scheduled'),    -- 21

-- Dr. Amit Patel (doctor_id=3) — 7 appointments
(3, 3, '2026-11-04', 'Completed'),    -- 22
(7, 3, '2026-11-09', 'Completed'),    -- 23
(5, 3, '2026-11-15', 'Completed'),    -- 24
(1, 3, '2026-11-21', 'Cancelled'),    -- 25
(8, 3, '2026-11-27', 'Scheduled'),    -- 26
(6, 3, '2026-12-06', 'Scheduled'),    -- 27
(2, 3, '2026-12-12', 'Scheduled');    -- 28

-- ============================================================
-- Seed Data: Prescriptions (18)
-- Realistic dosages and durations
-- Some appointments have multiple prescriptions
-- ============================================================
INSERT INTO Prescription (appointment_id, medicine_id, dosage, duration_days) VALUES
-- Appointment 1 (Rahul w/ Dr. Mehta, Completed) — 2 prescriptions
(1, 1, '75mg once daily', 14),
(1, 5, '500mg twice daily', 10),

-- Appointment 2 (Sneha w/ Dr. Mehta, Completed)
(2, 6, '20mg once daily before breakfast', 7),

-- Appointment 3 (Vikram w/ Dr. Mehta, Completed) — 2 prescriptions
(3, 1, '150mg once daily', 10),
(3, 10, '500mg as needed for pain', 5),

-- Appointment 4 (Anjali w/ Dr. Mehta, Completed)
(4, 9, '60000 IU once weekly', 14),

-- Appointment 5 (Karan w/ Dr. Mehta, Completed) — 2 prescriptions
(5, 5, '500mg twice daily after meals', 30),
(5, 8, '500mg once daily', 30),

-- Appointment 13 (Rahul w/ Dr. Sharma, Completed)
(13, 4, '10mg once daily at bedtime', 7),

-- Appointment 14 (Anjali w/ Dr. Sharma, Completed) — 2 prescriptions
(14, 7, 'Apply thin layer twice daily', 10),
(14, 4, '10mg once daily', 5),

-- Appointment 15 (Pooja w/ Dr. Sharma, Completed)
(15, 2, '500mg three times daily', 7),

-- Appointment 22 (Vikram w/ Dr. Patel, Completed) — 2 prescriptions
(22, 3, '400mg three times daily after meals', 7),
(22, 8, '500mg once daily', 14),

-- Appointment 23 (Arjun w/ Dr. Patel, Completed)
(23, 3, '200mg twice daily', 5),

-- Appointment 24 (Karan w/ Dr. Patel, Completed) — 2 prescriptions
(24, 7, 'Apply generously on affected area twice daily', 14),
(24, 10, '650mg every 6 hours as needed', 5),

-- Appointment 6 (Pooja w/ Dr. Mehta, Scheduled)
(6, 1, '75mg once daily', 7);

-- ============================================================
-- Seed Data: Users (7 accounts)
-- Plain-text passwords for local demonstration
-- ============================================================
INSERT INTO Users (username, password_hash, role, patient_id, doctor_id) VALUES
('admin',     'admin123',   'admin',   NULL, NULL),
('dr.mehta',  'doctor123',  'doctor',  NULL, 1),
('dr.sharma', 'doctor123',  'doctor',  NULL, 2),
('dr.patel',  'doctor123',  'doctor',  NULL, 3),
('rahul',     'patient123', 'patient', 1,    NULL),
('sneha',     'patient123', 'patient', 2,    NULL),
('vikram',    'patient123', 'patient', 3,    NULL);

-- ============================================================
-- End of MediCare Hub Database Setup
-- ============================================================
