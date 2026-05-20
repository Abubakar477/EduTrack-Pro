<?php
// Root redirect — send logged-in users to their dashboard,
// guests to the login page.
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'teacher') {
        header('Location: /student-dashboard/teacher/dashboard.php');
    } else {
        header('Location: /student-dashboard/student/dashboard.php');
    }
} else {
    header('Location: /student-dashboard/auth/login.php');
}
exit;
