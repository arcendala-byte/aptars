<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Departments';
$activeNav = 'departments';

$q      = get('q', '');
$status = get('status', '');

$where = []; $params = [];
if ($q !== '')     { $where[] = '(d.name LIKE ? OR d.code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($status !== ''){ $where[] = 'd.status = ?';                     $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM departments d $whereSql";
$dataSql  = "SELECT d.*,
                    (SELECT COUNT(*) FROM programmes p WHERE p.department_id = d.id) AS programme_count,
                    (SELECT COUNT(*) FROM students   s WHERE s.department_id = d.id) AS student_count
             FROM departments d $whereSql ORDER BY d.name";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 15);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Departments</h1><p class="muted mb-0">Manage academic departments.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Department</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-5"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name or code"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="active"   <?= $status==='active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status==='inactive' ? 'selected' : '' ?>>Inactive</option>
          </select></div>
        <div class="col-md-4"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Code</th><th>Name</th><th>Programmes</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $d): ?>
            <tr>
              <td><strong><?= e($d['code']) ?></strong></td>
              <td><?= e($d['name']) ?></td>
              <td><?= (int)$d['programme_count'] ?></td>
              <td><?= (int)$d['student_count'] ?></td>
              <td><?= statusBadge($d['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$d['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ($d['status'] === 'active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this department?');">
                    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No departments found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>