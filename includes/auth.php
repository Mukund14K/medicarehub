<?php
/**
 * auth.php - Session management, authentication, role guards, and CSRF protection
 * MedicareHub Clinic Management System
 */

// Secure session configuration and start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

/**
 * Get the application base path.
 */
function getBasePath(): string
{
    return '/medicarehub';
}

// ── Authentication helpers ───────────────────────────────────────────

/**
 * Check whether a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Require the visitor to be logged in; redirect to login page otherwise.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . getBasePath() . '/login.php');
        exit;
    }
}

/**
 * Require the logged-in user to hold a specific role.
 */
function requireRole(string $role): void
{
    if (!isLoggedIn() || $_SESSION['role'] !== $role) {
        setFlash('error', 'Access denied. You do not have the required permissions.');
        header('Location: ' . getBasePath() . '/login.php');
        exit;
    }
}

/**
 * Require the logged-in user to hold one of the given roles.
 */
function requireAnyRole(array $roles): void
{
    if (!isLoggedIn() || !in_array($_SESSION['role'], $roles, true)) {
        setFlash('error', 'Access denied. You do not have the required permissions.');
        header('Location: ' . getBasePath() . '/login.php');
        exit;
    }
}

// ── CSRF protection ──────────────────────────────────────────────────

/**
 * Generate a CSRF token, store it in the session if not set, and return it.
 */
function generateCSRFToken(bool $forceNew = false): string
{
    if ($forceNew || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the one stored in the session.
 */
function validateCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check the CSRF token submitted via POST. On failure, set a flash error
 * and redirect back to the referring page.
 */
function checkCSRF(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($token)) {
        setFlash('error', 'Invalid or expired form submission. Please try again.');

        $referer = $_SERVER['HTTP_REFERER'] ?? getBasePath() . '/login.php';
        header('Location: ' . $referer);
        exit;
    }
}

// ── Flash messages ───────────────────────────────────────────────────

/**
 * Store a flash message in the session.
 *
 * @param string $type    e.g. 'success', 'error', 'info', 'warning'
 * @param string $message The message text.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the current flash message, if any.
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return [
            'type'    => htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'),
            'message' => htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'),
        ];
    }
    return null;
}

// ── Login / session lifecycle ────────────────────────────────────────

/**
 * Authenticate a user against the Users table, set session variables on
 * success, and return true/false.
 */
function loginUser(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT * FROM Users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Prevent session fixation
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $_SESSION['user_id']  = (int) $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];

    // Resolve related IDs and display name based on role
    $_SESSION['patient_id'] = $user['patient_id'] ? (int) $user['patient_id'] : null;
    $_SESSION['doctor_id']  = $user['doctor_id'] ? (int) $user['doctor_id'] : null;
    $_SESSION['user_name']  = 'Administrator';

    if ($user['role'] === 'patient' && !empty($user['patient_id'])) {
        $stmt = $pdo->prepare('SELECT name FROM Patient WHERE patient_id = :pid LIMIT 1');
        $stmt->execute([':pid' => $user['patient_id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($patient) {
            $_SESSION['user_name'] = $patient['name'];
        }
    } elseif ($user['role'] === 'doctor' && !empty($user['doctor_id'])) {
        $stmt = $pdo->prepare('SELECT name FROM Doctor WHERE doctor_id = :did LIMIT 1');
        $stmt->execute([':did' => $user['doctor_id']]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($doctor) {
            $_SESSION['user_name'] = $doctor['name'];
        }
    }

    return true;
}

// ── Current-user accessors ───────────────────────────────────────────

function getCurrentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function getCurrentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function getCurrentPatientId(): ?int
{
    return $_SESSION['patient_id'] ?? null;
}

function getCurrentDoctorId(): ?int
{
    return $_SESSION['doctor_id'] ?? null;
}

function getCurrentUserName(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

// ── Role-based redirect ──────────────────────────────────────────────

/**
 * Redirect the current user to their role-specific dashboard.
 */
function redirectByRole(): void
{
    $base = getBasePath();

    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header('Location: ' . $base . '/admin.php');
            break;
        case 'doctor':
            header('Location: ' . $base . '/doctor.php');
            break;
        case 'patient':
            header('Location: ' . $base . '/patient.php');
            break;
        default:
            header('Location: ' . $base . '/login.php');
            break;
    }

    exit;
}
