<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Payments';
$activeNav = 'payments';

$q      = get('q', '');
$status = get('status', '');

$where = []; $params = [];
if ($q !== '') {
    $where[] = "(s.registration_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR p.mpesa_receipt_number LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($status !== '') { $where[] = 'p.status = ?'; $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM payments p JOIN students s ON s.id = p.student_id $whereSql";
$dataSql  = "SELECT p.*, s.registration_number, s.first_name, s.last_name, sem.semester_name, y.year_name
             FROM payments p
             JOIN students s  ON s.id = p.student_id
             JOIN semesters sem ON sem.id = p.semester_id
             JOIN academic_years y ON y.id = sem.academic_year_id
             $whereSql
             ORDER BY p.created_at DESC";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 25);

$sum = db()->query("
    SELECT
      COALESCE(SUM(CASE WHEN status='success' THEN amount ELSE 0 END),0) AS collected,
      COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) AS pending,
      COUNT(*) AS total
    FROM payments
")->fetch();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Payments</h1>
    <p class="muted mb-0">M-Pesa payment records across the institution.</p>
  </div>

  <?php displayFlash(); ?>

  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Total Collected</div><div class="stat-value">KES <?= number_format((float)$sum['collected'], 0) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value">KES <?= number_format((float)$sum['pending'], 0) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?= (int)$sum['total'] ?></div></div></div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-6"><label class="form-label small mb-1">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Student, reg. no., or receipt"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="success"   <?= $status==='success'   ?'selected':'' ?>>Success</option>
            <option value="pending"   <?= $status==='pending'   ?'selected':'' ?>>Pending</option>
            <option value="failed"    <?= $status==='failed'    ?'selected':'' ?>>Failed</option>
            <option value="cancelled" <?= $status==='cancelled' ?'selected':'' ?>>Cancelled</option>
          </select></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Date</th><th>Student</th><th>Semester</th><th>Amount</th><th>Phone</th><th>Receipt</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $p): ?>
            <tr>
              <td><small><?= e(date('d M Y H:i', strtotime($p['created_at']))) ?></small></td>
              <td><strong><?= e($p['registration_number']) ?></strong><br><small class="muted"><?= e($p['first_name'].' '.$p['last_name']) ?></small></td>
              <td><small><?= e($p['year_name']) ?> / <?= e($p['semester_name']) ?></small></td>
              <td>KES <?= number_format((float)$p['amount'], 2) ?></td>
              <td><?= e($p['phone']) ?></td>
              <td><?= e($p['mpesa_receipt_number'] ?? '—') ?></td>
              <td>
                <?php if ($p['status'] === 'success'): ?><span class="badge bg-success">Success</span>
                <?php elseif ($p['status'] === 'pending'): ?><span class="badge bg-warning text-dark">Pending</span>
                <?php elseif ($p['status'] === 'failed'): ?><span class="badge bg-danger">Failed</span>
                <?php else: ?><span class="badge bg-secondary"><?= e(ucfirst($p['status'])) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No payments recorded yet.</p>
      <?php endif; ?>
    </div>
    <?php if ($result['pages'] > 1): ?>
      <div class="panel-body border-top d-flex justify-content-between align-items-center">
        <small class="muted">Showing <?= count($result['rows']) ?> of <?= $result['total'] ?></small>
        <?= paginationLinks($result['page'], $result['pages'], array_filter(['q'=>$q,'status'=>$status])) ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>