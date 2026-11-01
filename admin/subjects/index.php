<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Subjects';
$activeNav = 'subjects';

$q    = get('q', '');
$prog = (int)get('programme_id', 0);
$sem  = get('semester', '');
$status = get('status', '');

$where = []; $params = [];
if ($q !== '')      { $where[] = '(s.name LIKE ? OR s.code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($prog > 0)      { $where[] = 's.programme_id = ?'; $params[] = $prog; }
if ($sem !== '')    { $where[] = 's.semester = ?';     $params[] = $sem; }
if ($status !== '') { $where[] = 's.status = ?';       $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM subjects s $whereSql";
$dataSql  = "SELECT s.*, p.code AS prog_code, d.code AS dept_code
             FROM subjects s
             JOIN programmes  p ON p.id = s.programme_id
             JOIN departments d ON d.id = s.department_id
             $whereSql ORDER BY s.code";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 20);

$programmes = db()->query("SELECT id, name, code FROM programmes WHERE status='active' ORDER BY code")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Subjects</h1><p class="muted mb-0">Manage subjects / courses.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Subject</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Name or code"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Programme</label>
          <select name="programme_id" class="form-select form-select-sm">
            <?= selectOptions($programmes, 'id', 'code', $prog ?: null, 'All') ?>
          </select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Semester</label>
          <select name="semester" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach (['1','2','3'] as $s): ?>
              <option value="<?= $s ?>" <?= $sem===$s?'selected':'' ?>>Sem <?= $s ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Code</th><th>Name</th><th>Programme</th><th>Sem</th><th>Credits</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $s): ?>
            <tr>
              <td><strong><?= e($s['code']) ?></strong></td>
              <td><?= e($s['name']) ?></td>
              <td><?= e($s['prog_code']) ?></td>
              <td>Sem <?= e($s['semester']) ?></td>
              <td><?= (int)$s['credit_hours'] ?></td>
              <td><?= statusBadge($s['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$s['id'] ?>"><i class="bi bi-pencil"></i></a>
                <?php if ($s['status'] === 'active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this subject?');">
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
        <p class="empty p-4 mb-0">No subjects found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>