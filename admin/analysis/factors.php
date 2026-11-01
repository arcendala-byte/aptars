<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/analysis.php';
requireRole('admin');

$pageTitle = 'Factor Analysis';
$activeNav = 'analysis_factors';

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

$factors = db()->query("SELECT * FROM influencing_factors WHERE status='active' ORDER BY name")->fetchAll();

$analyses = [];
foreach ($factors as $f) {
    $a = factorAssociation((int)$f['id'], $semId);
    $analyses[] = ['factor'=>$f, 'analysis'=>$a, 'desc'=>describeCorrelation($a['r'])];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Factor Analysis</h1>
      <p class="muted mb-0">Observed association between learning-environment factors and academic performance.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <div class="alert alert-info py-2 small">
    <strong>Note:</strong> The statistics below describe <em>statistical association</em> between factor ratings and average performance. They do <strong>not</strong> establish causation.
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ($analyses as $idx => $a): $r = $a['analysis']['r']; ?>
      <div class="col-lg-6">
        <div class="panel">
          <div class="panel-head d-flex justify-content-between align-items-center">
            <div><h5 class="mb-0"><?= e($a['factor']['name']) ?></h5>
              <small class="muted">Scale <?= (int)$a['factor']['scale_min'] ?>–<?= (int)$a['factor']['scale_max'] ?> &middot; n=<?= (int)$a['analysis']['n'] ?> students</small></div>
            <span class="badge bg-<?= $r === null ? 'secondary' : (abs($r)>=0.5 ? 'primary' : 'info text-dark') ?>"><?= e($a['desc']) ?></span>
          </div>
          <div class="panel-body">
            <div class="row mb-2">
              <div class="col-4"><small class="muted d-block">Pearson r</small><strong><?= $r === null ? '—' : number_format($r, 3) ?></strong></div>
              <div class="col-4"><small class="muted d-block">Data points</small><strong><?= (int)$a['analysis']['n'] ?></strong></div>
              <div class="col-4"><small class="muted d-block">Direction</small><strong><?= $r === null ? '—' : ($r >= 0 ? 'Positive' : 'Negative') ?></strong></div>
            </div>
            <?php if ($a['analysis']['points']): ?>
              <canvas id="scatter<?= $idx ?>" height="120"></canvas>
            <?php else: ?>
              <p class="empty">Not enough rated students to plot.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel"><div class="panel-head"><h5>Data Table</h5></div>
    <div class="panel-body p-0">
      <table class="table mb-0">
        <thead><tr><th>Factor</th><th>Code</th><th>Ratings Count</th><th>Pearson r</th><th>Interpretation</th></tr></thead>
        <tbody>
        <?php foreach ($analyses as $a): ?>
          <tr>
            <td><?= e($a['factor']['name']) ?></td>
            <td><?= e($a['factor']['code']) ?></td>
            <td><?= (int)$a['analysis']['n'] ?></td>
            <td><?= $a['analysis']['r'] === null ? '—' : number_format($a['analysis']['r'], 3) ?></td>
            <td><?= e($a['desc']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
window.APTARS_CHARTS = window.APTARS_CHARTS || {};
<?php foreach ($analyses as $idx => $a): if (!$a['analysis']['points']) continue; ?>
window.APTARS_CHARTS['scatter<?= $idx ?>'] = <?= json_encode($a['analysis']['points']) ?>;
<?php endforeach; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>