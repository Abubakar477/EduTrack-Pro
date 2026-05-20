<?php
// ============================================================
//  Setup Script — Run once to initialize the database + seed data
//  Visit: http://localhost/student-dashboard/setup.php
// ============================================================
$messages = [];
$errors   = [];

// ─── Config ──────────────────────────────────────────────────
$dbHost = $_POST['db_host'] ?? 'localhost';
$dbUser = $_POST['db_user'] ?? 'root';
$dbPass = $_POST['db_pass'] ?? '';
$dbName = 'student_dashboard';

$runSetup = isset($_POST['run_setup']);

if ($runSetup) {
    // ─── Step 1: Connect to MySQL ─────────────────────────
    try {
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $messages[] = ['ok', 'Connected to MySQL successfully'];
    } catch (PDOException $e) {
        $errors[] = 'MySQL connection failed: ' . $e->getMessage();
        goto showPage;
    }

    // ─── Step 2: Create Database ──────────────────────────
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
        $messages[] = ['ok', "Database `$dbName` created / selected"];
    } catch (PDOException $e) {
        $errors[] = 'Database creation failed: ' . $e->getMessage();
        goto showPage;
    }

    // ─── Step 3: Create Tables ────────────────────────────
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    // Strip USE and CREATE DATABASE lines (already done above)
    $sql = preg_replace('/^(CREATE DATABASE|USE)\b[^;]+;/im', '', $sql);

    try {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if ($query) $pdo->exec($query);
        }
        $messages[] = ['ok', 'All database tables created'];
    } catch (PDOException $e) {
        $errors[] = 'Table creation failed: ' . $e->getMessage();
        goto showPage;
    }

    // ─── Step 4: Seed Data ────────────────────────────────
    try {
        // Clear existing data
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (['attendance','grades','users','subjects','semesters'] as $t) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Users
        $insertUser = $pdo->prepare(
            "INSERT INTO users (name, email, password, role, roll_no) VALUES (?,?,?,?,?)"
        );
        $users = [
            ['Mr. Ahmad Khan',  'teacher@school.com', password_hash('teacher123', PASSWORD_DEFAULT), 'teacher', null],
            ['Ali Hassan',      'ali@school.com',     password_hash('student123', PASSWORD_DEFAULT), 'student', 'CS-2021-01'],
            ['Sara Ahmed',      'sara@school.com',    password_hash('student123', PASSWORD_DEFAULT), 'student', 'CS-2021-02'],
            ['Bilal Malik',     'bilal@school.com',   password_hash('student123', PASSWORD_DEFAULT), 'student', 'CS-2021-03'],
        ];
        foreach ($users as $u) $insertUser->execute($u);

        // Semesters
        $insertSem = $pdo->prepare(
            "INSERT INTO semesters (name, start_date, end_date, is_current) VALUES (?,?,?,?)"
        );
        $semesters = [
            ['Fall 2022',   '2022-09-01', '2022-12-31', 0],
            ['Spring 2023', '2023-01-15', '2023-05-31', 0],
            ['Fall 2023',   '2023-09-01', '2023-12-31', 0],
            ['Spring 2024', '2024-01-15', '2024-05-31', 0],
            ['Fall 2024',   '2024-09-01', '2024-12-31', 1],
        ];
        foreach ($semesters as $s) $insertSem->execute($s);

        // Subjects
        $insertSubj = $pdo->prepare(
            "INSERT INTO subjects (name, code, credit_hours) VALUES (?,?,?)"
        );
        $subjects = [
            ['Mathematics',              'MATH-101', 4],
            ['Physics',                  'PHY-101',  3],
            ['Programming Fundamentals', 'CS-101',   3],
            ['English',                  'ENG-101',  2],
            ['Web Engineering',          'CS-301',   3],
            ['Database Systems',         'CS-302',   3],
        ];
        foreach ($subjects as $s) $insertSubj->execute($s);

        // Grades for Ali Hassan (student id will be 2)
        $insertGrade = $pdo->prepare(
            "INSERT INTO grades (student_id, subject_id, semester_id, marks_obtained, total_marks) VALUES (?,?,?,?,100)"
        );
        // Ali's grades across 5 semesters (6 subjects × 5 semesters)
        // Format: [student_id, subject_id, semester_id, marks]
        $grades = [
            // Fall 2022 (sem 1) — Ali
            [2,1,1,72],[2,2,1,65],[2,3,1,78],[2,4,1,88],[2,5,1,60],[2,6,1,70],
            // Spring 2023 (sem 2) — Ali
            [2,1,2,76],[2,2,2,70],[2,3,2,82],[2,4,2,91],[2,5,2,68],[2,6,2,74],
            // Fall 2023 (sem 3) — Ali
            [2,1,3,80],[2,2,3,75],[2,3,3,85],[2,4,3,90],[2,5,3,77],[2,6,3,79],
            // Spring 2024 (sem 4) — Ali
            [2,1,4,85],[2,2,4,78],[2,3,4,88],[2,4,4,95],[2,5,4,82],[2,6,4,84],
            // Fall 2024 (sem 5, current) — Ali
            [2,1,5,88],[2,2,5,80],[2,3,5,91],[2,4,5,93],[2,5,5,87],[2,6,5,85],
            // Sara's grades (student 3)
            [3,1,1,60],[3,2,1,72],[3,3,1,66],[3,4,1,80],[3,5,1,55],[3,6,1,63],
            [3,1,2,65],[3,2,2,74],[3,3,2,70],[3,4,2,85],[3,5,2,62],[3,6,2,68],
            [3,1,3,70],[3,2,3,76],[3,3,3,73],[3,4,3,88],[3,5,3,68],[3,6,3,72],
            [3,1,4,74],[3,2,4,79],[3,3,4,77],[3,4,4,91],[3,5,4,73],[3,6,4,76],
            [3,1,5,78],[3,2,5,82],[3,3,5,80],[3,4,5,94],[3,5,5,76],[3,6,5,79],
            // Bilal's grades (student 4)
            [4,1,1,55],[4,2,1,48],[4,3,1,62],[4,4,1,70],[4,5,1,45],[4,6,1,52],
            [4,1,2,60],[4,2,2,55],[4,3,2,67],[4,4,2,74],[4,5,2,52],[4,6,2,58],
            [4,1,3,66],[4,2,3,62],[4,3,3,71],[4,4,3,78],[4,5,3,59],[4,6,3,64],
            [4,1,4,72],[4,2,4,68],[4,3,4,75],[4,4,4,82],[4,5,4,65],[4,6,4,70],
            [4,1,5,76],[4,2,5,72],[4,3,5,79],[4,4,5,85],[4,5,5,70],[4,6,5,74],
        ];
        foreach ($grades as $g) $insertGrade->execute($g);

        // Attendance for current month (Fall 2024 sim)
        $insertAtt = $pdo->prepare(
            "INSERT INTO attendance (student_id, date, status) VALUES (?,?,?)"
        );
        $statuses = ['present','present','present','present','absent','present','present','late','present','present'];
        $students = [2, 3, 4];
        $year = 2024; $month = 10;
        $daysInMonth = 31;
        foreach ($students as $sid) {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $dow  = date('N', strtotime($date));
                if ($dow >= 6) continue; // skip weekends
                $status = $statuses[($sid + $d) % count($statuses)];
                $insertAtt->execute([$sid, $date, $status]);
            }
        }
        // Add some for Nov-Dec 2024
        for ($m = 11; $m <= 12; $m++) {
            $daysInM = ($m == 11) ? 30 : 31;
            foreach ($students as $sid) {
                for ($d = 1; $d <= $daysInM; $d++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $m, $d);
                    $dow  = date('N', strtotime($date));
                    if ($dow >= 6) continue;
                    $status = $statuses[($sid + $d + $m) % count($statuses)];
                    $insertAtt->execute([$sid, $date, $status]);
                }
            }
        }

        $messages[] = ['ok', 'Seed data inserted (3 students, 5 semesters, 6 subjects, grades + attendance)'];
    } catch (PDOException $e) {
        $errors[] = 'Seed data failed: ' . $e->getMessage();
        goto showPage;
    }

    // ─── Step 5: Download FPDF ────────────────────────────
    $fpdfDir  = __DIR__ . '/fpdf/';
    $fpdfFile = $fpdfDir . 'fpdf.php';
    if (!is_dir($fpdfDir)) mkdir($fpdfDir, 0755, true);

    if (!file_exists($fpdfFile)) {
        $fpdfUrl = 'https://raw.githubusercontent.com/Setasign/FPDF/master/fpdf.php';
        $content = @file_get_contents($fpdfUrl);
        if ($content && strlen($content) > 1000) {
            file_put_contents($fpdfFile, $content);
            $messages[] = ['ok', 'FPDF library downloaded successfully'];
        } else {
            $errors[] = 'Could not auto-download FPDF. Manually download fpdf.php from http://www.fpdf.org and place in /fpdf/';
        }
    } else {
        $messages[] = ['ok', 'FPDF library already present'];
    }

    // ─── Update config if different host/user ─────────────
    if ($dbHost !== 'localhost' || $dbUser !== 'root' || $dbPass !== '') {
        $configContent = file_get_contents(__DIR__ . '/config/db.php');
        $configContent = str_replace("'localhost'", "'$dbHost'", $configContent);
        $configContent = str_replace("'root'", "'$dbUser'", $configContent);
        $configContent = str_replace("''", "'$dbPass'", $configContent);
        file_put_contents(__DIR__ . '/config/db.php', $configContent);
        $messages[] = ['ok', 'config/db.php updated with your credentials'];
    }
}

