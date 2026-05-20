<?php
// ============================================================
//  Teacher Dashboard
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('teacher');

$db = getDB();

// ─── Stats ───────────────────────────────────────────────────
$totalStudents = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalGrades   = $db->query("SELECT COUNT(*) FROM grades")->fetchColumn();

// Current semester
$semester = $db->query("SELECT * FROM semesters WHERE is_current=1 LIMIT 1")->fetch();
$semId    = $semester['id'] ?? 0;

// Class average this semester
$avgRow = $db->prepare("SELECT AVG((marks_obtained/total_marks)*100) AS avg FROM grades WHERE semester_id=?");
$avgRow->execute([$semId]);
$classAvg = round($avgRow->fetchColumn() ?? 0, 1);

// Attendance rate (current month)
$present = $db->query("SELECT COUNT(*) FROM attendance WHERE status='present' AND MONTH(date)=MONTH(NOW()) AND YEAR(date)=YEAR(NOW())")->fetchColumn();
$total   = $db->query("SELECT COUNT(*) FROM attendance WHERE MONTH(date)=MONTH(NOW()) AND YEAR(date)=YEAR(NOW())")->fetchColumn();
$attRate = $total > 0 ? round(($present / $total) * 100, 1) : 0;

// ─── Top Performers ──────────────────────────────────────────
$topPerformers = $db->prepare("
    SELECT u.name, u.roll_no,
           ROUND(AVG((g.marks_obtained/g.total_marks)*100),1) AS avg_pct,
           COUNT(g.id) AS subjects_graded
    FROM users u
    JOIN grades g ON g.student_id = u.id AND g.semester_id = ?
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY avg_pct DESC
    LIMIT 10
");
$topPerformers->execute([$semId]);
$performers = $topPerformers->fetchAll();

// ─── Subject Averages for Chart ───────────────────────────────
$subAvgStmt = $db->prepare("
    SELECT s.name, ROUND(AVG((g.marks_obtained/g.total_marks)*100),1) AS avg
    FROM grades g JOIN subjects s ON s.id = g.subject_id
    WHERE g.semester_id = ?
    GROUP BY s.id
");
$subAvgStmt->execute([$semId]);
$subAvgs = $subAvgStmt->fetchAll();

$subLabels = array_column($subAvgs, 'name');
$subData   = array_column($subAvgs, 'avg');

// ─── Recent Grade Uploads ─────────────────────────────────────
$recentGrades = $db->query("
    SELECT u.name AS student, s.name AS subject, sem.name AS semester,
           g.marks_obtained, g.total_marks, g.submitted_at
    FROM grades g
    JOIN users u    ON u.id    = g.student_id
    JOIN subjects s ON s.id    = g.subject_id
    JOIN semesters sem ON sem.id = g.semester_id
    ORDER BY g.submitted_at DESC LIMIT 8
")->fetchAll();

// ─── Page Render ─────────────────────────────────────────────
$pageTitle = 'Teacher Dashboard';
$activeNav = 't_dashboard';
$extraHead = '';
$topbarActions = '<a href="/student-dashboard/teacher/upload_grades.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Grades</a>';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat Cards -->
<div class="grid-4 mb-4">
  <div class="stat-card accent">
    <div class="stat-icon accent"><i class="bi bi-people-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $totalStudents ?></div>
      <div class="stat-label">Total Students</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon success"><i class="bi bi-clipboard2-check-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $totalGrades ?></div>
      <div class="stat-label">Grades Entered</div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon warning"><i class="bi bi-trophy-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $classAvg ?>%</div>
      <div class="stat-label">Class Average</div>
      <div class="stat-sub"><?= $semester['name'] ?? 'No semester' ?></div>
    </div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon info"><i class="bi bi-calendar-check-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $attRate ?>%</div>
      <div class="stat-label">Avg Attendance</div>
      <div class="stat-sub">This month</div>
    </div>
  </div>
</div>

<!-- Subject Averages Chart + Top Performers -->
<div class="grid-2 mb-4">

  <!-- Subject Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-fill"></i> Subject Averages — <?= htmlspecialchars($semester['name'] ?? 'Current Semester') ?></div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px">
        <canvas id="subjectAvgChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-lightning-fill"></i> Quick Actions</div>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <a href="/student-dashboard/teacher/manage_students.php" class="btn btn-secondary" style="flex-direction:column;padding:20px;text-align:center;gap:8px">
          <i class="bi bi-person-plus-fill" style="font-size:24px;color:var(--accent)"></i>
          <span>Add Student</span>
        </a>
        <a href="/student-dashboard/teacher/upload_grades.php" class="btn btn-secondary" style="flex-direction:column;padding:20px;text-align:center;gap:8px">
          <i class="bi bi-clipboard2-data-fill" style="font-size:24px;color:var(--success)"></i>
          <span>Upload Grades</span>
        </a>
        <a href="/student-dashboard/teacher/attendance.php" class="btn btn-secondary" style="flex-direction:column;padding:20px;text-align:center;gap:8px">
          <i class="bi bi-calendar-check-fill" style="font-size:24px;color:var(--warning)"></i>
          <span>Mark Attendance</span>
        </a>
        <a href="/student-dashboard/teacher/manage_students.php" class="btn btn-secondary" style="flex-direction:column;padding:20px;text-align:center;gap:8px">
          <i class="bi bi-people-fill" style="font-size:24px;color:var(--info)"></i>
          <span>View Students</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Student Performance Table -->
<div class="grid-2 mb-4">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-award-fill"></i> Student Rankings — <?= htmlspecialchars($semester['name'] ?? '') ?></div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Roll No</th>
            <th>Average</th>
            <th>Grade</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($performers): foreach ($performers as $i => $p):
            $grade   = gradeFromMarks($p['avg_pct'], 100);
            $badge   = gradeBadgeClass($grade);
          ?>
          <tr>
            <td>
              <?php if ($i === 0): ?>
                <span style="font-size:18px">🥇</span>
              <?php elseif ($i === 1): ?>
                <span style="font-size:18px">🥈</span>
              <?php elseif ($i === 2): ?>
                <span style="font-size:18px">🥉</span>
              <?php else: ?>
                <span class="text-muted"><?= $i+1 ?></span>
              <?php endif; ?>
            </td>
            <td class="fw-600"><?= htmlspecialchars($p['name']) ?></td>
            <td><code style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($p['roll_no'] ?? '—') ?></code></td>
            <td>
              <?= $p['avg_pct'] ?>%
              <div class="progress mt-1" style="width:80px">
                <div class="progress-bar <?= $p['avg_pct']>=75?'success':($p['avg_pct']>=60?'':'danger') ?>"
                     style="width:<?= $p['avg_pct'] ?>%"></div>
              </div>
            </td>
            <td><span class="badge <?= $badge ?>"><?= $grade ?></span></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:32px">No grades for this semester yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Uploads -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-clock-fill"></i> Recent Grade Uploads</div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Student</th><th>Subject</th><th>Marks</th><th>When</th></tr>
        </thead>
        <tbody>
          <?php if ($recentGrades): foreach ($recentGrades as $r):
            $pct   = round(($r['marks_obtained']/$r['total_marks'])*100, 1);
            $grade = gradeFromMarks($r['marks_obtained'], $r['total_marks']);
            $badge = gradeBadgeClass($grade);
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($r['student']) ?></td>
            <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($r['subject']) ?></td>
            <td>
              <span class="badge <?= $badge ?>"><?= $r['marks_obtained'] ?>/<?= (int)$r['total_marks'] ?></span>
            </td>
            <td style="font-size:12px;color:var(--text-muted)"><?= date('M j', strtotime($r['submitted_at'])) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:24px">No grades uploaded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScripts = "<script>
createMarksBarChart('subjectAvgChart',
  " . json_encode($subLabels) . ",
  " . json_encode($subData) . ",
  'Subject Averages'
);
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>
