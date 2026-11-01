<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/analysis.php';
require_once __DIR__ . '/../includes/report_shell.php';
requireRole('admin');

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$stmt = db()->prepare("SELECT s.*, y.year_name FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id WHERE s.id=?");
$stmt->execute([$semId]);
$semester = $stmt->fetch();
if (!$semester) { setFlash('danger','Invalid semester.'); redirect(baseUrl('reports/index.php')); }

$stmt = db()->prepare("
    SELECT COUNT(DISTINCT student_id) AS students,
           COALESCE(AVG(percentage),0) AS avg,
           COALESCE(SUM(CASE WHEN percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100,0) AS pass_rate
    FROM results WHERE semester_id=? AND status='published'
");
$stmt->execute([$semId]);
$overall = $stmt->fetch();

$stmt = db()->prepare("SELECT grade, COUNT(*) AS c FROM results WHERE semester_id=? AND status='published' GROUP BY grade ORDER BY grade");
$stmt->execute([$semId]);
$grades = [];
foreach ($stmt->fetchAll() as $g) $grades[$g['grade']] = (int)$g['c'];

$stmt = db()->prepare("
    SELECT p.code, p.name,
           (SELECT AVG(r.percentage) FROM results r JOIN students s2 ON s2.id=r.student_id WHERE s2.programme_id=p.id AND r.semester_id=? AND r.status='published') AS avg,
           (SELECT SUM(CASE WHEN r.percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100 FROM results r JOIN students s2 ON s2.id=r.student_id WHERE s2.programme_id=p.id AND r.semester_id=? AND r.status='published') AS pass_rate
    FROM programmes p WHERE p.status='active' ORDER BY p.code
");
$stmt->execute([$semId, $semId]);
$programmes = $stmt->fetchAll();

$stmt = db()->prepare("
    SELECT sub.code, sub.name,
           (SELECT AVG(r.percentage) FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS avg,
           (SELECT SUM(CASE WHEN r.percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100 FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS pass_rate
    FROM subjects sub WHERE sub.status='active' ORDER BY sub.code
");
$stmt->execute([$semId, $semId]);
$subjects = $stmt->fetchAll();

$trend = db()->query("
    SELECT sem.semester_name, y.year_name, y.start_date,
           AVG(r.percentage) AS avg
    FROM semesters sem
    JOIN academic_years y ON y.id=sem.academic_year_id
    LEFT JOIN results r ON r.semester_id=sem.id AND r.status='published'
    GROUP BY sem.id, sem.semester_name, y.year_name, y.start_date
    ORDER BY y.start_date, sem.start_date
")->fetchAll();

$factors = db()->query("SELECT * FROM influencing_factors WHERE status='active' ORDER BY name")->fetchAll();
$factorRows = [];
foreach ($factors as $f) {
    $a = factorAssociation((int)$f['id'], $semId);
    $factorRows[] = ['factor'=>$f, 'r'=>$a['r'], 'n'=>$a['n'], 'desc'=>describeCorrelation($a['r'])];
}

$students = db()->query("SELECT id, registration_number, first_name, last_name FROM students WHERE status='active' ORDER BY registration_number")->fetchAll();
$attention = [];
foreach ($students as $s) {
    $a = attentionStatus((int)$s['id'], $semId);
    if ($a['status'] === 'Normal') continue;
    $attention[] = ['student'=>$s] + $a;
}
usort($attention, fn($a,$b) => $a['status'] === 'Requires Attention' ? -1 : 1);

$meta = [
    'Academic Year' => $semester['year_name'],
    'Semester'      => $semester['semester_name'],
    'Report Scope'  => 'Institution-wide',
];
reportHeader('Academic Performance Analysis Report', 'Institution-Wide Summary', $meta);
?>
  <section class="report-section">
    <h2>Overall Performance</h2>
    <div class="stat-grid">
      <div class="stat-box"><div class="lbl">Students with Results</div><div class="val"><?= (int)$overall['students'] ?></div></div>
      <div class="stat-box"><div class="lbl">Overall Average</div><div class="val"><?= pct((float)$overall['avg']) ?></div></div>
      <div class="stat-box"><div class="lbl">Pass Rate</div><div class="val"><?= pct((float)$overall['pass_rate']) ?></div></div>
      <div class="stat-box"><div class="lbl">Failure Rate</div><div class="val"><?= pct(100 - (float)$overall['pass_rate']) ?></div></div>
    </div>
  </section>

  <?php if ($grades): ?>
  <section class="report-section">
    <h2>Grade Distribution</h2>
    <table>
      <thead><tr><th>Grade</th><?php foreach (array_keys($grades) as $g): ?><th><?= e($g) ?></th><?php endforeach; ?></tr></thead>
      <tbody><tr><td><strong>Count</strong></td><?php foreach ($grades as $c): ?><td><?= (int)$c ?></td><?php endforeach; ?></tr></tbody>
    </table>
  </section>
  <?php endif; ?>

  <section class="report-section">
    <h2>Performance Trend</h2>
    <?php if ($trend): ?>
      <table>
        <thead><tr><th>Academic Year</th><th>Semester</th><th>Average</th></tr></thead>
        <tbody>
        <?php foreach ($trend as $t): ?>
          <tr>
            <td><?= e($t['year_name']) ?></td>
            <td><?= e($t['semester_name']) ?></td>
            <td><?= $t['avg'] !== null ? pct((float)$t['avg']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-msg">No trend data.</p>
    <?php endif; ?>
  </section>

  <section class="report-section">
    <h2>Programme Comparison</h2>
    <table>
      <thead><tr><th>Code</th><th>Programme</th><th>Average</th><th>Pass Rate</th></tr></thead>
      <tbody>
      <?php foreach ($programmes as $p): ?>
        <tr>
          <td><strong><?= e($p['code']) ?></strong></td>
          <td><?= e($p['name']) ?></td>
          <td><?= $p['avg'] !== null ? pct((float)$p['avg']) : '—' ?></td>
          <td><?= $p['pass_rate'] !== null ? pct((float)$p['pass_rate']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="report-section">
    <h2>Subject Comparison</h2>
    <table>
      <thead><tr><th>Code</th><th>Subject</th><th>Average</th><th>Pass Rate</th></tr></thead>
      <tbody>
      <?php foreach ($subjects as $s): if ($s['avg'] === null) continue; ?>
        <tr>
          <td><strong><?= e($s['code']) ?></strong></td>
          <td><?= e($s['name']) ?></td>
          <td><?= pct((float)$s['avg']) ?></td>
          <td><?= pct((float)$s['pass_rate']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="report-section">
    <h2>Influencing Factors — Observed Association</h2>
    <p class="muted" style="font-size:.85rem"><em>These are statistical associations only — not causal claims.</em></p>
    <?php if ($factorRows): ?>
      <table>
        <thead><tr><th>Factor</th><th>Code</th><th>Ratings</th><th>Pearson r</th><th>Interpretation</th></tr></thead>
        <tbody>
        <?php foreach ($factorRows as $f): ?>
          <tr>
            <td><?= e($f['factor']['name']) ?></td>
            <td><?= e($f['factor']['code']) ?></td>
            <td><?= (int)$f['n'] ?></td>
            <td><?= $f['r'] === null ? '—' : number_format($f['r'], 3) ?></td>
            <td><?= e($f['desc']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-msg">No factor data available.</p>
    <?php endif; ?>
  </section>

  <section class="report-section">
    <h2>Students Requiring Attention</h2>
    <?php if ($attention): ?>
      <table>
        <thead><tr><th>Reg. No.</th><th>Student</th><th>Average</th><th>Attendance</th><th>Status</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($attention as $a): ?>
          <tr>
            <td><strong><?= e($a['student']['registration_number']) ?></strong></td>
            <td><?= e($a['student']['first_name'].' '.$a['student']['last_name']) ?></td>
            <td><?= pct($a['avg']) ?></td>
            <td><?= pct($a['attendance']) ?></td>
            <td><span class="badge-status-<?= $a['status']==='Requires Attention'?'Requires':e($a['status']) ?>"><?= e($a['status']) ?></span></td>
            <td><small><?= e(implode(', ', $a['reason'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty-msg">No students currently flagged.</p>
    <?php endif; ?>
  </section>
<?php
reportFooter($_SESSION['full_name'] ?? '');