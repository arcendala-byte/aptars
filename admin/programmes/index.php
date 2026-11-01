<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Programmes';
$activeNav = 'programmes';

$q    = get('q', '');
$dept = (int)get('department_id', 0);
$status = get('status', '');

$where = []; $params = [];
if ($q !== '')      { $where[] = '(p.name LIKE ? OR p.code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($dept > 0)      { $where[] = 'p.department_id = ?';              $params[] = $dept; }
if ($status !== '') { $where[] = 'p.status = ?';                     $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM programmes p $whereSql";
$dataSql  = "SELECT p.*, d.name AS dept_name, d.code AS dept_code,
                    (SELECT COUNT(*) FROM students s WHERE s.programme_id = p.id) AS student_count,
                    (SELECT COUNT(*) FROM subjects sub WHERE sub.programme_id = p.id) AS subject_count
             FROM programmes p
             JOIN departments d ON d.id = p.department_id
             $whereSql ORDER BY d.name, p.name";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 15);

$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Programmes</h1><p class="muted mb-0">Manage academic programmes.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Programme</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name or code"></div>
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
          <thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Level</th><th>Students</th><th>Subjects</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $p): ?>
            <tr>
              <td><strong><?= e($p['code']) ?></strong></td>
              <td><?= e($p['name']) ?></td>
              <td><?= e($p['dept_code']) ?></td>
              <td><?= e($p['level']) ?></td>
              <td><?= (int)$p['student_count'] ?></td>
              <td><?= (int)$p['subject_count'] ?></td>
              <td><?= statusBadge($p['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$p['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ($p['status'] === 'active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this programme?');">
                    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No programmes found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>