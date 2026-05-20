<?php
// ============================================================
//  Teacher — Upload / Edit Grades
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('teacher');

$db    = getDB();
$error = '';

// ─── Handle Grade Submission ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $studentId = (int)($_POST['student_id']  ?? 0);
    $subjectId = (int)($_POST['subject_id']  ?? 0);
    $semId     = (int)($_POST['semester_id'] ?? 0);
    $marks     = (float)($_POST['marks']     ?? 0);
    $total     = (float)($_POST['total']     ?? 100);

    if ($studentId && $subjectId && $semId && $marks >= 0 && $total > 0 && $marks <= $total) {
        try {
            $stmt = $db->prepare("
                INSERT INTO grades (student_id, subject_id, semester_id, marks_obtained, total_marks)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), total_marks=VALUES(total_marks), submitted_at=NOW()
            ");
            $stmt->execute([$studentId, $subjectId, $semId, $marks, $total]);
            setFlash('success', 'Grade saved successfully!');
            header('Location: /student-dashboard/teacher/upload_grades.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Save failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill all fields. Marks must be 0–Total.';
    }
}

// ─── Handle Delete Grade ──────────────────────────────────────
if (isset($_GET['delete_grade']) && is_numeric($_GET['delete_grade'])) {
    $db->prepare("DELETE FROM grades WHERE id=?")->execute([$_GET['delete_grade']]);
    setFlash('success', 'Grade deleted.');
    header('Location: /student-dashboard/teacher/upload_grades.php');
    exit;
}

// ─── Fetch Data ───────────────────────────────────────────────
$students  = $db->query("SELECT id, name, roll_no FROM users WHERE role='student' ORDER BY name")->fetchAll();
$subjects  = $db->query("SELECT id, name, code FROM subjects ORDER BY name")->fetchAll();
$semesters = $db->query("SELECT id, name FROM semesters ORDER BY id DESC")->fetchAll();

// Filters
$filterStudent = (int)($_GET['student'] ?? 0);
$filterSem     = (int)($_GET['semester'] ?? 0);

// Recent grades
$where  = 'WHERE 1=1';
$params = [];
if ($filterStudent) { $where .= ' AND g.student_id=?'; $params[] = $filterStudent; }
if ($filterSem)     { $where .= ' AND g.semester_id=?'; $params[] = $filterSem; }

$gradesStmt = $db->prepare("
    SELECT g.id, u.name AS student, u.roll_no, s.name AS subject,
           sem.name AS semester, g.marks_obtained, g.total_marks, g.submitted_at
    FROM grades g
    JOIN users u     ON u.id  = g.student_id
    JOIN subjects s  ON s.id  = g.subject_id
    JOIN semesters sem ON sem.id = g.semester_id
    $where
    ORDER BY g.submitted_at DESC LIMIT 50
");
$gradesStmt->execute($params);
$grades = $gradesStmt->fetchAll();

$pageTitle = 'Upload Grades';
$activeNav = 't_grades';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid-2 mb-4" style="grid-template-columns: 380px 1fr; gap:24px">

  <!-- Add Grade Form -->
  <div class="card" style="height:fit-content">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-clipboard2-plus-fill"></i> Add / Update Grade</div>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label class="form-label">Student</label>
          <select class="form-select" name="student_id" required>
            <option value="">— Select Student —</option>
            <?php foreach ($students as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['roll_no'] ?? 'no roll') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Subject</label>
          <select class="form-select" name="subject_id" required>
            <option value="">— Select Subject —</option>
            <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Semester</label>
          <select class="form-select" name="semester_id" required>
            <option value="">— Select Semester —</option>
            <?php foreach ($semesters as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex gap-2">
          <div class="form-group flex-1">
            <label class="form-label">Marks Obtained</label>
            <input class="form-control" name="marks" type="number" step="0.5" min="0" placeholder="e.g. 75" required>
          </div>
          <div class="form-group" style="width:110px">
            <label class="form-label">Out of</label>
            <input class="form-control" name="total" type="number" step="1" min="1" value="100">
          </div>
        </div>
        <div style="margin-top:4px">
          <button type="submit" name="save_grade" class="btn btn-primary w-100">
            <i class="bi bi-check2-circle"></i> Save Grade
          </button>
        </div>
      </form>

      <div style="margin-top:20px;padding:14px;background:var(--bg-card2);border-radius:var(--radius);border:1px solid var(--border)">
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em">GPA Scale Reference</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:12px">
          <?php
          $scale = [['85-100','A','4.0'],['80-84','A-','3.7'],['75-79','B+','3.3'],
                    ['70-74','B','3.0'],['65-69','B-','2.7'],['60-64','C+','2.3'],
                    ['55-59','C','2.0'],['50-54','C-','1.7'],['40-49','D','1.0'],['<40','F','0.0']];
          foreach ($scale as [$range, $letter, $gpa]):
          ?>
          <div style="display:flex;justify-content:space-between;color:var(--text-secondary);padding:2px 4px">
            <span><?= $range ?>%</span>
            <span style="font-weight:700;color:var(--text-primary)"><?= $letter ?> (<?= $gpa ?>)</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Grades Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-table"></i> Grade Records</div>
      <!-- Filter -->
      <form method="GET" class="d-flex gap-2">
        <select name="student" class="form-select" style="width:160px;padding:6px 10px;font-size:13px">
          <option value="">All Students</option>
          <?php foreach ($students as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterStudent==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="semester" class="form-select" style="width:140px;padding:6px 10px;font-size:13px">
          <option value="">All Sems</option>
          <?php foreach ($semesters as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterSem==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-funnel-fill"></i></button>
      </form>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Student</th><th>Subject</th><th>Semester</th><th>Marks</th><th>%</th><th>Grade</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
          <?php if ($grades): foreach ($grades as $g):
            $pct   = round(($g['marks_obtained']/$g['total_marks'])*100, 1);
            $grade = gradeFromMarks($g['marks_obtained'], $g['total_marks']);
            $badge = gradeBadgeClass($grade);
          ?>
          <tr>
            <td class="fw-600"><?= htmlspecialchars($g['student']) ?></td>
            <td style="font-size:13px;color:var(--text-secondary)"><?= htmlspecialchars($g['subject']) ?></td>
            <td><span class="badge badge-neutral" style="font-size:11px"><?= htmlspecialchars($g['semester']) ?></span></td>
            <td><?= $g['marks_obtained'] ?>/<?= (int)$g['total_marks'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <?= $pct ?>%
                <div class="progress" style="width:50px">
                  <div class="progress-bar <?= $pct>=75?'success':($pct>=50?'':'danger') ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            </td>
            <td><span class="badge <?= $badge ?>"><?= $grade ?></span></td>
            <td style="font-size:12px;color:var(--text-muted)"><?= date('M j, Y', strtotime($g['submitted_at'])) ?></td>
            <td>
              <a href="?delete_grade=<?= $g['id'] ?>"
                 onclick="return confirm('Delete this grade?')"
                 class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i></a>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No grades found. Add one using the form.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
