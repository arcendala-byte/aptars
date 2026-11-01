<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Academic Years';
$activeNav = 'academic_years';

$q = get('q', '');
$where = []; $params = [];
if ($q !== '') { $where[] = 'year_name LIKE ?'; $params[] = "%$q%"; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM academic_years $whereSql";
$dataSql  = "SELECT y.*, (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id = y.id) AS semester_count
             FROM academic_years y $whereSql ORDER BY y.start_date DESC";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 15);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Academic Years</h1><p class="muted mb-0">Manage academic years.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Academic Year</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-5"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="e.g. 2024/2025"></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Year</th><th>Start</th><th>End</th><th>Semesters</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $y): ?>
            <tr>
              <td><strong><?= e($y['year_name']) ?></strong></td>
              <td><?= e($y['start_date']) ?></td>
              <td><?= e($y['end_date']) ?></td>
              <td><?= (int)$y['semester_count'] ?></td>
              <td><?= statusBadge($y['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$y['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ($y['status'] === 'active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this academic year?');">
                    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$y['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No academic years found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>