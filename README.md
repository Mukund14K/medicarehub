# MediCare Hub — Clinic Management System

A healthcare management web application engineered with **PHP 8 (PDO)**, **MySQL/MariaDB**, **Bootstrap 5 (CDN)**, **Chart.js (CDN)**, and vanilla JavaScript. Designed for deployment on **XAMPP** (Apache + MySQL) from `C:\xampp\htdocs\medicarehub\` at `http://localhost/medicarehub/`.

---

## 1. System Overview & Key Features

### Role-Based Portals
- **Admin**:
  - Analytical Dashboard with 4 stat counters and dynamic flat **Chart.js** visualizations:
    - Appointments per Specialist (single-hue clinical bar chart).
    - Status breakdown (horizontal stacked bar chart in grey-blue shades).
  - Complete **CRUD** with live search, server-side pagination, and modal confirmation for **Patients**, **Doctors**, and **Pharmacy Catalog (Medicines)**.
  - Full **Appointments Management** (creation, rescheduling, status updates, deletion).
  - **SQL Reports Engine**: 5 exact parameterized SQL queries with collapsible query inspection (`View query`) and instant **CSV export**.
- **Doctor**:
  - Focused dashboard with today's queue and upcoming 7-day bookings.
  - One-click **Mark Completed** status handler.
  - Interactive **Digital Prescription Form**: add multiple medications, set dosages/durations, edit, remove, and calculate total medicine costs in real time.
  - Dedicated **Printable Prescription Slip** styled with clean monochrome clinic letterhead and print-specific CSS.
- **Patient**:
  - Personalized patient dashboard showing confirmed visits and consultation history.
  - Interactive **3-Step Appointment Booking**:
    1. Select Specialization.
    2. Select doctor from clean directory table.
    3. Choose Consultation Date with past-date prevention.
  - **Double-booking prevention**: blocks same doctor on same date and limits doctors to 10 patients per day.
  - Cancel scheduled appointments.
  - View full prescription history and open/print official slips.
  - Self-service profile management.

---

## 2. Setup & Installation (XAMPP)

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL / MariaDB) installed.
- Default credentials: User `root`, empty password (``).

### Step-by-Step Instructions

1. **Deploy Project Files**:
   Copy the entire `medicarehub` folder into your XAMPP web root directory:
   ```text
   C:\xampp\htdocs\medicarehub\
   ```

2. **Start Services**:
   Launch the **XAMPP Control Panel** and start both **Apache** and **MySQL**.

3. **Import Database**:
   - Open your browser and navigate to phpMyAdmin:
     ```text
     http://localhost/phpmyadmin/
     ```
   - Click the **Import** tab at the top.
   - Click **Choose File** and select:
     ```text
     C:\xampp\htdocs\medicarehub\database.sql
     ```
   - Click **Import** (or **Go**) at the bottom.
   - *Note: `database.sql` is safe to re-run anytime; it includes `DROP DATABASE IF EXISTS clinic_db; CREATE DATABASE clinic_db;`.*

4. **Launch Application**:
   Open your browser and navigate to:
   ```text
   http://localhost/medicarehub/
   ```

---

## 3. Demo Credentials

All accounts come pre-seeded with realistic records and hashed passwords (via PHP `password_hash` with bcrypt):

| Role | Username | Password | Linked Entity / Specialization |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | System Administrator |
| **Doctor** | `dr.mehta` | `doctor123` | Dr. Rajesh Mehta *(Cardiology)* |
| **Doctor** | `dr.sharma` | `doctor123` | Dr. Priya Sharma *(Dermatology)* |
| **Doctor** | `dr.patel` | `doctor123` | Dr. Amit Patel *(Orthopedics)* |
| **Patient** | `rahul` | `patient123` | Rahul Verma (Age 28, Male) |
| **Patient** | `sneha` | `patient123` | Sneha Kapoor (Age 34, Female) |
| **Patient** | `vikram` | `patient123` | Vikram Singh (Age 45, Male) |

*New patients can also self-register via the **Create Account** link on the login page.*

---

## 4. Relational Database Schema

### Tables
1. **`Patient`**:
   `patient_id` (PK AI), `name`, `age`, `gender` (ENUM: 'Male','Female','Other'), `phone`.
2. **`Doctor`**:
   `doctor_id` (PK AI), `name`, `specialization`, `phone`.
3. **`Medicine`**:
   `medicine_id` (PK AI), `medicine_name`, `type`, `price` (DECIMAL 8,2).
4. **`Appointment`**:
   `appointment_id` (PK AI), `patient_id` (FK &rarr; Patient ON DELETE CASCADE), `doctor_id` (FK &rarr; Doctor ON DELETE CASCADE), `appointment_date` (DATE), `status` (ENUM: 'Scheduled','Completed','Cancelled' DEFAULT 'Scheduled').
5. **`Prescription`**:
   `prescription_id` (PK AI), `appointment_id` (FK &rarr; Appointment ON DELETE CASCADE), `medicine_id` (FK &rarr; Medicine ON DELETE CASCADE), `dosage`, `duration_days`.
6. **`Users`**:
   `user_id` (PK AI), `username` (VARCHAR 50 UNIQUE), `password_hash`, `role` (ENUM: 'admin','doctor','patient'), `patient_id` (FK NULL), `doctor_id` (FK NULL).

---

## 5. SQL Reports Engine

The Admin Reports page (`/admin/reports.php`) executes and displays the following 5 relational queries:

1. **Patient-Doctor Consultations (`JOIN`)**:
   ```sql
   SELECT p.name AS patient_name, p.phone AS patient_phone, d.name AS doctor_name, d.specialization, a.appointment_date, a.status
   FROM Appointment a
   JOIN Patient p ON a.patient_id = p.patient_id
   JOIN Doctor d ON a.doctor_id = d.doctor_id
   ORDER BY a.appointment_date DESC;
   ```

