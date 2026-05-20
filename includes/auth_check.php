<?php
// ============================================================
//  Auth Guard — include at top of every protected page
//  Usage: require_once ROOT . '/includes/auth_check.php';
//  Or:    require_once ROOT . '/includes/auth_check.php'; requireAuth('teacher');
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /student-dashboard/auth/login.php');
    exit;
}