showPage:
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Setup — EduTrack Pro</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/student-dashboard/assets/css/style.css">
  <style>body { display:block; background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%); }</style>
</head>
<body class="setup-body">
<div class="setup-card">
  <div class="setup-header">
    <h1><i class="bi bi-gear-fill"></i> EduTrack Pro Setup</h1>
    <p>Initialize your database, seed demo data, and download dependencies</p>
  </div>

  <div class="setup-body-inner">

    <?php if (!$runSetup): ?>
    <!-- Config Form -->
    <form method="POST">
      <div style="margin-bottom:20px">
        <label class="form-label" style="color:#94a3b8">MySQL Host</label>
        <input class="form-control" name="db_host" value="localhost" style="background:#1e293b;border-color:#334155;color:#e2e8f0;">
      </div>
      <div style="margin-bottom:20px">
        <label class="form-label" style="color:#94a3b8">MySQL Username</label>
        <input class="form-control" name="db_user" value="root" style="background:#1e293b;border-color:#334155;color:#e2e8f0;">
      </div>
      <div style="margin-bottom:24px">
        <label class="form-label" style="color:#94a3b8">MySQL Password <span style="opacity:.6">(leave blank for XAMPP default)</span></label>
        <input class="form-control" name="db_pass" type="password" placeholder="(blank for XAMPP)" style="background:#1e293b;border-color:#334155;color:#e2e8f0;">
      </div>
      <button type="submit" name="run_setup" class="btn btn-primary btn-lg btn-block">
        <i class="bi bi-play-fill"></i> Run Setup
      </button>
    </form>

    <?php else: ?>
    <!-- Results -->
    <?php foreach ($messages as [$type, $msg]): ?>
    <div class="setup-step">
      <div class="setup-step-icon ok"><i class="bi bi-check-lg"></i></div>
      <div style="color:#94a3b8;font-size:13.5px"><?= htmlspecialchars($msg) ?></div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
    <div class="setup-step">
      <div class="setup-step-icon error"><i class="bi bi-x-lg"></i></div>
      <div style="color:#f87171;font-size:13.5px"><?= htmlspecialchars($err) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (!$errors): ?>
    <div style="margin-top:24px;padding:20px;background:rgba(34,197,94,.1);border-radius:12px;border:1px solid rgba(34,197,94,.2);text-align:center;">
      <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:32px"></i>
      <p style="color:#22c55e;font-weight:700;margin-top:8px;font-size:15px">Setup Complete!</p>
      <a href="/student-dashboard/auth/login.php" class="btn btn-primary mt-2">
        <i class="bi bi-arrow-right"></i> Go to Login
      </a>
    </div>
    <?php else: ?>
    <a href="/student-dashboard/setup.php" class="btn btn-secondary mt-3 btn-block">
      <i class="bi bi-arrow-counterclockwise"></i> Try Again
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:24px;padding:14px;background:rgba(99,102,241,.08);border-radius:10px">
      <p style="color:#94a3b8;font-size:12px;margin:0"><i class="bi bi-info-circle"></i>
        <strong style="color:#a5b4fc">Demo Accounts:</strong>
        teacher@school.com / <strong>teacher123</strong> &nbsp;|&nbsp;
        ali@school.com / <strong>student123</strong>
      </p>
    </div>
  </div>
</div>
</body>
</html>
