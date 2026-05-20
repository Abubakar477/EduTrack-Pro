<?php
// ============================================================
//  Login Page
// ============================================================
session_start();

// Already logged in? Redirect.
if (!empty($_SESSION['user_id'])) {
    $loc = $_SESSION['role'] === 'teacher'
        ? '/student-dashboard/teacher/dashboard.php'
        : '/student-dashboard/student/dashboard.php';
    header("Location: $loc");
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password, role, roll_no FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['roll_no'] = $user['roll_no'];

            $redirect = $user['role'] === 'teacher'
                ? '/student-dashboard/teacher/dashboard.php'
                : '/student-dashboard/student/dashboard.php';
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}

$urlError = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — EduTrack Pro Student Dashboard</title>
  <meta name="description" content="Login to EduTrack Pro — Student Performance Dashboard">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/student-dashboard/assets/css/style.css">
  <style>body { display: block; }</style>
</head>
<body class="login-body">

<div class="login-card">
  <!-- Logo -->
  <div class="login-logo"><i class="bi bi-mortarboard-fill"></i></div>
  <h1 class="login-title">EduTrack Pro</h1>
  <p class="login-subtitle">Student Performance Dashboard</p>

  <!-- Error -->
  <?php if ($error): ?>
  <div class="alert alert-danger" style="background:rgba(239,68,68,.15);border-left-color:#ef4444;">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php elseif ($urlError === 'unauthorized'): ?>
  <div class="alert alert-warning">
    <i class="bi bi-shield-exclamation"></i> You don't have permission to access that page.
  </div>
  <?php endif; ?>

  <!-- Login Form -->
  <form method="POST">
    <label class="login-label" for="email"><i class="bi bi-envelope-fill"></i>&nbsp; Email Address</label>
    <input class="login-input" type="email" id="email" name="email"
           placeholder="teacher@school.com" required
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

    <label class="login-label" for="password"><i class="bi bi-lock-fill"></i>&nbsp; Password</label>
    <input class="login-input" type="password" id="password" name="password"
           placeholder="Enter your password" required>

    <button type="submit" class="login-btn">
      <i class="bi bi-box-arrow-in-right"></i>&nbsp; Sign In
    </button>
  </form>

  <!-- Demo Credentials -->
  <div class="login-demo-info">
    <p>🔐 Demo Accounts</p>
    <div class="demo-cred">
      <span>🧑‍🏫 Teacher</span>
      <code>teacher@school.com / teacher123</code>
    </div>
    <div class="demo-cred">
      <span>🧑‍🎓 Student</span>
      <code>ali@school.com / student123</code>
    </div>
    <div class="demo-cred" style="margin-bottom:0">
      <span>🧑‍🎓 Student</span>
      <code>sara@school.com / student123</code>
    </div>
  </div>


</div>

</body>
</html>
