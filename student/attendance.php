<?php
// ============================================================
//  Student — Attendance Calendar
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('student');

$db        = getDB();
$studentId = $_SESSION['user_id'];

// Month/Year selection
$selYear  = (int)($_GET['year']  ?? date('Y'));
$selMonth = (int)($_GET['month'] ?? date('m'));
if ($selMonth < 1)  { $selMonth = 12; $selYear--; }
if ($selMonth > 12) { $selMonth = 1;  $selYear++; }

// Fetch attendance for selected month
$attStmt = $db->prepare("
    SELECT date, status FROM attendance
    WHERE student_id=? AND YEAR(date)=? AND MONTH(date)=?
");
$attStmt->execute([$studentId, $selYear, $selMonth]);
$attMap = [];
foreach ($attStmt->fetchAll() as $row) {
    $attMap[$row['date']] = $row['status'];
}

// Calendar math
$firstDay    = (int)date('N', mktime(0,0,0,$selMonth,1,$selYear)); // 1=Mon..7=Sun
$daysInMonth = (int)date('t', mktime(0,0,0,$selMonth,1,$selYear));

// Overall stats for this month
$present = 0; $absent = 0; $late = 0; $holiday = 0;
foreach ($attMap as $s) {
    if ($s === 'present') $present++;
    elseif ($s === 'absent')  $absent++;
    elseif ($s === 'late')    $late++;
    elseif ($s === 'holiday') $holiday++;
}
$total   = $present + $absent + $late;
$attRate = $total > 0 ? round(($present / $total) * 100) : 0;

// Prev/next month links
$prevMonth = $selMonth - 1 < 1  ? 12 : $selMonth - 1;
$prevYear  = $selMonth - 1 < 1  ? $selYear - 1 : $selYear;
$nextMonth = $selMonth + 1 > 12 ? 1  : $selMonth + 1;
$nextYear  = $selMonth + 1 > 12 ? $selYear + 1 : $selYear;

$today = date('Y-m-d');
$dayLabels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

$pageTitle = 'Attendance Calendar';
$activeNav = 's_attendance';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats Row -->
<div class="grid-4 mb-4">
  <div class="stat-card success">
    <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $present ?></div>
      <div class="stat-label">Present</div>
    </div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $absent ?></div>
      <div class="stat-label">Absent</div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon warning"><i class="bi bi-clock-fill"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $late ?></div>
      <div class="stat-label">Late</div>
    </div>
  </div>
  <div class="stat-card <?= $attRate>=80?'success':($attRate>=60?'warning':'danger') ?>">
    <div class="stat-icon <?= $attRate>=80?'success':($attRate>=60?'warning':'danger') ?>"><i class="bi bi-percent"></i></div>
    <div class="stat-info">
      <div class="stat-value"><?= $attRate ?>%</div>
      <div class="stat-label">Attendance Rate</div>
      <div class="stat-sub"><?= $total ?> school days</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Calendar -->
  <div class="card">
    <div class="card-header">
      <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary btn-sm"><i class="bi bi-chevron-left"></i></a>
      <div class="card-title" style="margin:0">
        <i class="bi bi-calendar3"></i>
        <?= date('F Y', mktime(0,0,0,$selMonth,1,$selYear)) ?>
      </div>
      <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary btn-sm"><i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="card-body">

      <!-- Day labels -->
      <div class="cal-grid mb-2">
        <?php foreach ($dayLabels as $dl): ?>
        <div class="cal-day-label"><?= $dl ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Calendar cells -->
      <div class="cal-grid">
        <!-- Empty cells for month offset (Mon=1, correct for ISO Mon-start) -->
        <?php for ($e = 1; $e < $firstDay; $e++): ?>
        <div class="cal-cell empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
          $dateStr = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
          $dow     = (int)date('N', strtotime($dateStr)); // 6=Sat,7=Sun
          $status  = $attMap[$dateStr] ?? ($dow >= 6 ? 'holiday' : 'no-data');
          $isToday = $dateStr === $today;
        ?>
        <div class="cal-cell <?= $status ?> <?= $isToday ? 'today' : '' ?>"
             title="<?= $dateStr ?>: <?= ucfirst($status) ?>">
          <?= $d ?>
        </div>
        <?php endfor; ?>
      </div>

      <!-- Legend -->
      <div class="d-flex gap-2 flex-wrap mt-3" style="padding-top:12px;border-top:1px solid var(--border)">
        <?php foreach (['present'=>'success','absent'=>'danger','late'=>'warning','holiday'=>'accent','no-data'=>'neutral'] as $s=>$badge): ?>
        <span class="badge badge-<?= $badge ?>" style="font-size:11px">
          <?= ucfirst(str_replace('-',' ',$s)) ?>
        </span>
        <?php endforeach; ?>
        <span style="font-size:11px;color:var(--text-muted);margin-left:4px"><i class="bi bi-circle" style="color:var(--accent)"></i> = Today</span>
      </div>
    </div>
  </div>

  <!-- Progress over time (all months) -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-steps"></i> Monthly Attendance Rates</div>
    </div>
    <div class="card-body">
      <?php
      // Compute per-month attendance rate for this student
      $monthlyStmt = $db->prepare("
          SELECT YEAR(date) AS y, MONTH(date) AS m,
                 SUM(status='present') AS p, COUNT(*) AS t
          FROM attendance
          WHERE student_id=? AND status IN ('present','absent','late')
          GROUP BY y, m ORDER BY y, m
      ");
      $monthlyStmt->execute([$studentId]);
      $monthly = $monthlyStmt->fetchAll();
      ?>
      <?php if ($monthly): ?>
      <div class="chart-container" style="height:260px">
        <canvas id="monthlyAttChart"></canvas>
      </div>
      <?php else: ?>
      <div class="text-center text-muted" style="padding:60px">No attendance data recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// Build monthly chart data
$mLabels = [];
$mData   = [];
foreach ($monthly ?? [] as $r) {
    $mLabels[] = date('M Y', mktime(0,0,0,$r['m'],1,$r['y']));
    $mData[]   = $r['t'] > 0 ? round(($r['p']/$r['t'])*100,1) : 0;
}

$extraScripts = "<script>
" . (!empty($mLabels) ? "
new Chart(document.getElementById('monthlyAttChart'), {
  type: 'bar',
  data: {
    labels: " . json_encode($mLabels) . ",
    datasets: [{
      label: 'Attendance %',
      data: " . json_encode($mData) . ",
      backgroundColor: " . json_encode(array_map(fn($v)=>$v>=80?'rgba(34,197,94,.5)':($v>=60?'rgba(245,158,11,.5)':'rgba(239,68,68,.5)'),$mData)) . ",
      borderColor:     " . json_encode(array_map(fn($v)=>$v>=80?'#22c55e':($v>=60?'#f59e0b':'#ef4444'),$mData)) . ",
      borderWidth: 2, borderRadius: 8, borderSkipped: false,
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      y:{min:0,max:100,grid:{color:'#f1f5f9'},ticks:{callback:v=>v+'%'}},
      x:{grid:{display:false}}
    }
  }
});
" : "") . "
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>
