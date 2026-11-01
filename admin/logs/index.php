<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Activity Log';
$activeNav = 'logs';

$q    = get('q', '');
$role = get('role', '');

$where = []; $params = [];
if ($q !== '') {
    $where[] = '(l.action LIKE ? OR l.description LIKE ? OR u.username LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($role !== '') { $where[] = 'u.role = ?'; $params[] = $role; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM activity_log l LEFT JOIN users u ON u.id = l.user_id $whereSql";
$dataSql  = "SELECT l.*, u.username, u.role FROM activity_log l LEFT JOIN users u ON u.id = l.user_id
             $whereSql ORDER BY l.created_at DESC";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 30);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Activity Log</h1>
    <p class="muted mb-0">Recent user actions recorded in the system.</p>
  </div>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-6"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Action, description, or username"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Role</label>
          <select name="role" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="admin"    <?= $role==='admin'   ?'selected':'' ?>>Admin</option>
            <option value="lecturer" <?= $role==='lecturer'?'selected':'' ?>>Lecturer</option>
            <option value="student"  <?= $role==='student' ?'selected':'' ?>>Student</option>
          </select></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $l): ?>
            <tr>
              <td><small><?= e(date('d M Y H:i', strtotime($l['created_at']))) ?></small></td>
              <td><?= e($l['username'] ?? '—') ?></td>
              <td><?= $l['role'] ? '<span class="badge bg-secondary">'.e($l['role']).'</span>' : '—' ?></td>
              <td><code class="small"><?= e($l['action']) ?></code></td>
              <td><small><?= e($l['description']) ?></small></td>
              <td><small class="muted"><?= e($l['ip_address'] ?? '') ?></small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No activity recorded yet.</p>
      <?php endif; ?>
    </div>
    <?php if ($result['pages'] > 1): ?>
      <div class="panel-body border-top d-flex justify-content-between align-items-center">
        <small class="muted">Showing <?= count($result['rows']) ?> of <?= $result['total'] ?></small>
        <?= paginationLinks($result['page'], $result['pages'], array_filter(['q'=>$q,'role'=>$role])) ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>