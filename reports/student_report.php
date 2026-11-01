<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/analysis.php';
require_once __DIR__ . '/../includes/report_shell.php';
requireRole(['admin','lecturer','student']);

$role = $_SESSION['role'];
$myProfile = currentProfile();
$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));

$studentId = (int)get('student_id', 0);
if ($role === 'student') {
    $studentId = (int)($myProfile['id'] ?? 0);
}
if ($studentId <= 0) {
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
    $semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();
    if ($role === 'admin') {
        $students = db()->query("SELECT id, registration_number, first_name, last_name FROM students WHERE status='active' ORDER BY registration_number")->fetchAll();
    } else {
        $lectId = (int)($myProfile['id'] ?? 0);
        $stmt = db()->prepare("
            SELECT DISTINCT s.id, s.registration_number, s.first_name, s.last_name
            FROM lecturer_subjects ls
            JOIN student_subjects ss ON ss.subject_id = ls.subject_id AND ss.semester_id = ls.semester_id
            JOIN students s ON s.id = ss.student_id
            WHERE ls.lecturer_id = ? AND ss.status='registered'
            ORDER BY s.registration_number
        ");
        $stmt->execute([$lectId]);
        $students = $stmt->fetchAll();
    }
    ?>
    <main class="content">
      <div class="page-head"><h1>Student Report</h1><p class="muted mb-0">Select a student to view their report.</p></div>
      <div class="panel"><div class="panel-body">
        <form method="get" class="row g-3">
          <div class="col-md-6"><label class="form-label">Student</label>
            <select name="student_id" class="form-select" required>
              <?= selectOptions($students, 'id', 'registration_number', null, 'Select student') ?>
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

$stmt = db()->prepare("
    SELECT s.*, p.name AS programme_name, p.code AS programme_code,
           d.name AS dept_name, d.code AS dept_code
    FROM students s
    JOIN programmes  p ON p.id = s.programme_id
    JOIN departments d ON d.id = s.department_id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
if (!$student) { setFlash('danger','Student not found.'); redirect(baseUrl('reports/index.php')); }

if ($role === 'student' && (int)$student['id'] !== (int)($myProfile['id'] ?? 0)) {
    setFlash('danger','You can only view your own report.'); redirect(baseUrl('student/performance.php'));
}
if ($role === 'lecturer') {
    $lectId = (int)($myProfile['id'] ?? 0);
    $chk = db()->prepare("
        SELECT 1 FROM student_subjects ss
        JOIN lecturer_subjects ls ON ls.subject_id = ss.subject_id AND ls.semester_id = ss.semester_id
        WHERE ss.student_id = ? AND ls.lecturer_id = ? LIMIT 1
    ");
    $chk->execute([$studentId, $lectId]);
    if (!$chk->fetchColumn()) { setFlash('danger','That student is not in any of your classes.'); redirect(baseUrl('reports/index.php')); }
}

$stmt = db()->prepare("SELECT s.*, y.year_name FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id WHERE s.id=?");
$stmt->execute([$semId]);
$semester = $stmt->fetch();
if (!$semester) { setFlash('danger','Invalid semester.'); redirect(baseUrl('reports/index.php')); }

$summary   = studentPerformanceSummary($studentId, $semId);
$attention = attentionStatus($studentId, $semId);

$stmt = db()->prepare("
    SELECT r.marks, r.max_marks, r.percentage, r.grade, r.grade_point,
           sub.code AS subj_code, sub.name AS subj_name, sub.credit_hours,
           a.name AS assessment_name, a.type
    FROM results r
    JOIN subjects sub ON sub.id = r.subject_id
    JOIN assessments a ON a.id = r.assessment_id
    WHERE r.student_id = ? AND r.semester_id = ? AND r.status='published'
    ORDER BY sub.code, a.id
");
$stmt->execute([$studentId, $semId]);
$results = $stmt->fetchAll();

$bySubject = [];
foreach ($results as $r) {
    $bySubject[$r['subj_code']]['name'] = $r['subj_name'];
    $bySubject[$r['subj_code']]['credits'] = $r['credit_hours'];
    $bySubject[$r['subj_code']]['rows'][] = $r;
}

$stmt = db()->prepare("
    SELECT sub.code, sub.name, COUNT(*) AS sessions,
           SUM(CASE WHEN a.status IN ('Present','Late') THEN 1 ELSE 0 END) AS attended
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ? AND a.semester_id = ?
    GROUP BY sub.id, sub.code, sub.name ORDER BY sub.code
");
$stmt->execute([$studentId, $semId]);
$attendance = $stmt->fetchAll();

$subtitle = fullName($student) . '  ·  ' . $student['registration_number'];
$meta = [
    'Programme'     => $student['programme_code'] . ' — ' . $student['programme_name'],
    'Department'    => $student['dept_code'],
    'Academic Year' => $semester['year_name'],
    'Semester'      => $semester['semester_name'],
    'Admission Yr'  => $student['admission_year'],
];

reportHeader('Student Academic Report', $subtitle, $meta);
?>
  <section class="report-section">
    <h2>Summary</h2>
    <div class="stat-grid">
      <div class="stat-box"><div class="lbl">Average</div><div class="val"><?= pct($summary['avg']) ?></div></div>
      <div class="stat-box"><div class="lbl">GPA</div><div class="val"><?= number_format($summary['gpa'],2) ?></div></div>
      <div class="stat-box"><div class="lbl">Subjects Passed</div><div class="val"><?= (int)$summary['passed'] ?> / <?= (int)$summary['subjects'] ?></div></div>
      <div class="stat-box"><div class="lbl">Attendance</div><div class="val"><?= pct($summary['attendance_pct']) ?></div></div>
    </div>
    <p><strong>Attention Status:</strong>
      <span class="badge-status-<?= $attention['status']==='Requires Attention'?'Requires':e($attention['status']) ?>">
        <?= e($attention['status']) ?>
      </span>
      <?php if ($attention['reason']): ?><small class="muted"> — <?= e(implode(', ', $attention['reason'])) ?></small><?php endif; ?>
    </p>
  </section>

  <section class="report-section">
    <h2>Detailed Marks</h2>
    <?php if ($bySubject): ?>
      <?php foreach ($bySubject as $code => $subj): ?>
        <h6 style="margin-top:1rem;color:#0f3460"><strong><?= e($code) ?></strong> — <?= e($subj['name']) ?> <small class="muted">(<?= (int)$subj['credits'] ?> credits)</small></h6>
        <table>
          <thead><tr><th>Assessment</th><th>Type</th><th>Marks</th><th>Max</th><th>%</th><th>Grade</th><th>Points</th></tr></thead>
          <tbody>
          <?php $subjSum = 0; $subjN = 0;
                foreach ($subj['rows'] as $r): $subjSum += (float)$r['percentage']; $subjN++; ?>
            <tr>
              <td><?= e($r['assessment_name']) ?></td>
              <td><?= e($r['type']) ?></td>
              <td><?= (float)$r['marks'] ?></td>
              <td><?= (float)$r['max_marks'] ?></td>
              <td><?= pct((float)$r['percentage']) ?></td>
              <td><span class="badge-grade"><?= e($r['grade']) ?></span></td>
              <td><?= number_format((float)$r['grade_point'],2) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4"><strong>Subject Average</strong></td>
              <td colspan="2"><strong><?= pct($subjN ? $subjSum/$subjN : 0) ?></strong></td>
              <td><strong><?= e(calculateGrade($subjN ? $subjSum/$subjN : 0)) ?></strong></td>
            </tr>
          </tfoot>
        </table>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty-msg">No published results for this semester.</p>
    <?php endif; ?>
  </section>

  <section class="report-section">
    <h2>Attendance Summary</h2>
    <?php if ($attendance): ?>
      <table>
        <thead><tr><th>Code</th><th>Subject</th><th>Sessions</th><th>Attended</th><th>Attendance %</th></tr></thead>
        <tbody>
        <?php foreach ($attendance as $a): $p = (int)$a['sessions']>0 ? (int)$a['attended']/(int)$a['sessions']*100 : 0; ?>
          <tr>
            <td><strong><?= e($a['code']) ?></strong></td>
            <td><?= e($a['name']) ?></td>
            <td><?= (int)$a['sessions'] ?></td>
            <td><?= (int)$a['attended'] ?></td>
            <td><?= pct($p) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-msg">No attendance records for this semester.</p>
    <?php endif; ?>
  </section>
<?php
reportFooter($_SESSION['full_name'] ?? '');