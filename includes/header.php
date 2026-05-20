<?php
// ============================================================
//  Shared HTML <head> + sidebar — included on every dashboard page
//  Expects: $pageTitle, $activeNav to be set before include
// ============================================================
$role     = $_SESSION['role']   ?? '';
$userName = $_SESSION['name']   ?? 'User';
$initials = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — EduTrack Pro</title>
  <meta name="description" content="Student Performance Dashboard — track marks, attendance & GPA trends.">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="/student-dashboard/assets/css/style.css">

  <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>

<!-- ─── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
    <div class="brand-text">
      <div class="brand-title">EduTrack Pro</div>
      <div class="brand-sub">Performance Dashboard</div>
    </div>
  </div>

  <!-- User info -->
  <div class="sidebar-user">
    <div class="user-avatar"><?= $initials ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($userName) ?></div>
      <div class="user-role"><i class="bi bi-circle-fill" style="font-size:7px;margin-right:4px;color:var(--success)"></i><?= $role ?></div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <?php if ($role === 'teacher'): ?>

      <div class="nav-section-label">Overview</div>
      <a href="/student-dashboard/teacher/dashboard.php"
         class="sidebar-link <?= ($activeNav??'')==='t_dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>

      <div class="nav-section-label">Manage</div>
      <a href="/student-dashboard/teacher/manage_students.php"
         class="sidebar-link <?= ($activeNav??'')==='t_students' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> Students
      </a>
      <a href="/student-dashboard/teacher/upload_grades.php"
         class="sidebar-link <?= ($activeNav??'')==='t_grades' ? 'active' : '' ?>">
        <i class="bi bi-clipboard2-data-fill"></i> Upload Grades
      </a>
      <a href="/student-dashboard/teacher/attendance.php"
         class="sidebar-link <?= ($activeNav??'')==='t_attendance' ? 'active' : '' ?>">
        <i class="bi bi-calendar-check-fill"></i> Attendance
      </a>

    <?php else: ?>

      <div class="nav-section-label">My Dashboard</div>
      <a href="/student-dashboard/student/dashboard.php"
         class="sidebar-link <?= ($activeNav??'')==='s_dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Overview
      </a>

      <div class="nav-section-label">Performance</div>
      <a href="/student-dashboard/student/marks.php"
         class="sidebar-link <?= ($activeNav??'')==='s_marks' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-fill"></i> My Marks
      </a>
      <a href="/student-dashboard/student/attendance.php"
         class="sidebar-link <?= ($activeNav??'')==='s_attendance' ? 'active' : '' ?>">
        <i class="bi bi-calendar3"></i> Attendance
      </a>
      <a href="/student-dashboard/student/gpa.php"
         class="sidebar-link <?= ($activeNav??'')==='s_gpa' ? 'active' : '' ?>">
        <i class="bi bi-graph-up-arrow"></i> GPA Trend
      </a>

      <div class="nav-section-label">Reports</div>
      <a href="/student-dashboard/pdf/report.php" target="_blank"
         class="sidebar-link">
        <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
      </a>

    <?php endif; ?>
  </nav>

  <!-- Logout -->
  <div class="sidebar-footer">
    <a href="/student-dashboard/auth/logout.php" class="sidebar-link">
      <i class="bi bi-box-arrow-left"></i> Logout
    </a>
  </div>
</aside>

<!-- ─── Main Wrapper ─────────────────────────────────────────── -->
<div class="main-wrapper">

  <!-- Topbar -->
  <header class="topbar">
    <div>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      <div class="topbar-subtitle"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="d-flex align-center gap-2">
      <?php if (!empty($topbarActions)) echo $topbarActions; ?>
      <div class="badge badge-accent">
        <i class="bi bi-person-fill"></i> <?= ucfirst($role) ?>
      </div>
    </div>
  </header>

  <!-- Flash messages -->
  <?php
  $flash = getFlash();
  if ($flash): ?>
  <div style="padding: 0 28px; padding-top: 16px;">
    <div class="alert alert-<?= $flash['type'] ?>">
      <i class="bi bi-<?= $flash['type']==='success' ? 'check-circle' : ($flash['type']==='danger' ? 'exclamation-triangle' : 'info-circle') ?>-fill"></i>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page content starts here — closed in footer.php -->
  <main class="page-content">
