<?php
// ============================================================
//  Teacher — Mark Attendance
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('teacher');

$db = getDB();

// ─── Handle Save ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_att'])) {
    $date       = $_POST['att_date'] ?? '';
    $attendance = $_POST['att']      ?? [];

    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $attendance) {
        $stmt = $db->prepare("
            INSERT INTO attendance (student_id, date, status) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE status=VALUES(status)
        ");
        foreach ($attendance as $studentId => $status) {
            if (in_array($status, ['present','absent','late','holiday'])) {
                $stmt->execute([(int)$studentId, $date, $status]);
            }
        }
        setFlash('success', 'Attendance saved for ' . date('M j, Y', strtotime($date)) . '!');
        header('Location: /student-dashboard/teacher/attendance.php?date=' . urlencode($date));
        exit;
    }
}

// ─── Selected Date ────────────────────────────────────────────
$selDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate)) $selDate = date('Y-m-d');

// ─── Fetch Students ───────────────────────────────────────────
$students = $db->query("SELECT id, name, roll_no FROM users WHERE role='student' ORDER BY name")->fetchAll();

// ─── Existing Attendance for Selected Date ────────────────────
$existingStmt = $db->prepare("SELECT student_id, status FROM attendance WHERE date=?");
$existingStmt->execute([$selDate]);
$existing = [];
foreach ($existingStmt->fetchAll() as $row) {
    $existing[$row['student_id']] = $row['status'];
}

// ─── Monthly Summary ──────────────────────────────────────────
$year  = date('Y', strtotime($selDate));
$month = date('m', strtotime($selDate));
$monthSummary = $db->prepare("
    SELECT a.student_id, a.status, COUNT(*) AS cnt
    FROM attendance a
    WHERE YEAR(a.date)=? AND MONTH(a.date)=?
    GROUP BY a.student_id, a.status
");
$monthSummary->execute([$year, $month]);
$summary = [];
foreach ($monthSummary->fetchAll() as $r) {
    $summary[$r['student_id']][$r['status']] = $r['cnt'];
}

$pageTitle = 'Mark Attendance';
$activeNav = 't_attendance';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="grid-2 mb-4" style="grid-template-columns: 420px 1fr; gap:24px">

  <!-- Attendance Form -->
  <div class="card" style="height:fit-content">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-calendar-check-fill"></i> Mark Attendance</div>
    </div>
    <div class="card-body">
      <!-- Date Picker -->
      <form method="GET" class="mb-3">
        <label class="form-label">Select Date</label>
        <div class="d-flex gap-2 align-center">
          <input class="form-control" type="date" name="date" value="<?= $selDate ?>" max="<?= date('Y-m-d') ?>">
          <button type="submit" class="btn btn-secondary"><i class="bi bi-arrow-right"></i></button>
        </div>
      </form>

      <div style="padding:10px 14px;background:rgba(99,102,241,.08);border-radius:8px;margin-bottom:16px;font-size:13px;color:var(--text-secondary)">
        <i class="bi bi-calendar3" style="color:var(--accent)"></i>
        Marking attendance for: <strong style="color:var(--text-primary)"><?= date('l, F j, Y', strtotime($selDate)) ?></strong>
      </div>

      <!-- Attendance Form -->
      <form method="POST">
        <input type="hidden" name="att_date" value="<?= $selDate ?>">
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
          <?php foreach ($students as $s):
            $status = $existing[$s['id']] ?? 'present';
          ?>
          <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg-card2);border-radius:var(--radius);border:1px solid var(--border)">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0">
              <?= strtoupper(substr($s['name'],0,1)) ?>
            </div>
            <div style="flex:1">
              <div style="font-weight:600;font-size:13.5px"><?= htmlspecialchars($s['name']) ?></div>
              <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($s['roll_no'] ?? '') ?></div>
            </div>
            <div class="d-flex gap-2">
              <?php foreach (['present'=>['success','check-lg'],'absent'=>['danger','x-lg'],'late'=>['warning','clock-fill']] as $val=>[$clr,$icon]): ?>
              <label style="cursor:pointer">
                <input type="radio" name="att[<?= $s['id'] ?>]" value="<?= $val ?>" <?= $status===$val?'checked':'' ?> style="display:none" class="att-radio">
                <span class="badge badge-<?= $clr ?>" style="opacity:<?= $status===$val?'1':'0.3' ?>;transition:.15s;cursor:pointer;padding:5px 9px" onclick="this.parentElement.querySelector('input').checked=true;updateRadios()">
                  <i class="bi bi-<?= $icon ?>"></i>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="submit" name="save_att" class="btn btn-primary w-100">
          <i class="bi bi-check2-all"></i> Save Attendance
        </button>
      </form>
    </div>
  </div>

  <!-- Monthly Summary -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-bar-chart-fill"></i> Monthly Summary — <?= date('F Y', strtotime($selDate)) ?></div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Student</th><th>Present</th><th>Absent</th><th>Late</th><th>Total</th><th>Rate</th></tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s):
            $p = $summary[$s['id']]['present'] ?? 0;
            $a = $summary[$s['id']]['absent']  ?? 0;
            $l = $summary[$s['id']]['late']    ?? 0;
            $t = $p + $a + $l;
            $rate = $t > 0 ? round(($p / $t) * 100) : 0;
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($s['name']) ?></td>
            <td><span class="badge badge-success"><?= $p ?></span></td>
            <td><span class="badge badge-danger"><?= $a ?></span></td>
            <td><span class="badge badge-warning"><?= $l ?></span></td>
            <td><?= $t ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;min-width:100px">
                <span style="font-weight:700;font-size:13px;color:<?= $rate>=80?'var(--success)':($rate>=60?'var(--warning)':'var(--danger)') ?>"><?= $rate ?>%</span>
                <div class="progress flex-1" style="min-width:60px">
                  <div class="progress-bar <?= $rate>=80?'success':($rate>=60?'warning':'danger') ?>" style="width:<?= $rate ?>%"></div>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScripts = "<script>
function updateRadios() {
  document.querySelectorAll('.att-radio').forEach(r => {
    const badges = r.closest('div.d-flex').querySelectorAll('.badge');
    badges.forEach(b => b.style.opacity = '0.3');
    if (r.checked) r.parentElement.querySelector('.badge').style.opacity = '1';
  });
}
// Init on load
document.querySelectorAll('.att-radio:checked').forEach(r => {
  r.parentElement.querySelector('.badge').style.opacity = '1';
});
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>
