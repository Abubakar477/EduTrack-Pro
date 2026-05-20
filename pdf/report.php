<?php
// ============================================================
//  PDF Progress Report — FPDF
//  Generates and downloads a PDF report for the logged-in student
// ============================================================
ob_start(); // Buffer output — prevents stray HTML/whitespace from corrupting the PDF binary
session_start();
require_once __DIR__ . '/../config/db.php'; // defines getDB(), requireAuth(), gpaFromMarks() etc.
requireAuth('student'); // redirect to login if not a logged-in student

$fpdfPath = __DIR__ . '/../fpdf/fpdf.php';
if (!file_exists($fpdfPath)) {
    die("
    <div style='font-family:sans-serif;padding:32px;background:#fee2e2;border-left:4px solid #ef4444;margin:32px;border-radius:8px'>
        <h3 style='color:#b91c1c'>FPDF Not Found</h3>
        <p style='color:#7f1d1d'>Please run <a href='/student-dashboard/setup.php'>setup.php</a> first to download the FPDF library, or manually download <code>fpdf.php</code> from
        <a href='http://www.fpdf.org' target='_blank'>fpdf.org</a> and place it in the <code>/fpdf/</code> folder.</p>
    </div>
    ");
}

require_once $fpdfPath;

$db        = getDB();
$studentId = $_SESSION['user_id'];

// ─── Fetch Student Info ───────────────────────────────────────
$student = $db->prepare("SELECT * FROM users WHERE id=?");
$student->execute([$studentId]);
$student = $student->fetch();

// ─── Current Semester ─────────────────────────────────────────
$semester = $db->query("SELECT * FROM semesters WHERE is_current=1 LIMIT 1")->fetch();
$semId    = $semester['id'] ?? 0;

// ─── Grades for Current Semester ─────────────────────────────
$gradesStmt = $db->prepare("
    SELECT s.name AS subject, s.code, s.credit_hours,
           g.marks_obtained, g.total_marks
    FROM grades g JOIN subjects s ON s.id=g.subject_id
    WHERE g.student_id=? AND g.semester_id=?
    ORDER BY s.name
");
$gradesStmt->execute([$studentId, $semId]);
$grades = $gradesStmt->fetchAll();

// ─── GPA Calculation ─────────────────────────────────────────
$totalPts  = 0; $totalCr  = 0;
foreach ($grades as $g) {
    $gp        = gpaFromMarks($g['marks_obtained'],$g['total_marks']);
    $totalPts += $gp * $g['credit_hours'];
    $totalCr  += $g['credit_hours'];
}
$semGpa  = $totalCr > 0 ? round($totalPts/$totalCr,2) : 0;
$semAvg  = count($grades)>0 ? round(array_sum(array_map(fn($g)=>($g['marks_obtained']/$g['total_marks'])*100,$grades))/count($grades),1) : 0;

// ─── Attendance ───────────────────────────────────────────────
$attStmt = $db->prepare("
    SELECT status, COUNT(*) AS cnt FROM attendance
    WHERE student_id=? AND MONTH(date)=MONTH(NOW()) AND YEAR(date)=YEAR(NOW())
    GROUP BY status
");
$attStmt->execute([$studentId]);
$attData = ['present'=>0,'absent'=>0,'late'=>0];
foreach ($attStmt->fetchAll() as $r) $attData[$r['status']] = (int)$r['cnt'];
$attTotal = array_sum($attData);
$attRate  = $attTotal>0 ? round(($attData['present']/$attTotal)*100) : 0;

// ─── GPA Per Semester ─────────────────────────────────────────
$semRaw = $db->prepare("
    SELECT sem.name AS semester, g.marks_obtained, g.total_marks, s.credit_hours
    FROM grades g
    JOIN semesters sem ON sem.id=g.semester_id
    JOIN subjects s ON s.id=g.subject_id
    WHERE g.student_id=?
    ORDER BY sem.id
");
$semRaw->execute([$studentId]);
$gpaBySem = [];
foreach ($semRaw->fetchAll() as $r) {
    $k = $r['semester'];
    if (!isset($gpaBySem[$k])) $gpaBySem[$k] = ['pts'=>0,'cr'=>0];
    $gpaBySem[$k]['pts'] += gpaFromMarks($r['marks_obtained'],$r['total_marks']) * $r['credit_hours'];
    $gpaBySem[$k]['cr']  += $r['credit_hours'];
}
$cgpa = 0; $allPts=0; $allCr=0;
foreach ($gpaBySem as $d) { $allPts+=$d['pts']; $allCr+=$d['cr']; }
$cgpa = $allCr>0 ? round($allPts/$allCr,2) : 0;

// ============================================================
//  FPDF PDF Generation
// ============================================================

class ReportPDF extends FPDF {
    function Header() {
        // Header background
        $this->SetFillColor(63, 63, 241); // Indigo
        $this->Rect(0, 0, 210, 38, 'F');
        // Title
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(15, 8);
        $this->Cell(0, 10, 'EduTrack Pro', 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->SetXY(15, 20);
        $this->Cell(0, 8, 'Student Performance Progress Report', 0, 1);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(14);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Generated on ' . date('F j, Y \a\t H:i') . '  |  Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(99, 102, 241);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, '  ' . $title, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    function InfoRow($label, $value) {
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(55, 7, $label . ':', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 7, $value, 0, 1);
    }
}

$pdf = new ReportPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// ─── Student Info ─────────────────────────────────────────────
$pdf->SectionTitle('Student Information');
$pdf->InfoRow('Full Name',    $student['name']);
$pdf->InfoRow('Roll Number',  $student['roll_no'] ?? 'N/A');
$pdf->InfoRow('Email',        $student['email']);
$pdf->InfoRow('Semester',     $semester['name'] ?? 'N/A');
$pdf->InfoRow('Report Date',  date('F j, Y'));
$pdf->Ln(5);

// ─── Summary Stats ─────────────────────────────────────────────
$pdf->SectionTitle('Academic Summary');
$pdf->SetFont('Arial', '', 9);
$stats = [
    ['Semester GPA', number_format($semGpa,2), '/ 4.00'],
    ['Cumulative GPA (CGPA)', number_format($cgpa,2), '/ 4.00'],
    ['Semester Average', $semAvg.'%', ''],
    ['Attendance Rate (This Month)', $attRate.'%', "({$attData['present']} present, {$attData['absent']} absent)"],
    ['Total Credit Hours (This Sem)', $totalCr, 'hours'],
];

foreach ($stats as [$label, $value, $sub]) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(100,116,139);
    $pdf->Cell(90, 7, $label . ':', 0, 0);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(63,63,241);
    $pdf->Cell(30, 7, $value, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100,116,139);
    $pdf->Cell(0, 7, $sub, 0, 1);
}
$pdf->Ln(5);

// ─── Marks Table ─────────────────────────────────────────────
$pdf->SectionTitle('Subject Marks — ' . ($semester['name'] ?? 'Current Semester'));

// Table header
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(248, 250, 252);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetLineWidth(0.1);
foreach (['Subject'=>60,'Code'=>25,'Marks'=>25,'Out of'=>20,'%'=>18,'Grade'=>18,'GPA'=>15] as $h=>$w) {
    $pdf->Cell($w, 8, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);
$rowBg = false;
foreach ($grades as $g) {
    $pct      = round(($g['marks_obtained']/$g['total_marks'])*100,1);
    $grade    = gradeFromMarks($g['marks_obtained'],$g['total_marks']);
    $gpaPoint = gpaFromMarks($g['marks_obtained'],$g['total_marks']);
    $pass     = $pct >= 50;

    $pdf->SetFillColor($rowBg ? 248 : 255, $rowBg ? 250 : 255, $rowBg ? 252 : 255);
    $pdf->SetTextColor($pass ? 15 : 180, $pass ? 23 : 30, $pass ? 42 : 30);

    $pdf->Cell(60, 7, $g['subject'], 1, 0, 'L', true);
    $pdf->Cell(25, 7, $g['code'],    1, 0, 'C', true);
    $pdf->Cell(25, 7, $g['marks_obtained'], 1, 0, 'C', true);
    $pdf->SetTextColor(15,23,42);
    $pdf->Cell(20, 7, (int)$g['total_marks'], 1, 0, 'C', true);
    $pdf->Cell(18, 7, $pct.'%', 1, 0, 'C', true);
    // Grade colored
    if     (in_array($grade,['A','A-']))       { $pdf->SetTextColor(21,128,61); }
    elseif (in_array($grade,['B+','B','B-']))  { $pdf->SetTextColor(14,116,144); }
    elseif (in_array($grade,['C+','C','C-']))  { $pdf->SetTextColor(161,98,7); }
    else                                        { $pdf->SetTextColor(185,28,28); }
    $pdf->Cell(18, 7, $grade,  1, 0, 'C', true);
    $pdf->SetTextColor(15,23,42);
    $pdf->Cell(15, 7, number_format($gpaPoint,1), 1, 0, 'C', true);
    $pdf->Ln();
    $rowBg = !$rowBg;
}

// GPA row
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(63,63,241);
$pdf->SetTextColor(255,255,255);
$pdf->Cell(60+25+25+20+18, 8, 'Semester GPA', 1, 0, 'L', true);
$pdf->Cell(18+15, 8, number_format($semGpa,2), 1, 0, 'C', true);
$pdf->Ln(12);

// ─── GPA History ─────────────────────────────────────────────
$pdf->SectionTitle('GPA History');
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(248,250,252);
$pdf->SetTextColor(100,116,139);
$pdf->Cell(80, 8, 'Semester', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'GPA', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'Status', 1, 0, 'C', true);
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);
$rowBg = false;
foreach ($gpaBySem as $semN => $d) {
    $g = $d['cr']>0 ? round($d['pts']/$d['cr'],2) : 0;
    $status = $g>=3.5?'Dean\'s List':($g>=3.0?'Excellent':($g>=2.0?'Good':($g>=1.0?'Fair':'At Risk')));
    $pdf->SetFillColor($rowBg?248:255,$rowBg?250:255,$rowBg?252:255);
    if ($g>=3)      { $pdf->SetTextColor(21,128,61); }
    elseif ($g>=2)  { $pdf->SetTextColor(14,116,144); }
    elseif ($g>=1)  { $pdf->SetTextColor(161,98,7); }
    else             { $pdf->SetTextColor(185,28,28); }
    $pdf->Cell(80, 7, $semN, 1, 0, 'L', true);
    $pdf->Cell(30, 7, number_format($g,2), 1, 0, 'C', true);
    $pdf->Cell(80, 7, $status, 1, 0, 'C', true);
    $pdf->Ln();
    $rowBg = !$rowBg;
}

// CGPA row
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(63,63,241);
$pdf->SetTextColor(255,255,255);
$pdf->Cell(80, 8, 'Cumulative GPA (CGPA)', 1, 0, 'L', true);
$pdf->Cell(30+80, 8, number_format($cgpa,2), 1, 0, 'C', true);
$pdf->Ln(10);

// ─── Attendance Summary ───────────────────────────────────────
$pdf->SectionTitle('Attendance Summary (Current Month)');
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(15,23,42);
$pdf->Cell(0,7,"Present: {$attData['present']} days  |  Absent: {$attData['absent']} days  |  Late: {$attData['late']} days  |  Rate: {$attRate}%",0,1);

// Output
$filename = 'Progress_Report_' . preg_replace('/[^A-Za-z0-9_]/', '_', $student['name']) . '_' . date('Y-m-d') . '.pdf';
ob_end_clean(); // Discard any buffered output before sending binary PDF
$pdf->Output('D', $filename);
exit;
