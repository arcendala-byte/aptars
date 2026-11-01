<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Lecturers';
$activeNav = 'lecturers';

$q    = get('q', '');
$dept = (int)get('department_id', 0);
$status = get('status', '');

$where = []; $params = [];
if ($q !== '')      { $where[] = '(l.first_name LIKE ? OR l.last_name LIKE ? OR l.staff_number LIKE ?)';
                      $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($dept > 0)      { $where[] = 'l.department_id = ?'; $params[] = $dept; }
if ($status !== '') { $where[] = 'l.status = ?';        $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM lecturers l $whereSql";
$dataSql  = "SELECT l.*, d.code AS dept_code, u.username
             FROM lecturers l
             JOIN departments d ON d.id = l.department_id
             LEFT JOIN users u ON u.id = l.user_id
             $whereSql ORDER BY l.last_name, l.first_name";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 15);

$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Lecturers</h1><p class="muted mb-0">Manage lecturers and their user accounts.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Lecturer</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name or staff no."></div>
        <div class="col-md-3"><label class="form-label small mb-1">Department</label>
          <select name="department_id" class="form-select form-select-sm">
            <?= selectOptions($departments, 'id', 'name', $dept ?: null, 'All') ?>
          </select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="active"   <?= $status==='active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status==='inactive' ? 'selected' : '' ?>>Inactive</option>
          </select></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Staff No.</th><th>Name</th><th>Department</th><th>Email</th><th>Username</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $l): ?>
            <tr>
              <td><strong><?= e($l['staff_number']) ?></strong></td>
              <td><?= e($l['first_name'] . ' ' . $l['last_name']) ?></td>
              <td><?= e($l['dept_code']) ?></td>
              <td><?= e($l['email']) ?></td>
              <td><?= e($l['username'] ?? '-') ?></td>
              <td><?= statusBadge($l['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$l['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ($l['status'] === 'active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this lecturer?');">
                    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No lecturers found. <a href="form.php">Add the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>