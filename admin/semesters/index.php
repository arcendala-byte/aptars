<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Semesters';
$activeNav = 'semesters';

$year   = (int)get('academic_year_id', 0);
$status = get('status', '');

$where = []; $params = [];
if ($year > 0)      { $where[] = 's.academic_year_id = ?'; $params[] = $year; }
if ($status !== '') { $where[] = 's.status = ?';           $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM semesters s $whereSql";
$dataSql  = "SELECT s.*, y.year_name FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id
             $whereSql ORDER BY y.start_date DESC, s.start_date";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 15);

$years = db()->query("SELECT id, year_name FROM academic_years ORDER BY start_date DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Semesters</h1><p class="muted mb-0">Manage semesters within academic years.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Semester</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label small mb-1">Academic Year</label>
          <select name="academic_year_id" class="form-select form-select-sm">
            <?= selectOptions($years, 'id', 'year_name', $year ?: null, 'All') ?>
          </select></div>
        <div class="col-md-3"><label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="active"   <?= $status==='active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status==='inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="closed"   <?= $status==='closed'   ? 'selected' : '' ?>>Closed</option>
          </select></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Year</th><th>Semester</th><th>Start</th><th>End</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $s): ?>
            <tr>
              <td><strong><?= e($s['year_name']) ?></strong></td>
              <td><?= e($s['semester_name']) ?></td>
              <td><?= e($s['start_date']) ?></td>
              <td><?= e($s['end_date']) ?></td>
              <td><?= statusBadge($s['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$s['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ($s['status'] === 'active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this semester?');">
                    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No semesters found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>