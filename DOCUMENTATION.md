# MediCare Hub — Codebase & Database Documentation

**MediCare Hub** is an enterprise-grade Clinic Management System built with PHP 8 (PDO), MySQL/MariaDB, IBM Plex Sans, and vanilla JavaScript. This document provides a concise summary of every code file and the relational database architecture.

---

## 1. Database Architecture Summary

The relational database (`clinic_db`) consists of **6 normalized tables** with strict referential integrity (`ON DELETE CASCADE` foreign keys) and UTF-8 encoding.

```
       +---------------+             +--------------+
       |    Patient    |<----+       |    Doctor    |<----+
       +---------------+     |       +--------------+     |
       | patient_id PK |     |       | doctor_id PK |     |
       | name          |     |       | name         |     |
       | age, gender   |     |       | specializ... |     |
       | phone         |     |       | phone        |     |
       +---------------+     |       +--------------+     |
               |             |              |             |
               |             |              |             |
               v             |              v             |
       +---------------+     |       +--------------+     |
       |  Appointment  |     |       |    Users     |     |
       +---------------+     |       +--------------+     |
       | appointment_id|     +-------| patient_id FK|     |
       | patient_id FK |             | doctor_id  FK|-----+
       | doctor_id  FK |             | username     |
       | date, status  |             | password_hash|
       +---------------+             | role (enum)  |
               |                     +--------------+
               v
       +---------------+             +--------------+
       | Prescription  |             |   Medicine   |
       +---------------+             +--------------+
       | prescription_id             | medicine_id  |
       | appointment_FK|             | name, type   |
       | medicine_id FK|------------>| price        |
       | dosage        |             +--------------+
       | duration_days |
       +---------------+
```

### Table Breakdown

1. **`Patient`**:
   - `patient_id` (INT, Primary Key, Auto Increment): Unique patient identifier.
   - `name` (VARCHAR 100): Patient's full name.
   - `age` (INT): Patient age in years (0–150).
   - `gender` (ENUM: `'Male'`, `'Female'`, `'Other'`): Demographic gender.
   - `phone` (VARCHAR 20): 10-digit mobile contact number.

2. **`Doctor`**:
   - `doctor_id` (INT, Primary Key, Auto Increment): Unique doctor identifier.
   - `name` (VARCHAR 100): Doctor full name with title (e.g. *Dr. Rajesh Mehta*).
   - `specialization` (VARCHAR 100): Medical specialty (e.g. *Cardiology*, *Dermatology*, *Orthopedics*).
   - `phone` (VARCHAR 20): Direct contact line.

3. **`Medicine`**:
   - `medicine_id` (INT, Primary Key, Auto Increment): Inventory item identifier.
   - `medicine_name` (VARCHAR 100): Commercial/generic drug name with strength (e.g. *Amoxicillin 500mg*).
   - `type` (VARCHAR 50): Dosage form (*Tablet*, *Capsule*, *Syrup*, *Topical Gel*, *Drops*, *Inhaler*).
   - `price` (DECIMAL 8,2): Dispensing unit cost in INR (₹).

4. **`Appointment`**:
   - `appointment_id` (INT, Primary Key, Auto Increment): Unique consultation booking identifier.
   - `patient_id` (INT, Foreign Key &rarr; `Patient.patient_id`): Registered patient.
   - `doctor_id` (INT, Foreign Key &rarr; `Doctor.doctor_id`): Consulting physician.
   - `appointment_date` (DATE): Scheduled consultation date.
   - `status` (ENUM: `'Scheduled'`, `'Completed'`, `'Cancelled'`): Operational appointment state.

5. **`Prescription`**:
   - `prescription_id` (INT, Primary Key, Auto Increment): Itemized prescription line.
   - `appointment_id` (INT, Foreign Key &rarr; `Appointment.appointment_id`): Linked consultation.
   - `medicine_id` (INT, Foreign Key &rarr; `Medicine.medicine_id`): Prescribed medication.
   - `dosage` (VARCHAR 100): Clinical administration frequency (e.g. *500mg twice daily after meals*).
   - `duration_days` (INT): Treatment course duration in days.

6. **`Users`**:
   - `user_id` (INT, Primary Key, Auto Increment): Authentication identity identifier.
   - `username` (VARCHAR 50, UNIQUE): Login username.
   - `password_hash` (VARCHAR 255): Secure bcrypt password hash (`password_hash()`).
   - `role` (ENUM: `'admin'`, `'doctor'`, `'patient'`): Access governance role.
   - `patient_id` (INT, NULL, Foreign Key &rarr; `Patient.patient_id`): Populated if user is a patient.
   - `doctor_id` (INT, NULL, Foreign Key &rarr; `Doctor.doctor_id`): Populated if user is a doctor.

---

## 2. Code File Directory & Explanations

### Root Gateway & Authentication Files
| File | Short Description |
| :--- | :--- |
| `index.php` | Application entry point; evaluates active authentication and redirects users to their role portal or the login screen. |
| `login.php` | Single-card clinical sign-in portal; validates credentials with bcrypt, initializes sessions, and displays demo login hints. |
| `register.php` | Self-service patient onboarding form; creates both a demographic `Patient` row and an authentication `Users` record atomically. |
| `logout.php` | Teardown script; invalidates the session, deletes authentication cookies, and redirects safely to the login screen. |
| `database.sql` | Complete MySQL schema script with table DDLs, cascade constraints, and realistic pre-seeded Indian clinical data. |
| `README.md` | Deployment manual containing system specifications, setup instructions, and feature walkthrough. |

