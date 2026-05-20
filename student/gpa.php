<?php
// ============================================================
//  Student — GPA Trend
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('student');

$db        = getDB();
$studentId = $_SESSION['user_id'];

// ─── GPA per Semester ─────────────────────────────────────────
$raw = $db->prepare("
    SELECT sem.id, sem.name AS semester, sem.is_current,
           g.marks_obtained, g.total_marks, s.credit_hours, s.name AS subject
    FROM grades g
    JOIN semesters sem ON sem.id = g.semester_id
    JOIN subjects s    ON s.id   = g.subject_id
    WHERE g.student_id = ?
    ORDER BY sem.id, s.name
");
$raw->execute([$studentId]);
$rows = $raw->fetchAll();

// Build per-semester data
$semData = []; // semName => [gpa, credits, grades[], is_current]
foreach ($rows as $r) {
    $key = $r['semester'];
    if (!isset($semData[$key])) {
        $semData[$key] = ['points'=>0,'credits'=>0,'grades'=>[],'is_current'=>$r['is_current']];
    }
    $gp = gpaFromMarks($r['marks_obtained'], $r['total_marks']);
    $semData[$key]['points']  += $gp * $r['credit_hours'];
    $semData[$key]['credits'] += $r['credit_hours'];
    $semData[$key]['grades'][] = [
        'subject'   => $r['subject'],
        'marks'     => $r['marks_obtained'],
        'total'     => $r['total_marks'],
        'gpa'       => $gp,
        'credit_hrs'=> $r['credit_hours'],
        'grade'     => gradeFromMarks($r['marks_obtained'],$r['total_marks']),
    ];
}

// Compute GPA for each semester
$semGpas = [];
foreach ($semData as $sem => $d) {
    $semGpas[$sem] = $d['credits'] > 0 ? round($d['points']/$d['credits'],2) : 0;
}

$gpaLabels = array_keys($semGpas);
$gpaValues = array_values($semGpas);

// Overall CGPA
$totalPts = 0; $totalCr = 0;
foreach ($semData as $d) { $totalPts += $d['points']; $totalCr += $d['credits']; }
$cgpa = $totalCr > 0 ? round($totalPts/$totalCr,2) : 0;

// Highest / Lowest semester GPA
$maxGpa = $gpaValues ? max($gpaValues) : 0;
$minGpa = $gpaValues ? min($gpaValues) : 0;
$improvement = count($gpaValues)>=2 ? round(end($gpaValues)-$gpaValues[0],2) : 0;

$pageTitle = 'GPA Trend';
$activeNav = 's_gpa';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- CGPA Hero -->
<div style="background:linear-gradient(135deg,#0f172a,#1e1b4b);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;gap:32px;overflow:hidden;position:relative">
  <div style="position:absolute;top:-40px;right:-40px;width:220px;height:220px;background:rgba(99,102,241,.12);border-radius:50%"></div>
  <div style="text-align:center;min-width:140px;position:relative">
    <div class="gpa-big"><?= number_format($cgpa,2) ?></div>
    <div style="color:#94a3b8;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-top:6px">Cumulative GPA</div>
  </div>
  <div style="flex:1;display:grid;grid-template-columns:repeat(3,1fr);gap:20px;position:relative">
    <?php foreach ([['Best Semester GPA','⭐',number_format($maxGpa,2),'success'],
                   ['Lowest Semester GPA','📉',number_format($minGpa,2),'danger'],
                   ['Improvement','📈',($improvement>=0?'+':'').number_format($improvement,2),$improvement>=0?'success':'danger']] as [$label,$icon,$val,$clr]): ?>
    <div style="background:rgba(255,255,255,.05);border-radius:var(--radius);padding:16px;border:1px solid rgba(255,255,255,.06)">
      <div style="font-size:24px;margin-bottom:6px"><?= $icon ?></div>
      <div style="font-size:22px;font-weight:800;color:<?= $clr==='success'?'#4ade80':'#f87171' ?>"><?= $val ?></div>
      <div style="font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-top:2px"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid-2 mb-4">

  <!-- GPA Trend Chart -->
  <div class="card" style="">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-graph-up-arrow"></i> GPA Per Semester</div>
    </div>
    <div class="card-body">
      <?php if ($gpaLabels): ?>
      <div class="chart-container" style="height:300px">
        <canvas id="gpaTrendChart"></canvas>
      </div>
      <!-- GPA Zone Legend -->
      <div class="d-flex gap-2 flex-wrap mt-3" style="padding-top:12px;border-top:1px solid var(--border)">
        <span class="badge badge-success">≥3.0 — Excellent</span>
        <span class="badge badge-info">2.0–2.9 — Good</span>
        <span class="badge badge-warning">1.0–1.9 — Fair</span>
        <span class="badge badge-danger">&lt;1.0 — Poor</span>
      </div>
      <?php else: ?>
      <div class="text-center text-muted" style="padding:60px">No GPA data available.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Per-Semester Summary Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-table"></i> Semester Breakdown</div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Semester</th><th>Subjects</th><th>Avg %</th><th>GPA</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($semData as $semName => $d):
            $gpa    = $d['credits']>0 ? round($d['points']/$d['credits'],2) : 0;
            $avgPct = count($d['grades'])>0 ? round(array_sum(array_map(fn($g)=>($g['marks']/$g['total'])*100,$d['grades']))/count($d['grades']),1) : 0;
          ?>
          <tr>
            <td class="fw-600">
              <?= htmlspecialchars($semName) ?>
              <?php if ($d['is_current']): ?><span class="badge badge-accent" style="font-size:10px">Current</span><?php endif; ?>
            </td>
            <td><?= count($d['grades']) ?></td>
            <td><?= $avgPct ?>%</td>
            <td>
              <span style="font-size:18px;font-weight:800;color:<?= $gpa>=3?'var(--success)':($gpa>=2?'var(--info)':($gpa>=1?'var(--warning)':'var(--danger)')) ?>">
                <?= number_format($gpa,2) ?>
              </span>
            </td>
            <td>
              <?php if ($gpa>=3.5): ?>
                <span class="badge badge-success"><i class="bi bi-trophy-fill"></i> Dean's List</span>
              <?php elseif ($gpa>=3): ?>
                <span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Excellent</span>
              <?php elseif ($gpa>=2): ?>
                <span class="badge badge-info"><i class="bi bi-hand-thumbs-up-fill"></i> Good</span>
              <?php elseif ($gpa>=1): ?>
                <span class="badge badge-warning"><i class="bi bi-exclamation-triangle-fill"></i> Fair</span>
              <?php else: ?>
                <span class="badge badge-danger"><i class="bi bi-x-circle-fill"></i> At Risk</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Detailed Subject GPA Per Semester -->
<?php foreach ($semData as $semName => $d): ?>
<div class="card mb-3">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-book-fill"></i> <?= htmlspecialchars($semName) ?> — Subject Details</div>
    <span style="font-size:13px;font-weight:700;color:var(--accent)">
      GPA: <?= number_format($d['credits']>0?round($d['points']/$d['credits'],2):0, 2) ?>
    </span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Subject</th><th>Marks</th><th>%</th><th>Grade</th><th>Credit Hrs</th><th>GPA Points</th></tr>
      </thead>
      <tbody>
        <?php foreach ($d['grades'] as $g):
          $pct = round(($g['marks']/$g['total'])*100,1);
          $badge = gradeBadgeClass($g['grade']);
        ?>
        <tr>
          <td class="fw-600"><?= htmlspecialchars($g['subject']) ?></td>
          <td><?= $g['marks'] ?>/<?= (int)$g['total'] ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <?= $pct ?>%
              <div class="progress" style="width:60px">
                <div class="progress-bar <?= $pct>=75?'success':($pct>=50?'':'danger') ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          </td>
          <td><span class="badge <?= $badge ?>"><?= $g['grade'] ?></span></td>
          <td><?= $g['credit_hrs'] ?></td>
          <td style="font-weight:700;color:<?= $g['gpa']>=3?'var(--success)':($g['gpa']>=2?'var(--warning)':'var(--danger)') ?>">
            <?= number_format($g['gpa'],1) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php
$extraScripts = "<script>
createGPATrendChart('gpaTrendChart',
  " . json_encode($gpaLabels) . ",
  " . json_encode($gpaValues) . "
);
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>