2. **Patients by Doctor Specialization (`JOIN` + Filter)**:
   ```sql
   SELECT DISTINCT p.name AS patient_name, p.age, p.gender, p.phone, d.name AS doctor_name, d.specialization
   FROM Appointment a
   JOIN Patient p ON a.patient_id = p.patient_id
   JOIN Doctor d ON a.doctor_id = d.doctor_id
   WHERE d.specialization = ?
   ORDER BY p.name ASC;
   ```

3. **Appointments Between Chosen Dates (`BETWEEN`)**:
   ```sql
   SELECT a.appointment_id, p.name AS patient_name, d.name AS doctor_name, d.specialization, a.appointment_date, a.status
   FROM Appointment a
   JOIN Patient p ON a.patient_id = p.patient_id
   JOIN Doctor d ON a.doctor_id = d.doctor_id
   WHERE a.appointment_date BETWEEN ? AND ?
   ORDER BY a.appointment_date ASC;
   ```

4. **Appointments Volume per Doctor (`GROUP BY` + `COUNT`)**:
   ```sql
   SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count
   FROM Appointment a
   JOIN Doctor d ON a.doctor_id = d.doctor_id
   GROUP BY a.doctor_id, d.name, d.specialization
   ORDER BY appointment_count DESC;
   ```

5. **Doctors Having More than N Appointments (`HAVING`)**:
   ```sql
   SELECT d.name AS doctor_name, d.specialization, COUNT(*) AS appointment_count
   FROM Appointment a
   JOIN Doctor d ON a.doctor_id = d.doctor_id
   GROUP BY a.doctor_id, d.name, d.specialization
   HAVING COUNT(*) > ?
   ORDER BY appointment_count DESC;
   ```

---

## 6. Directory Layout

```text
medicarehub/
├── index.php                  # Landing gateway & role-based routing
├── login.php                  # Single-card clinical authentication & demo credentials
├── register.php               # Patient registration portal
├── logout.php                 # Session invalidation & teardown
├── database.sql               # Complete schema and seed datasets
├── README.md                  # System manual and deployment guide
│
├── config/
│   └── db.php                 # PDO database connector with UTF-8 & exceptions
│
├── includes/
│   ├── auth.php               # Role guardrails, session helpers, CSRF engine
│   ├── functions.php          # Pagination, CSV generator, sanitizer, badge helpers
│   ├── header.php             # HTML head, IBM Plex Sans, top navbar, factual alerts
│   ├── sidebar_admin.php      # Admin navigation menu (240px white background)
│   ├── sidebar_doctor.php     # Doctor navigation menu
│   ├── sidebar_patient.php    # Patient navigation menu
│   └── footer.php             # Closing tags, scripts, delete confirmation modal
│
├── admin/
│   ├── dashboard.php          # 4 stat cards, flat Chart.js bar charts
│   ├── patients.php           # Patient directory with search & pagination
│   ├── patient_form.php       # Add / Edit patient demographics
│   ├── doctors.php            # Specialist staff directory
│   ├── doctor_form.php        # Add / Edit specialist profiles
│   ├── medicines.php          # Pharmacy catalog & pricing
│   ├── medicine_form.php      # Add / Edit drug formulations
│   ├── appointments.php       # Clinic appointment master schedule
│   ├── appointment_form.php   # Appointment booking & status editor
│   ├── reports.php            # Tabbed interface for queries (a) through (e)
│   └── export_csv.php         # CSV export stream handler
│
├── doctor/
│   ├── dashboard.php          # Today's queue & quick completion handler
│   ├── appointments.php       # Doctor's scheduled appointments
│   ├── patients.php           # Assigned patient cohort history
│   ├── prescription_form.php  # Itemized digital prescription builder
│   └── prescription_print.php # Monochrome clinical printable Rx slip
│
├── patient/
│   ├── dashboard.php          # Patient overview & scheduled visits
│   ├── book_appointment.php   # 3-step structured booking flow
│   ├── my_appointments.php    # Active bookings & cancellation handler
│   ├── prescriptions.php      # Prescription archive by consultation
│   ├── prescription_print.php # Monochrome printable prescription copy
│   ├── profile.php            # Patient demographics editor
│   └── get_doctors.php        # AJAX JSON endpoint for specialty filtering
│
└── assets/
    ├── css/
    │   ├── style.css          # Enterprise clinical design system (IBM Plex Sans, CSS variables)
    │   └── print.css          # Monochrome print styles for prescription slips
    ├── js/
    │   ├── app.js             # Sidebar toggle, delete modal binder, toast auto-dismiss
    │   ├── charts.js          # Flat Chart.js configuration for admin dashboard
    │   └── booking.js         # Table-based doctor selection & booking flow
    └── img/
        └── logo.svg           # Clean monochrome clinical cross mark
```

---

## 7. Security & Engineering Standards
- **Zero Third-Party Package Dependencies**: Runs without Composer or npm.
- **SQL Injection Prevention**: 100% of queries use PDO prepared statements with parameter binding.
- **Cross-Site Scripting (XSS) Mitigation**: All dynamic output wrapped in `htmlspecialchars()` via helper `e()`.
- **Cross-Site Request Forgery (CSRF) Protection**: Cryptographically secure tokens checked on all POST forms.
- **Authentication**: Passwords hashed using bcrypt via `password_hash()` and verified via `password_verify()`.
- **Session Fixation Prevention**: `session_regenerate_id(true)` executed upon login.
- **Strict Role-Based Access Control (RBAC)**: Every endpoint validates user role and redirects unauthorized visitors.