---

### Configuration & Core Framework (`config/` & `includes/`)
| File | Short Description |
| :--- | :--- |
| `config/db.php` | Establishes the singleton PDO database connection with UTF-8 encoding, persistent error modes, and custom port configuration (`3308`). |
| `includes/auth.php` | Security subsystem providing role-based route guardrails (`requireRole()`), CSRF token generation/verification, and flash alerts. |
| `includes/functions.php` | Central utility toolkit: XSS output escaping (`e()`), server-side pagination (`paginate()`), CSV streaming (`exportCSV()`), and date/currency formatters. |
| `includes/header.php` | Global layout header; loads IBM Plex Sans fonts, renders the 56px top bar with breadcrumbs and date, and provides cache-busting CSS links. |
| `includes/footer.php` | Global layout footer; injects Bootstrap scripts, loads `app.js`, and renders the universal record deletion confirmation modal. |
| `includes/sidebar_admin.php` | Admin 240px navigation drawer with section labels (`OVERVIEW`, `MANAGEMENT`, `REPORTS`), outline icons, and user session details. |
| `includes/sidebar_doctor.php` | Doctor-specific navigation sidebar providing direct access to the queue, appointment calendar, and patient cohort. |
| `includes/sidebar_patient.php` | Patient-facing sidebar linking to dashboard, booking stepper, appointment records, prescription archive, and profile. |

---

### Administration Module (`admin/`)
| File | Short Description |
| :--- | :--- |
| `admin/dashboard.php` | Executive admin dashboard featuring 4 stat cards, Chart.js appointments-by-doctor bar chart, and recent consultations table. |
| `admin/patients.php` | Patient directory supporting live search by name/phone, server pagination, and edit/delete record actions. |
| `admin/patient_form.php` | Demographic form for registering new patients or editing existing profiles with full field validation. |
| `admin/doctors.php` | Specialist roster displaying doctor names, medical departments, contact numbers, and consultation totals. |
| `admin/doctor_form.php` | Specialist editor form for adding and modifying doctor details and clinical specializations. |
| `admin/medicines.php` | Pharmacy catalog table showing available drugs, dosage forms, unit prices, and stock management actions. |
| `admin/medicine_form.php` | Form for adding or editing pharmaceutical products, formulation types, and unit dispensing prices. |
| `admin/appointments.php` | Master appointment schedule with patient/doctor search, status dropdown filters, and rescheduling tools. |
| `admin/appointment_form.php` | Appointment creation and editing form linking patients and physicians with date selection and status tracking. |
| `admin/reports.php` | SQL report generator running 5 relational queries (a–e) with tabbed switching, monospace SQL inspection, and CSV export. |
| `admin/export_csv.php` | Data streaming controller that executes the active report query and pipes an RFC 4180 CSV file to the browser. |

---

### Physician Module (`doctor/`)
| File | Short Description |
| :--- | :--- |
| `doctor/dashboard.php` | Doctor's primary workspace showing today's patient queue, 7-day upcoming bookings, and quick consultation completion. |
| `doctor/appointments.php` | Doctor's consultation log with patient filters, contact details, status management, and digital Rx generation. |
| `doctor/patients.php` | Assigned patient cohort table displaying all individuals who have booked visits with the logged-in doctor. |
| `doctor/prescription_form.php` | Interactive prescription builder supporting itemized drug selection, dosage/duration entry, and live cost calculation. |
| `doctor/prescription_print.php` | Monochrome clinical Rx slip formatted for A4 printing with clinic letterhead, itemized medications, and signature line. |

---

### Patient Module (`patient/`)
| File | Short Description |
| :--- | :--- |
| `patient/dashboard.php` | Patient home portal showing active bookings, consultation history, assigned doctor profiles, and booking actions. |
| `patient/book_appointment.php` | 3-step structured booking flow (select specialization &rarr; select doctor from table &rarr; select date with past-date checks). |
| `patient/get_doctors.php` | AJAX JSON endpoint returning active doctors filtered by the user's selected specialization. |
| `patient/my_appointments.php` | Patient's appointment history with cancellation controls for upcoming visits and direct links to prescription slips. |
| `patient/prescriptions.php` | Chronological prescription repository displaying prescribed medications, administration instructions, and slip links. |
| `patient/prescription_print.php` | Patient-accessible printable prescription slip identical to the doctor's official clinic format. |
| `patient/profile.php` | Self-service demographic profile editor for patients to maintain updated contact phone numbers and age. |

---

### Frontend Assets (`assets/`)
| File | Short Description |
| :--- | :--- |
| `assets/css/style.css` | Complete design system stylesheet; defines IBM Plex Sans, clinical maroon theme (`#7A1C28`), 4px radius, unboxed text statuses, red for delete/cancel (`#A12C2C`), green for completed/complete (`#2E6B4F`), and default accent for edit/view. |
| `assets/css/print.css` | Print-only stylesheet hiding navigation bars, toolbars, and buttons for clean black-and-white physical document printing. |
| `assets/js/app.js` | UI script handling mobile sidebar offcanvas toggling, global delete modal parameter binding, and alert dismissals. |
| `assets/js/booking.js` | Client-side AJAX script powering the 3-step patient appointment booking flow and physician table selection. |
| `assets/js/charts.js` | Flat Chart.js chart engine rendering the monochrome maroon specialist bar chart and consultation status breakdown. |
| `assets/img/logo.svg` | Clean monochrome SVG medical cross mark styled with the clinical maroon accent color. |
