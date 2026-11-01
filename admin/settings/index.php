<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Settings';
$activeNav = 'settings';

$rows = db()->query('SELECT * FROM settings ORDER BY setting_key')->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1>Settings</h1><p class="muted mb-0">System configuration.</p></div>
  <?php displayFlash(); ?>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="panel"><div class="panel-head"><h5>General Configuration</h5></div><div class="panel-body">
        <form method="post" action="save.php" class="row g-3">
          <?= csrfField() ?>
          <?php foreach ($rows as $r): ?>
            <div class="col-12">
              <label class="form-label"><?= e(ucwords(str_replace('_',' ',$r['setting_key']))) ?></label>
              <input type="text" name="settings[<?= e($r['setting_key']) ?>]" class="form-control" value="<?= e($r['setting_value']) ?>">
              <small class="muted"><?= e($r['description']) ?></small>
            </div>
          <?php endforeach; ?>
          <div class="col-12"><button class="btn btn-primary">Save Settings</button></div>
        </form>
      </div></div>
    </div>

    <div class="col-lg-6">
      <div class="panel"><div class="panel-head d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Grading Scale</h5>
        <a href="grading.php" class="btn btn-sm btn-outline-primary">Edit</a>
      </div><div class="panel-body p-0">
        <?php $scales = db()->query('SELECT * FROM grading_scales ORDER BY min_percentage DESC')->fetchAll(); ?>
        <table class="table mb-0">
          <thead><tr><th>Grade</th><th>Range</th><th>Point</th><th>Description</th></tr></thead>
          <tbody>
          <?php foreach ($scales as $s): ?>
            <tr>
              <td><span class="badge bg-primary"><?= e($s['grade']) ?></span></td>
              <td><?= pct((float)$s['min_percentage'], 0) ?> – <?= pct((float)$s['max_percentage'], 0) ?></td>
              <td><?= number_format((float)$s['grade_point'], 2) ?></td>
              <td><?= e($s['description']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>