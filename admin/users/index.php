<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'User Accounts';
$activeNav = 'users';

$q    = get('q', '');
$role = get('role', '');
$status = get('status', '');

$pendingCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();

$where = []; $params = [];
if ($q !== '')      { $where[] = '(u.username LIKE ? OR u.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($role !== '')   { $where[] = 'u.role = ?';   $params[] = $role; }
if ($status !== '') { $where[] = 'u.status = ?'; $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM users u $whereSql";
$dataSql  = "SELECT u.*,
                    (SELECT registration_number FROM students  WHERE user_id = u.id LIMIT 1) AS reg_no,
                    (SELECT CONCAT(first_name,' ',last_name) FROM students WHERE user_id = u.id LIMIT 1) AS student_name,
                    (SELECT CONCAT(first_name,' ',last_name) FROM lecturers WHERE user_id = u.id LIMIT 1) AS lecturer_name,
                    (SELECT staff_number FROM lecturers WHERE user_id = u.id LIMIT 1) AS staff_no
             FROM users u $whereSql
             ORDER BY (u.status='pending') DESC, u.role, u.username";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 20);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>User Accounts</h1>
    <p class="muted mb-0">Manage login credentials, status, and access.</p>
  </div>
  <?php displayFlash(); ?>

  <?php if ($pendingCount > 0): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
      <i class="bi bi-info-circle fs-5"></i>
      <div class="flex-grow-1"><strong><?= $pendingCount ?></strong> account(s) awaiting approval.</div>
      <a href="?status=pending" class="btn btn-sm btn-primary">Review pending</a>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-5"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Username or email"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Role</label>
          <select name="role" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="admin"    <?= $role==='admin'   ?'selected':'' ?>>Admin</option>
            <option value="lecturer" <?= $role==='lecturer'?'selected':'' ?>>Lecturer</option>
            <option value="student"  <?= $role==='student' ?'selected':'' ?>>Student</option>
          </select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="pending"   <?= $status==='pending'  ?'selected':'' ?>>Pending</option>
            <option value="active"    <?= $status==='active'   ?'selected':'' ?>>Active</option>
            <option value="inactive"  <?= $status==='inactive' ?'selected':'' ?>>Inactive</option>
            <option value="suspended" <?= $status==='suspended'?'selected':'' ?>>Suspended</option>
          </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Linked Profile</th><th>Last Login</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $u): $isPending = $u['status'] === 'pending'; ?>
            <tr <?= $isPending ? 'class="table-warning"' : '' ?>>
              <td><strong><?= e($u['username']) ?></strong><?= $isPending ? ' <i class="bi bi-hourglass-split text-warning"></i>' : '' ?></td>
              <td><?= e($u['email']) ?></td>
              <td><span class="badge bg-secondary"><?= e($u['role']) ?></span></td>
              <td>
                <?php if ($u['role'] === 'student' && $u['student_name']): ?>
                  <?= e($u['student_name']) ?><br><small class="muted"><?= e($u['reg_no']) ?></small>
                <?php elseif ($u['role'] === 'lecturer' && $u['lecturer_name']): ?>
                  <?= e($u['lecturer_name']) ?><br><small class="muted"><?= e($u['staff_no']) ?></small>
                <?php else: ?><small class="muted">—</small><?php endif; ?>
              </td>
              <td><small class="muted"><?= $u['last_login'] ? e(date('d M Y H:i', strtotime($u['last_login']))) : 'never' ?></small></td>
              <td><?= $isPending ? '<span class="badge bg-warning text-dark">Pending</span>' : statusBadge($u['status']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No users found.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>