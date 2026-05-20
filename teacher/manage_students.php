<?php
// ============================================================
//  Teacher — Manage Students
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAuth('teacher');

$db    = getDB();
$error = '';

// ─── Handle Add Student ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $rollNo  = trim($_POST['roll_no'] ?? '');
    $pass    = trim($_POST['password'] ?? '');

    if ($name && $email && $pass) {
        try {
            $stmt = $db->prepare("INSERT INTO users (name,email,password,role,roll_no) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'student', $rollNo ?: null]);
            setFlash('success', "Student '$name' added successfully.");
            header('Location: /student-dashboard/teacher/manage_students.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not add student: ' . ($e->getCode() == 23000 ? 'Email already exists.' : $e->getMessage());
        }
    } else {
        $error = 'Please fill in Name, Email, and Password.';
    }
}

// ─── Handle Delete ────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$_GET['delete']]);
    setFlash('success', 'Student removed.');
    header('Location: /student-dashboard/teacher/manage_students.php');
    exit;
}

// ─── Fetch Students ───────────────────────────────────────────
$students = $db->query("
    SELECT u.*,
      (SELECT COUNT(*) FROM grades g WHERE g.student_id=u.id) AS grade_count,
      (SELECT COUNT(*) FROM attendance a WHERE a.student_id=u.id) AS att_days
    FROM users u WHERE u.role='student' ORDER BY u.name
")->fetchAll();

$pageTitle   = 'Manage Students';
$activeNav   = 't_students';
$topbarActions = '<button onclick="document.getElementById(\'addModal\').style.display=\'flex\'" class="btn btn-primary btn-sm"><i class="bi bi-person-plus-fill"></i> Add Student</button>';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Students Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-people-fill"></i> All Students (<?= count($students) ?>)</div>
    <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg"></i> Add Student
    </button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Roll No</th>
          <th>Grades</th>
          <th>Att. Days</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($students): foreach ($students as $i => $s): ?>
        <tr>
          <td class="text-muted"><?= $i+1 ?></td>
          <td>
            <div class="d-flex align-center gap-2">
              <div style="width:32px;height:32px;background:linear-gradient(135deg,var(--accent),#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0">
                <?= strtoupper(substr($s['name'],0,1)) ?>
              </div>
              <span class="fw-600"><?= htmlspecialchars($s['name']) ?></span>
            </div>
          </td>
          <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($s['email']) ?></td>
          <td><code style="font-size:12px;color:var(--accent)"><?= htmlspecialchars($s['roll_no'] ?? '—') ?></code></td>
          <td><span class="badge badge-accent"><?= $s['grade_count'] ?> entries</span></td>
          <td><span class="badge badge-info"><?= $s['att_days'] ?> days</span></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
          <td>
            <a href="?delete=<?= $s['id'] ?>"
               onclick="return confirm('Remove <?= htmlspecialchars(addslashes($s['name'])) ?>?')"
               class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i></a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No students yet. Add one above!</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Student Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#1e293b;border:1px solid #334155;border-radius:16px;padding:32px;width:480px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.5)">
    <div class="d-flex justify-between align-center mb-3">
      <h3 style="color:#f1f5f9;font-size:17px;font-weight:700"><i class="bi bi-person-plus-fill" style="color:var(--accent)"></i> Add New Student</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label" style="color:#94a3b8">Full Name *</label>
        <input class="form-control" name="name" required placeholder="e.g. Umar Farooq" style="background:#0f172a;border-color:#334155;color:#e2e8f0;">
      </div>
      <div class="form-group">
        <label class="form-label" style="color:#94a3b8">Email Address *</label>
        <input class="form-control" name="email" type="email" required placeholder="student@school.com" style="background:#0f172a;border-color:#334155;color:#e2e8f0;">
      </div>
      <div class="form-group">
        <label class="form-label" style="color:#94a3b8">Roll Number</label>
        <input class="form-control" name="roll_no" placeholder="e.g. CS-2021-04" style="background:#0f172a;border-color:#334155;color:#e2e8f0;">
      </div>
      <div class="form-group">
        <label class="form-label" style="color:#94a3b8">Password *</label>
        <input class="form-control" name="password" type="password" required placeholder="Set a password for the student" style="background:#0f172a;border-color:#334155;color:#e2e8f0;">
      </div>
      <div class="d-flex gap-2 justify-end mt-2">
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
        <button type="submit" name="add_student" class="btn btn-primary"><i class="bi bi-person-check-fill"></i> Add Student</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
