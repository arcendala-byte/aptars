<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/analysis.php';
require_once __DIR__ . '/../includes/report_shell.php';
requireRole(['admin','lecturer']);

$role = $_SESSION['role'];
$myProfile = currentProfile();

$subjectId = (int)get('subject_id', 0);
$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));

if ($subjectId <= 0) {
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
    $semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();
    if ($role === 'admin') {
        $subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
    } else {
        $lectId = (int)($myProfile['id'] ?? 0);
        $stmt = db()->prepare("SELECT DISTINCT sub.id, sub.code, sub.name FROM lecturer_subjects ls JOIN subjects sub ON sub.id=ls.subject_id WHERE ls.lecturer_id=? ORDER BY sub.code");
        $stmt->execute([$lectId]);
        $subjects = $stmt->fetchAll();
    }
    ?>
    <main class="content">
      <div class="page-head"><h1>Subject Report</h1><p class="muted mb-0">Select a subject.</p></div>
      <div class="panel"><div class="panel-body">
        <form method="get" class="row g-3">
          <div class="col-md-6"><label class="form-label">Subject</label>
            <select name="subject_id" class="form-select" required>
              <?= selectOptions($subjects, 'id', 'code', null, 'Select subject') ?>
            </select></div>
          <div class="col-md-4"><label class="form-label">Semester</label>
            <select name="semester_id" class="form-select"><?= selectOptions($semesters, 'id', 'label', $semId) ?></select></div>
          <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Open</button></div>
        </form>
      </div></div>
    </main>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

if ($role === 'lecturer') {
    $lectId = (int)($myProfile['id'] ?? 0);
    $chk = db()->prepare('SELECT 1 FROM lecturer_subjects WHERE lecturer_id=? AND subject_id=? AND semester_id=? LIMIT 1');
    $chk->execute([$lectId, $subjectId, $semId]);
    if (!$chk->fetchColumn()) {
        setFlash('danger','You are not assigned to that subject/semester.');
        redirect(baseUrl('reports/index.php'));
    }
}

$stmt = db()->prepare("
    SELECT sub.*, p.name AS programme_name, p.code AS programme_code
    FROM subjects sub JOIN programmes p ON p.id = sub.programme_id
    WHERE sub.id = ?
");
$stmt->execute([$subjectId]);
$subject = $stmt->fetch();
if (!$subject) { setFlash('danger','Subject not found.'); redirect(baseUrl('reports/index.php')); }

$stmt = db()->prepare("SELECT s.*, y.year_name FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id WHERE s.id=?");
$stmt->execute([$semId]);
$semester = $stmt->fetch();
if (!$semester) { setFlash('danger','Invalid semester.'); redirect(baseUrl('reports/index.php')); }

$stats = subjectPerformance($subjectId, $semId);

$lectStmt = db()->prepare("
    SELECT l.first_name, l.last_name, l.staff_number
    FROM lecturer_subjects ls JOIN lecturers l ON l.id = ls.lecturer_id
    WHERE ls.subject_id=? AND ls.semester_id=?
");
$lectStmt->execute([$subjectId, $semId]);
$lecturers = $lectStmt->fetchAll();

$rowsStmt = db()->prepare("
    SELECT s.registration_number, s.first_name, s.last_name,
           AVG(r.percentage) AS avg_pct,
           (SELECT SUM(CASE WHEN a2.status IN ('Present','Late') THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100
            FROM attendance a2 WHERE a2.student_id = s.id AND a2.subject_id = ? AND a2.semester_id = ?) AS attendance
    FROM student_subjects ss
    JOIN students s ON s.id = ss.student_id
    LEFT JOIN results r ON r.student_id = s.id AND r.subject_id = ? AND r.semester_id = ? AND r.status='published'
    WHERE ss.subject_id = ? AND ss.semester_id = ? AND ss.status='registered'
    GROUP BY s.id, s.registration_number, s.first_name, s.last_name
    ORDER BY s.registration_number
");
$rowsStmt->execute([$subjectId, $semId, $subjectId, $semId, $subjectId, $semId]);
$students = $rowsStmt->fetchAll();

$lecturerNames = implode(', ', array_map(fn($l) => $l['first_name'] . ' ' . $l['last_name'], $lecturers));
$meta = [
    'Subject'   => $subject['code'] . ' — ' . $subject['name'],
    'Programme' => $subject['programme_code'],
    'Semester'  => $semester['year_name'] . ' / ' . $semester['semester_name'],
    'Lecturer'  => $lecturerNames !== '' ? $lecturerNames : '—',
];
reportHeader('Subject Performance Report', $subject['code'] . ' — ' . $subject['name'], $meta);
?>
  <section class="report-section">
    <h2>Statistics</h2>
    <div class="stat-grid">
      <div class="stat-box"><div class="lbl">Students</div><div class="val"><?= (int)$stats['students'] ?></div></div>
      <div class="stat-box"><div class="lbl">Average</div><div class="val"><?= pct($stats['avg']) ?></div></div>
      <div class="stat-box"><div class="lbl">Pass Rate</div><div class="val"><?= pct($stats['pass_rate']) ?></div></div>
      <div class="stat-box"><div class="lbl">Failure Rate</div><div class="val"><?= pct($stats['failure_rate']) ?></div></div>
      <div class="stat-box"><div class="lbl">Lowest</div><div class="val"><?= pct($stats['lowest']) ?></div></div>
      <div class="stat-box"><div class="lbl">Highest</div><div class="val"><?= pct($stats['highest']) ?></div></div>
    </div>
  </section>

  <?php if ($stats['grade_distribution']): ?>
  <section class="report-section">
    <h2>Grade Distribution</h2>
    <table>
      <thead><tr>
        <?php foreach (array_keys($stats['grade_distribution']) as $g): ?><th><?= e($g) ?></th><?php endforeach; ?>
      </tr></thead>
      <tbody><tr>
        <?php foreach ($stats['grade_distribution'] as $c): ?><td><?= (int)$c ?></td><?php endforeach; ?>
      </tr></tbody>
    </table>
  </section>
  <?php endif; ?>

  <section class="report-section">
    <h2>Per-Student Breakdown</h2>
    <?php if ($students): ?>
      <table>
        <thead><tr><th>Reg. No.</th><th>Student</th><th>Average</th><th>Grade</th><th>Attendance</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s):
            $avg = $s['avg_pct'] !== null ? (float)$s['avg_pct'] : null;
            $att = $s['attendance'] !== null ? (float)$s['attendance'] : null;
        ?>
          <tr>
            <td><strong><?= e($s['registration_number']) ?></strong></td>
            <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
            <td><?= pct($avg) ?></td>
            <td><?= $avg !== null ? '<span class="badge-grade">'.e(calculateGrade($avg)).'</span>' : '—' ?></td>
            <td><?= pct($att) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-msg">No students registered for this subject/semester.</p>
    <?php endif; ?>
  </section>
<?php
reportFooter($_SESSION['full_name'] ?? '');