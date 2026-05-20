<?php
// ============================================================
//  Student — My Marks (All Semesters)
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('student');

$db        = getDB();
$studentId = $_SESSION['user_id'];

// Semester filter
$semesters  = $db->query("SELECT * FROM semesters ORDER BY id")->fetchAll();
$currentSem = $db->query("SELECT * FROM semesters WHERE is_current=1 LIMIT 1")->fetch();
$selSemId   = (int)($_GET['semester'] ?? ($currentSem['id'] ?? 0));

// Grades for selected semester
$gradesStmt = $db->prepare("
    SELECT s.name AS subject, s.code, s.credit_hours,
           g.marks_obtained, g.total_marks
    FROM grades g JOIN subjects s ON s.id=g.subject_id
    WHERE g.student_id=? AND g.semester_id=?
    ORDER BY s.name
");
$gradesStmt->execute([$studentId, $selSemId]);
$grades = $gradesStmt->fetchAll();

// Compute GPA for selected semester
$semGpa=0; $semCr=0; $semGpaPts=0;
foreach ($grades as $g) {
    $gp = gpaFromMarks($g['marks_obtained'],$g['total_marks']);
    $semGpaPts += $gp * $g['credit_hours'];
    $semCr     += $g['credit_hours'];
}
$semGpa = $semCr > 0 ? round($semGpaPts/$semCr,2) : 0;

// Semester average
$semAvg = count($grades) > 0
    ? round(array_sum(array_map(fn($g)=>($g['marks_obtained']/$g['total_marks'])*100, $grades))/count($grades),1)
    : 0;

// Subject trend (all semesters for line chart)
$trendStmt = $db->prepare("
    SELECT sem.name AS semester, s.name AS subject,
           ROUND((g.marks_obtained/g.total_marks)*100,1) AS pct
    FROM grades g
    JOIN subjects s ON s.id=g.subject_id
    JOIN semesters sem ON sem.id=g.semester_id
    WHERE g.student_id=?
    ORDER BY sem.id, s.name
");
$trendStmt->execute([$studentId]);
$trendRaw = $trendStmt->fetchAll();

// Pivot: semesterName => [subject => pct]
$semNames = []; $subjectNames = []; $pivoted = [];
foreach ($trendRaw as $r) {
    $semNames[$r['semester']] = true;
    $subjectNames[$r['subject']] = true;
    $pivoted[$r['semester']][$r['subject']] = $r['pct'];
}
$semNames     = array_keys($semNames);
$subjectNames = array_keys($subjectNames);

// Build datasets for multi-line Chart.js
$trendDatasets = [];
foreach ($subjectNames as $sub) {
    $data = [];
    foreach ($semNames as $sem) {
        $data[] = $pivoted[$sem][$sub] ?? null;
    }
    $trendDatasets[] = ['label'=>$sub,'data'=>$data];
}

// For bar chart on selected semester
$chartLabels = array_map(fn($g) => strlen($g['subject'])>10?substr($g['subject'],0,8).'…':$g['subject'], $grades);
$chartData   = array_map(fn($g) => round(($g['marks_obtained']/$g['total_marks'])*100,1), $grades);

$selSemName = '';
foreach ($semesters as $s) { if ($s['id'] == $selSemId) { $selSemName = $s['name']; break; } }

$pageTitle = 'My Marks';
$activeNav = 's_marks';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Semester Filter -->
<div class="d-flex align-center gap-2 mb-4">
  <?php foreach ($semesters as $s): ?>
  <a href="?semester=<?= $s['id'] ?>"
     class="btn <?= $s['id']==$selSemId ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
    <?= htmlspecialchars($s['name']) ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Stats for selected Semester -->
<div class="grid-4 mb-4">
  <div class="stat-card accent">
    <div class="stat-icon accent"><i class="bi bi-star-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($semGpa,2) ?></div>
      <div class="stat-label">Semester GPA</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon success"><i class="bi bi-percent"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $semAvg ?>%</div>
      <div class="stat-label">Avg Score</div>
    </div>
  </div>
  <div class="stat-card info">
    <div class="stat-icon info"><i class="bi bi-book-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= count($grades) ?></div>
      <div class="stat-label">Subjects</div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon warning"><i class="bi bi-award-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $semCr ?></div>
      <div class="stat-label">Credit Hours</div>
    </div>
  </div>
</div>

<div class="grid-2 mb-4">

  <!-- Bar Chart -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-fill"></i> <?= htmlspecialchars($selSemName) ?> — Marks</div>
    </div>
    <div class="card-body">
      <?php if ($grades): ?>
      <div class="chart-container" style="height:260px">
        <canvas id="marksBarChart"></canvas>
      </div>
      <?php else: ?>
      <div class="text-center text-muted" style="padding:60px">No grades for this semester.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Grades Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-table"></i> Detailed Marks</div>
    </div>
    <div class="table-wrapper" style="max-height:360px;overflow-y:auto">
      <table>
        <thead>
          <tr><th>Subject</th><th>Code</th><th>Marks</th><th>Percent</th><th>Grade</th><th>GPA Pts</th></tr>
        </thead>
        <tbody>
          <?php if ($grades): foreach ($grades as $g):
            $pct   = round(($g['marks_obtained']/$g['total_marks'])*100,1);
            $grade = gradeFromMarks($g['marks_obtained'],$g['total_marks']);
            $badge = gradeBadgeClass($grade);
            $gpa   = gpaFromMarks($g['marks_obtained'],$g['total_marks']);
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($g['subject']) ?></td>
            <td><code style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($g['code']) ?></code></td>
            <td><?= $g['marks_obtained'] ?>/<?= (int)$g['total_marks'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <span style="min-width:40px"><?= $pct ?>%</span>
                <div class="progress flex-1" style="min-width:60px">
                  <div class="progress-bar <?= $pct>=75?'success':($pct>=50?'':'danger') ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            </td>
            <td><span class="badge <?= $badge ?>"><?= $grade ?></span></td>
            <td style="font-weight:700;color:<?= $gpa>=3?'var(--success)':($gpa>=2?'var(--warning)':'var(--danger)') ?>"><?= number_format($gpa,1) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:40px">No grades uploaded for this semester.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Subject Trend Line Chart (all semesters) -->
<?php if (count($semNames) > 1): ?>
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-graph-up"></i> Subject Performance Trend — All Semesters</div>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:300px">
      <canvas id="trendChart"></canvas>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$extraScripts = "<script>
createMarksBarChart('marksBarChart',
  " . json_encode(array_column($grades,'subject')) . ",
  " . json_encode($chartData) . "
);
" . (count($semNames)>1 ? "createSubjectTrendChart('trendChart',
  " . json_encode($semNames) . ",
  " . json_encode($trendDatasets) . "
);" : "") . "
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>
