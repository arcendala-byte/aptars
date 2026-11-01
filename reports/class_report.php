<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/analysis.php';
require_once __DIR__ . '/../includes/report_shell.php';
requireRole(['admin','lecturer']);

$role = $_SESSION['role'];
$myProfile = currentProfile();

$programmeId = (int)get('programme_id', 0);
$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));

if ($programmeId <= 0) {
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
    $semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();
    $programmes = db()->query("SELECT p.id, p.code, p.name FROM programmes p WHERE p.status='active' ORDER BY p.code")->fetchAll();
    ?>
    <main class="content">
      <div class="page-head"><h1>Class / Programme Report</h1><p class="muted mb-0">Select a programme.</p></div>
      <div class="panel"><div class="panel-body">
        <form method="get" class="row g-3">
          <div class="col-md-6"><label class="form-label">Programme</label>
            <select name="programme_id" class="form-select" required>
              <?= selectOptions($programmes, 'id', 'code', null, 'Select programme') ?>
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

$stmt = db()->prepare("SELECT p.*, d.name AS dept_name, d.code AS dept_code FROM programmes p JOIN departments d ON d.id = p.department_id WHERE p.id=?");
$stmt->execute([$programmeId]);
$programme = $stmt->fetch();
if (!$programme) { setFlash('danger','Programme not found.'); redirect(baseUrl('reports/index.php')); }

$stmt = db()->prepare("SELECT s.*, y.year_name FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id WHERE s.id=?");
$stmt->execute([$semId]);
$semester = $stmt->fetch();
if (!$semester) { setFlash('danger','Invalid semester.'); redirect(baseUrl('reports/index.php')); }

$stmt = db()->prepare("
    SELECT id, registration_number, first_name, last_name, admission_year, status
    FROM students WHERE programme_id = ? AND status='active'
    ORDER BY registration_number
");
$stmt->execute([$programmeId]);
$students = $stmt->fetchAll();

$summaries = [];
foreach ($students as &$s) {
    $sum = studentPerformanceSummary((int)$s['id'], $semId);
    $att = attentionStatus((int)$s['id'], $semId);
    $s['avg'] = $sum['avg'];
    $s['gpa'] = $sum['gpa'];
    $s['subjects'] = $sum['subjects'];
    $s['attendance'] = $sum['attendance_pct'];
    $s['status_att'] = $att['status'];
    $summaries[] = $s;
}
unset($s);

$totalStudents = count($summaries);
$withResults = array_filter($summaries, fn($s) => $s['subjects'] > 0);
$classAvg = $withResults ? array_sum(array_column($withResults, 'avg')) / count($withResults) : 0;
$classAtt = $withResults ? array_sum(array_column($withResults, 'attendance')) / count($withResults) : 0;
$classGpa = $withResults ? array_sum(array_column($withResults, 'gpa')) / count($withResults) : 0;
$passCount = count(array_filter($withResults, fn($s) => $s['avg'] >= 40));
$passRate = $withResults ? $passCount / count($withResults) * 100 : 0;
$attentionCount = count(array_filter($summaries, fn($s) => $s['status_att'] === 'Requires Attention'));

$meta = [
    'Programme'  => $programme['code'] . ' — ' . $programme['name'],
    'Department' => $programme['dept_code'] . ' — ' . $programme['dept_name'],
    'Level'      => $programme['level'],
    'Semester'   => $semester['year_name'] . ' / ' . $semester['semester_name'],
];
reportHeader('Class / Programme Report', $programme['name'], $meta);
?>
  <section class="report-section">
    <h2>Class Summary</h2>
    <div class="stat-grid">
      <div class="stat-box"><div class="lbl">Active Students</div><div class="val"><?= (int)$totalStudents ?></div></div>
      <div class="stat-box"><div class="lbl">Class Average</div><div class="val"><?= pct($classAvg) ?></div></div>
      <div class="stat-box"><div class="lbl">Class GPA</div><div class="val"><?= number_format($classGpa,2) ?></div></div>
      <div class="stat-box"><div class="lbl">Pass Rate</div><div class="val"><?= pct($passRate) ?></div></div>
      <div class="stat-box"><div class="lbl">Avg Attendance</div><div class="val"><?= pct($classAtt) ?></div></div>
      <div class="stat-box"><div class="lbl">Requires Attention</div><div class="val"><?= (int)$attentionCount ?></div></div>
    </div>
  </section>

  <section class="report-section">
    <h2>Student Breakdown</h2>
    <?php if ($summaries): ?>
      <table>
        <thead><tr><th>Reg. No.</th><th>Student</th><th>Subjects</th><th>Average</th><th>GPA</th><th>Attendance</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($summaries as $s): ?>
          <tr>
            <td><strong><?= e($s['registration_number']) ?></strong></td>
            <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
            <td><?= (int)$s['subjects'] ?></td>
            <td><?= $s['subjects'] > 0 ? pct($s['avg']) : '—' ?></td>
            <td><?= $s['subjects'] > 0 ? number_format($s['gpa'], 2) : '—' ?></td>
            <td><?= $s['attendance'] > 0 ? pct($s['attendance']) : '—' ?></td>
            <td>
              <?php if ($s['subjects'] > 0 || $s['attendance'] > 0): ?>
                <span class="badge-status-<?= $s['status_att']==='Requires Attention'?'Requires':e($s['status_att']) ?>">
                  <?= e($s['status_att']) ?>
                </span>
              <?php else: ?>
                <small class="muted">No data</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-msg">No active students in this programme.</p>
    <?php endif; ?>
  </section>
<?php
reportFooter($_SESSION['full_name'] ?? '');