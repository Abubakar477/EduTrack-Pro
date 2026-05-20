<?php
// ============================================================
//  Student Performance Dashboard — Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change if needed
define('DB_PASS', '');            // Change if needed
define('DB_NAME', 'student_dashboard');
define('BASE_URL', '/student-dashboard'); // URL path relative to htdocs

// ─── PDO Singleton ───────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die("<div style='font-family:monospace;padding:20px;background:#fee2e2;border-left:4px solid #ef4444;'>
                <strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                <br><small>Check config/db.php and make sure MySQL is running.</small></div>");
        }
    }
    return $pdo;
}

// ─── GPA Helpers ─────────────────────────────────────────────
function gpaFromMarks(float $marks, float $total = 100): float {
    $pct = ($marks / $total) * 100;
    if ($pct >= 85) return 4.0;
    if ($pct >= 80) return 3.7;
    if ($pct >= 75) return 3.3;
    if ($pct >= 70) return 3.0;
    if ($pct >= 65) return 2.7;
    if ($pct >= 60) return 2.3;
    if ($pct >= 55) return 2.0;
    if ($pct >= 50) return 1.7;
    if ($pct >= 40) return 1.0;
    return 0.0;
}

function gradeFromMarks(float $marks, float $total = 100): string {
    $pct = ($marks / $total) * 100;
    if ($pct >= 85) return 'A';
    if ($pct >= 80) return 'A-';
    if ($pct >= 75) return 'B+';
    if ($pct >= 70) return 'B';
    if ($pct >= 65) return 'B-';
    if ($pct >= 60) return 'C+';
    if ($pct >= 55) return 'C';
    if ($pct >= 50) return 'C-';
    if ($pct >= 40) return 'D';
    return 'F';
}

function gradeBadgeClass(string $grade): string {
    return match(true) {
        in_array($grade, ['A', 'A-'])        => 'badge-success',
        in_array($grade, ['B+', 'B', 'B-'])  => 'badge-info',
        in_array($grade, ['C+', 'C', 'C-'])  => 'badge-warning',
        $grade === 'D'                         => 'badge-orange',
        default                                => 'badge-danger',
    };
}

// ─── Auth Helpers ─────────────────────────────────────────────
function requireAuth(string $role = ''): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/auth/login.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? 0,
        'name' => $_SESSION['name']      ?? 'Guest',
        'role' => $_SESSION['role']      ?? '',
        'email'=> $_SESSION['email']     ?? '',
    ];
}

function isTeacher(): bool {
    return ($_SESSION['role'] ?? '') === 'teacher';
}

function isStudent(): bool {
    return ($_SESSION['role'] ?? '') === 'student';
}

// ─── Flash Messages ──────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
