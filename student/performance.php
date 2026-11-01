<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/analysis.php';
requireRole('student');

$pageTitle = 'My Performance';
$activeNav = 'performance';

$stud = currentProfile();
if (!$stud) { echo '<p>Student profile not found.</p>'; exit; }
$studId = (int)$stud['id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC")->fetchAll();

$summary = studentPerformanceSummary($studId, $semId);
$attention = attentionStatus($studId, $semId);
$trend = studentTrend($studId);

// Per-subject averages
$perSubject = $summary['per_subject'];

// Class average for comparison
$programmeId = (int)$stud['programme_id'];
$classAvg = null;
try {
    $stmt = db()->prepare("
        SELECT AVG(percentage) FROM results r
        JOIN students s ON s.id = r.student_id
        WHERE s.programme_id = ? AND r.semester_id = ? AND r.status='published'
    ");
    $stmt->execute([$programmeId, $semId]);
    $classAvg = (float)$stmt->fetchColumn();
} catch (Throwable $t) {}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>My Performance</h1>
      <p class="muted mb-0">Your academic standing and progress. Status: <?= attentionBadge($attention['status']) ?></p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <?php if ($attention['status'] !== 'Normal'): ?>
    <div class="alert alert-<?= $attention['status']==='Requires Attention'?'warning':'info' ?> py-2">
      <strong><?= e($attention['status']) ?>:</strong> <?= e(implode(', ', $attention['reason'])) ?>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Average</div><div class="stat-value"><?= pct($summary['avg']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">GPA</div><div class="stat-value"><?= number_format($summary['gpa'],2) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Attendance</div><div class="stat-value"><?= pct($summary['attendance_pct']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Class Average</div><div class="stat-value"><?= pct($classAvg) ?></div></div></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="panel"><div class="panel-head"><h5>My Performance Trend</h5></div>
        <div class="panel-body">
          <?php if (count($trend) >= 2): ?><canvas id="trendChart" height="140"></canvas>
          <?php else: ?><p class="empty mb-0">Not enough semesters of data to plot a trend.</p><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel"><div class="panel-head"><h5>Subject Comparison</h5></div>
        <div class="panel-body">
          <?php if ($perSubject): ?><canvas id="subjectChart" height="140"></canvas>
          <?php else: ?><p class="empty mb-0">No subject data yet.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="panel"><div class="panel-head"><h5>Subject Averages</h5></div>
    <div class="panel-body p-0">
      <?php if ($perSubject): ?>
        <table class="table mb-0">
          <thead><tr><th>Code</th><th>Subject</th><th>Assessments</th><th>Average</th><th>Grade</th></tr></thead>
          <tbody>
          <?php foreach ($perSubject as $s): ?>
            <tr>
              <td><strong><?= e($s['code']) ?></strong></td>
              <td><?= e($s['name']) ?></td>
              <td><?= (int)$s['assessment_count'] ?></td>
              <td><?= pct((float)$s['avg_pct']) ?></td>
              <td><span class="badge bg-primary"><?= e(calculateGrade((float)$s['avg_pct'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No results yet.</p>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
window.APTARS_CHARTS = window.APTARS_CHARTS || {};
<?php if (count($trend) >= 2): ?>
window.APTARS_CHARTS.trendChart = {
  labels: <?= json_encode(array_column($trend, 'label')) ?>,
  data:   <?= json_encode(array_column($trend, 'avg')) ?>
};
<?php endif; ?>
<?php if ($perSubject): ?>
window.APTARS_CHARTS.subjectChart = {
  labels: <?= json_encode(array_column($perSubject, 'code')) ?>,
  data:   <?= json_encode(array_map(fn($s)=>round((float)$s['avg_pct'],2), $perSubject)) ?>
};
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>