<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Influencing Factors';
$activeNav = 'factors';

$factors = db()->query("
    SELECT f.*,
           (SELECT COUNT(*) FROM student_factors sf WHERE sf.factor_id = f.id) AS rating_count
    FROM influencing_factors f ORDER BY f.name
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Influencing Factors</h1>
      <p class="muted mb-0">Learning-environment factors used to analyse academic performance.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Factor</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($factors): ?>
      <table class="table mb-0 align-middle">
        <thead><tr><th>Code</th><th>Name</th><th>Scale</th><th>Ratings</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($factors as $f): ?>
          <tr>
            <td><strong><?= e($f['code']) ?></strong></td>
            <td><?= e($f['name']) ?><br><small class="muted"><?= e($f['description']) ?></small></td>
            <td><?= (int)$f['scale_min'] ?> – <?= (int)$f['scale_max'] ?></td>
            <td><?= (int)$f['rating_count'] ?></td>
            <td><?= statusBadge($f['status']) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$f['id'] ?>"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-sm btn-outline-success" href="rate.php?factor_id=<?= (int)$f['id'] ?>"><i class="bi bi-star"></i> Rate</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty p-4 mb-0">No factors defined yet. <a href="form.php">Create the first one</a>.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>