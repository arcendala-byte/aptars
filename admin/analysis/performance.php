<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
require_once __DIR__ . '/../../includes/analysis.php';
requireRole('admin');

$pageTitle = 'Performance Analysis';
$activeNav = 'analysis_perf';

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

$overall = db()->prepare("
    SELECT COUNT(DISTINCT student_id) AS students,
           COALESCE(AVG(percentage),0) AS avg,
           COALESCE(SUM(CASE WHEN percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100,0) AS pass_rate
    FROM results WHERE semester_id=? AND status='published'
");
$overall->execute([$semId]);
$o = $overall->fetch();

$subjStmt = db()->prepare("
    SELECT sub.id, sub.code, sub.name,
           (SELECT COUNT(DISTINCT student_id) FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS students,
           (SELECT AVG(percentage) FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS avg,
           (SELECT MIN(percentage) FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS lowest,
           (SELECT MAX(percentage) FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS highest,
           (SELECT SUM(CASE WHEN percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100 FROM results r WHERE r.subject_id=sub.id AND r.semester_id=? AND r.status='published') AS pass_rate
    FROM subjects sub
    WHERE sub.status='active'
    ORDER BY sub.code
");
$subjStmt->execute([$semId,$semId,$semId,$semId,$semId]);
$subjects = $subjStmt->fetchAll();

$progStmt = db()->prepare("
    SELECT p.code, p.name,
           COUNT(DISTINCT s.id) AS total,
           (SELECT AVG(r.percentage) FROM results r JOIN students s2 ON s2.id=r.student_id WHERE s2.programme_id=p.id AND r.semester_id=? AND r.status='published') AS avg,
           (SELECT SUM(CASE WHEN r.percentage>=40 THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0)*100 FROM results r JOIN students s2 ON s2.id=r.student_id WHERE s2.programme_id=p.id AND r.semester_id=? AND r.status='published') AS pass_rate
    FROM programmes p
    LEFT JOIN students s ON s.programme_id=p.id AND s.status='active'
    WHERE p.status='active'
    GROUP BY p.id, p.code, p.name
    ORDER BY p.code
");
$progStmt->execute([$semId,$semId]);
$programmes = $progStmt->fetchAll();

$gradeStmt = db()->prepare("SELECT grade, COUNT(*) AS c FROM results WHERE semester_id=? AND status='published' GROUP BY grade ORDER BY grade");
$gradeStmt->execute([$semId]);
$gradeDist = [];
foreach ($gradeStmt->fetchAll() as $g) $gradeDist[$g['grade']] = (int)$g['c'];

$trendStmt = db()->query("
    SELECT sem.id, sem.semester_name, y.year_name, y.start_date,
           AVG(r.percentage) AS avg
    FROM semesters sem
    JOIN academic_years y ON y.id=sem.academic_year_id
    LEFT JOIN results r ON r.semester_id=sem.id AND r.status='published'
    GROUP BY sem.id, sem.semester_name, y.year_name, y.start_date
    ORDER BY y.start_date, sem.start_date
");
$trend = $trendStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Performance Analysis</h1>
      <p class="muted mb-0">Institution-wide performance indicators for a selected semester.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Students with Results</div><div class="stat-value"><?= (int)$o['students'] ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Overall Average</div><div class="stat-value"><?= pct((float)$o['avg']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Pass Rate</div><div class="stat-value"><?= pct((float)$o['pass_rate']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Failure Rate</div><div class="stat-value"><?= pct(100-(float)$o['pass_rate']) ?></div></div></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="panel"><div class="panel-head"><h5>Performance Trend Across Semesters</h5></div>
        <div class="panel-body">
          <?php if ($trend): ?><canvas id="trendChart" height="140"></canvas>
          <?php else: ?><p class="empty">No trend data.</p><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel"><div class="panel-head"><h5>Grade Distribution</h5></div>
        <div class="panel-body">
          <?php if ($gradeDist): ?><canvas id="gradeChart" height="140"></canvas>
          <?php else: ?><p class="empty">No grade data.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="panel mb-4"><div class="panel-head"><h5>Subject Performance</h5></div>
    <div class="panel-body p-0">
      <table class="table mb-0 align-middle">
        <thead><tr><th>Code</th><th>Subject</th><th>Students</th><th>Average</th><th>Lowest</th><th>Highest</th><th>Pass Rate</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $s): if (!$s['students']) continue; ?>
          <tr>
            <td><strong><?= e($s['code']) ?></strong></td>
            <td><?= e($s['name']) ?></td>
            <td><?= (int)$s['students'] ?></td>
            <td><?= pct($s['avg'] !== null ? (float)$s['avg'] : null) ?></td>
            <td><?= pct($s['lowest'] !== null ? (float)$s['lowest'] : null) ?></td>
            <td><?= pct($s['highest'] !== null ? (float)$s['highest'] : null) ?></td>
            <td><?= pct($s['pass_rate'] !== null ? (float)$s['pass_rate'] : null) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel"><div class="panel-head"><h5>Programme Comparison</h5></div>
    <div class="panel-body p-0">
      <table class="table mb-0">
        <thead><tr><th>Code</th><th>Programme</th><th>Students</th><th>Average</th><th>Pass Rate</th></tr></thead>
        <tbody>
        <?php foreach ($programmes as $p): ?>
          <tr>
            <td><strong><?= e($p['code']) ?></strong></td>
            <td><?= e($p['name']) ?></td>
            <td><?= (int)$p['total'] ?></td>
            <td><?= pct($p['avg'] !== null ? (float)$p['avg'] : null) ?></td>
            <td><?= pct($p['pass_rate'] !== null ? (float)$p['pass_rate'] : null) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
window.APTARS_CHARTS = window.APTARS_CHARTS || {};
<?php if ($trend): ?>
window.APTARS_CHARTS.trendChart = {
  labels: <?= json_encode(array_map(fn($t)=>$t['year_name'].' / '.$t['semester_name'], $trend)) ?>,
  data:   <?= json_encode(array_map(fn($t)=>$t['avg'] !== null ? round((float)$t['avg'],2) : null, $trend)) ?>
};
<?php endif; ?>
<?php if ($gradeDist): ?>
window.APTARS_CHARTS.gradeChart = {
  labels: <?= json_encode(array_keys($gradeDist)) ?>,
  data:   <?= json_encode(array_values($gradeDist)) ?>
};
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>