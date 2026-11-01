<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/analysis.php';
requireRole('lecturer');

$pageTitle = 'Class Performance';
$activeNav = 'analysis_perf';

$lect = currentProfile();
if (!$lect) { echo '<p>Lecturer profile not found.</p>'; exit; }
$lectId = (int)$lect['id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));

$stmt = db()->prepare("
    SELECT DISTINCT sub.id, sub.code, sub.name
    FROM lecturer_subjects ls
    JOIN subjects sub ON sub.id = ls.subject_id
    WHERE ls.lecturer_id = ? AND ls.semester_id = ?
    ORDER BY sub.code
");
$stmt->execute([$lectId, $semId]);
$subjects = $stmt->fetchAll();

$rows = [];
foreach ($subjects as $s) {
    $rows[] = ['subject'=>$s] + subjectPerformance((int)$s['id'], $semId);
}

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Class Performance</h1><p class="muted mb-0">Performance summary across your assigned subjects.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ($rows as $idx => $r): ?>
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-head"><h5><?= e($r['subject']['code']) ?> — <?= e($r['subject']['name']) ?></h5></div>
          <div class="panel-body">
            <div class="row mb-3">
              <div class="col-6 col-md-3"><small class="muted d-block">Students</small><strong><?= (int)$r['students'] ?></strong></div>
              <div class="col-6 col-md-3"><small class="muted d-block">Average</small><strong><?= pct($r['avg']) ?></strong></div>
              <div class="col-6 col-md-3"><small class="muted d-block">Pass Rate</small><strong><?= pct($r['pass_rate']) ?></strong></div>
              <div class="col-6 col-md-3"><small class="muted d-block">Range</small><strong><?= number_format($r['lowest'],1) ?>–<?= number_format($r['highest'],1) ?>%</strong></div>
            </div>
            <?php if ($r['grade_distribution']): ?>
              <canvas id="gd<?= $idx ?>" height="90"></canvas>
            <?php else: ?>
              <p class="empty mb-0">No results yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <div class="col-12"><p class="empty">No subjects assigned for this semester.</p></div>
    <?php endif; ?>
  </div>
</main>

<script>
window.APTARS_CHARTS = window.APTARS_CHARTS || {};
<?php foreach ($rows as $idx => $r): if (!$r['grade_distribution']) continue; ?>
window.APTARS_CHARTS['grade<?= $idx ?>'] = {
  labels: <?= json_encode(array_keys($r['grade_distribution'])) ?>,
  data:   <?= json_encode(array_values($r['grade_distribution'])) ?>
};
<?php endforeach; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>