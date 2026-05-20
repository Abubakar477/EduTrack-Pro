<?php
// ============================================================
//  Student — Overview Dashboard
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('student');

$db        = getDB();
$studentId = $_SESSION['user_id'];

// ─── Current Semester ─────────────────────────────────────────
$semester = $db->query("SELECT * FROM semesters WHERE is_current=1 LIMIT 1")->fetch();
$semId    = $semester['id'] ?? 0;

// ─── Grades for Current Semester ─────────────────────────────
$gradesStmt = $db->prepare("
    SELECT s.name AS subject, g.marks_obtained, g.total_marks,
           s.credit_hours
    FROM grades g JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id=? AND g.semester_id=?
    ORDER BY s.name
");
$gradesStmt->execute([$studentId, $semId]);
$currentGrades = $gradesStmt->fetchAll();

// ─── GPA for Current Semester ─────────────────────────────────
$semGpa     = 0;
$totalCrHrs = 0;
$gpaPoints  = 0;
foreach ($currentGrades as $g) {
    $gp         = gpaFromMarks($g['marks_obtained'], $g['total_marks']);
    $gpaPoints  += $gp * $g['credit_hours'];
    $totalCrHrs += $g['credit_hours'];
}
$semGpa = $totalCrHrs > 0 ? round($gpaPoints / $totalCrHrs, 2) : 0;

// ─── Overall GPA (all semesters using credit-weighted) ────────
$allGradesStmt = $db->prepare("
    SELECT g.marks_obtained, g.total_marks, s.credit_hours
    FROM grades g JOIN subjects s ON s.id=g.subject_id
    WHERE g.student_id=?
");
$allGradesStmt->execute([$studentId]);
$allGrades = $allGradesStmt->fetchAll();
$totalCrAll = 0; $gpaPointsAll = 0;
foreach ($allGrades as $g) {
    $gp           = gpaFromMarks($g['marks_obtained'], $g['total_marks']);
    $gpaPointsAll += $gp * $g['credit_hours'];
    $totalCrAll   += $g['credit_hours'];
}
$overallGpa = $totalCrAll > 0 ? round($gpaPointsAll / $totalCrAll, 2) : 0;

// ─── Attendance (current month) ──────────────────────────────
$attStmt = $db->prepare("
    SELECT status, COUNT(*) AS cnt FROM attendance
    WHERE student_id=? AND MONTH(date)=MONTH(NOW()) AND YEAR(date)=YEAR(NOW())
    GROUP BY status
");
$attStmt->execute([$studentId]);
$attData = ['present'=>0,'absent'=>0,'late'=>0,'holiday'=>0];
foreach ($attStmt->fetchAll() as $r) $attData[$r['status']] = (int)$r['cnt'];
$attTotal  = array_sum($attData);
$attRate   = $attTotal > 0 ? round(($attData['present'] / $attTotal) * 100) : 0;

// ─── Chart Data ───────────────────────────────────────────────
$chartLabels = array_column($currentGrades, 'subject');
$chartData   = array_map(fn($g) => round(($g['marks_obtained']/$g['total_marks'])*100, 1), $currentGrades);

// ─── GPA Trend ───────────────────────────────────────────────
// Compute GPA per semester in PHP (not MySQL) using gpaFromMarks()
$gpaPerSemStmt = $db->prepare("
    SELECT sem.name AS semester, sem.id,
           g.marks_obtained, g.total_marks, s.credit_hours
    FROM grades g
    JOIN semesters sem ON sem.id = g.semester_id
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ?
    ORDER BY sem.id
");
$gpaPerSemStmt->execute([$studentId]);
$gpaBySem = [];
foreach ($gpaPerSemStmt->fetchAll() as $r) {
    $key = $r['semester'];
    if (!isset($gpaBySem[$key])) $gpaBySem[$key] = ['points'=>0,'credits'=>0];
    $gp = gpaFromMarks($r['marks_obtained'], $r['total_marks']);
    $gpaBySem[$key]['points']  += $gp * $r['credit_hours'];
    $gpaBySem[$key]['credits'] += $r['credit_hours'];
}
$gpaLabels = array_keys($gpaBySem);
$gpaValues = array_map(fn($d) => $d['credits']>0 ? round($d['points']/$d['credits'],2) : 0, $gpaBySem);

// ─── Student Info ─────────────────────────────────────────────
$student = $db->prepare("SELECT * FROM users WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();

$pageTitle = 'My Dashboard';
$activeNav = 's_dashboard';
$topbarActions = '<a href="/student-dashboard/pdf/report.php" target="_blank" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</a>';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,var(--accent) 0%,#6d28d9 100%);border-radius:var(--radius-lg);padding:24px 28px;margin-bottom:24px;color:#fff;display:flex;align-items:center;justify-content:space-between;overflow:hidden;position:relative">
  <div style="position:absolute;top:-30px;right:-30px;width:200px;height:200px;background:rgba(255,255,255,.06);border-radius:50%"></div>
  <div style="position:absolute;bottom:-60px;right:80px;width:160px;height:160px;background:rgba(255,255,255,.04);border-radius:50%"></div>
  <div>
    <p style="margin:0 0 4px;opacity:.8;font-size:13px;font-weight:500">Welcome back,</p>
    <h2 style="margin:0 0 4px;font-size:26px;font-weight:800"><?= htmlspecialchars($student['name']) ?></h2>
    <p style="margin:0;opacity:.75;font-size:13px"><i class="bi bi-mortarboard-fill"></i>&nbsp; <?= htmlspecialchars($student['roll_no'] ?? 'Student') ?> &nbsp;|&nbsp; <?= htmlspecialchars($semester['name'] ?? 'Current Semester') ?></p>
  </div>
  <div style="text-align:right;position:relative;z-index:1">
    <div style="font-size:52px;font-weight:900;line-height:1;text-shadow:0 2px 20px rgba(0,0,0,.2)"><?= number_format($overallGpa,2) ?></div>
    <div style="opacity:.8;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em">Overall GPA</div>
  </div>
</div>

<!-- Stat Cards -->
<div class="grid-4 mb-4">
  <div class="stat-card accent">
    <div class="stat-icon accent"><i class="bi bi-star-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($semGpa,2) ?></div>
      <div class="stat-label">Semester GPA</div>
      <div class="stat-sub"><?= $semester['name'] ?? '—' ?></div>
    </div>
  </div>
  <div class="stat-card <?= $attRate>=80?'success':($attRate>=60?'warning':'danger') ?>">
    <div class="stat-icon <?= $attRate>=80?'success':($attRate>=60?'warning':'danger') ?>"><i class="bi bi-calendar-check-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $attRate ?>%</div>
      <div class="stat-label">Attendance</div>
      <div class="stat-sub">This month</div>
    </div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon info"><i class="bi bi-book-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= count($currentGrades) ?></div>
      <div class="stat-label">Subjects</div>
      <div class="stat-sub">Current semester</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon success"><i class="bi bi-graph-up-arrow"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= count($gpaBySem) ?></div>
      <div class="stat-label">Semesters</div>
      <div class="stat-sub">Data recorded</div>
    </div>
  </div>
</div>

<!-- Main Charts -->
<div class="grid-2 mb-4">

  <!-- Marks Bar Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-fill"></i> Marks — <?= htmlspecialchars($semester['name'] ?? '') ?></div>
      <a href="/student-dashboard/student/marks.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="card-body">
      <?php if ($currentGrades): ?>
      <div class="chart-container" style="height:240px">
        <canvas id="marksChart"></canvas>
      </div>
      <?php else: ?>
      <div class="text-center text-muted" style="padding:60px 20px">
        <i class="bi bi-clipboard2-x" style="font-size:36px;margin-bottom:10px;display:block"></i>
        No grades uploaded for this semester yet.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- GPA Trend -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-graph-up-arrow"></i> GPA Trend</div>
      <a href="/student-dashboard/student/gpa.php" class="btn btn-secondary btn-sm">Details</a>
    </div>
    <div class="card-body">
      <?php if (count($gpaLabels) > 0): ?>
      <div class="chart-container" style="height:240px">
        <canvas id="gpaChart"></canvas>
      </div>
      <?php else: ?>
      <div class="text-center text-muted" style="padding:60px 20px">No GPA data yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Attendance + Marks Table -->
<div class="grid-2 mb-4">

  <!-- Attendance Summary -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-pie-chart-fill"></i> Attendance Breakdown</div>
      <a href="/student-dashboard/student/attendance.php" class="btn btn-secondary btn-sm">Calendar</a>
    </div>
    <div class="card-body">
      <div style="display:flex;gap:24px;align-items:center">
        <div style="width:160px;height:160px;flex-shrink:0">
          <canvas id="attChart"></canvas>
        </div>
        <div style="flex:1">
          <?php
          $attBreakdown = [
            'present' => ['Present', 'success'],
            'absent'  => ['Absent',  'danger'],
            'late'    => ['Late',    'warning'],
          ];
          foreach ($attBreakdown as $k => [$label, $cls]):
            $cnt  = $attData[$k];
            $prop = $attTotal > 0 ? round(($cnt/$attTotal)*100) : 0;
          ?>
          <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
              <span style="font-size:13px;font-weight:600"><?= $label ?></span>
              <span style="font-size:13px;color:var(--text-muted)"><?= $cnt ?> days (<?= $prop ?>%)</span>
            </div>
            <div class="progress">
              <div class="progress-bar <?= $cls ?>" style="width:<?= $prop ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Current Semester Marks Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-table"></i> Subject Marks</div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Subject</th><th>Marks</th><th>%</th><th>Grade</th><th>GPA</th></tr>
        </thead>
        <tbody>
          <?php if ($currentGrades): foreach ($currentGrades as $g):
            $pct   = round(($g['marks_obtained']/$g['total_marks'])*100,1);
            $grade = gradeFromMarks($g['marks_obtained'],$g['total_marks']);
            $badge = gradeBadgeClass($grade);
            $gpa   = gpaFromMarks($g['marks_obtained'],$g['total_marks']);
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($g['subject']) ?></td>
            <td><?= $g['marks_obtained'] ?>/<?= (int)$g['total_marks'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <?= $pct ?>%
                <div class="progress" style="width:48px">
                  <div class="progress-bar <?= $pct>=75?'success':($pct>=50?'':'danger') ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            </td>
            <td><span class="badge <?= $badge ?>"><?= $grade ?></span></td>
            <td style="font-weight:700;color:<?= $gpa>=3?'var(--success)':($gpa>=2?'var(--warning)':'var(--danger)') ?>"><?= number_format($gpa,1) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:32px">No grades yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScripts = "<script>
createMarksBarChart('marksChart',
  " . json_encode(array_map(fn($l) => strlen($l)>10?substr($l,0,8).'…':$l, $chartLabels)) . ",
  " . json_encode($chartData) . "
);
createGPATrendChart('gpaChart',
  " . json_encode($gpaLabels) . ",
  " . json_encode(array_values($gpaValues)) . "
);
createAttendanceDoughnut('attChart',{$attData['present']},{$attData['absent']},{$attData['late']});
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>
