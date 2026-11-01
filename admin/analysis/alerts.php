<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/analysis.php';
require_once __DIR__ . '/../../includes/notifications.php';
requireRole('admin');

$pageTitle = 'Attention Alerts';
$activeNav = 'analysis_alerts';

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$filter = get('filter', 'all');

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();
$students = db()->query("SELECT id, user_id, registration_number, first_name, last_name FROM students WHERE status='active' ORDER BY registration_number")->fetchAll();

$rows = [];
foreach ($students as $s) {
    $st = attentionStatus((int)$s['id'], $semId);
    if ($st['status'] === 'Normal') continue;
    $rows[] = ['student'=>$s] + $st;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    if (post('action') === 'notify_all') {
        $userIds = [];
        foreach ($rows as $r) if (!empty($r['student']['user_id'])) $userIds[] = (int)$r['student']['user_id'];
        $title = 'Academic attention required';
        $msg = 'Your current performance or attendance may require attention. Please review your academic dashboard and speak with your lecturer.';
        $count = createNotificationBulk($userIds, $title, $msg, 'warning');
        logActivity((int)$_SESSION['user_id'], 'alert.notify', "Notified $count flagged students");
        setFlash('success', "$count student(s) notified.");
        redirect(baseUrl('admin/analysis/alerts.php?semester_id='.$semId.'&filter='.$filter));
    }
}

if ($filter === 'requires') $rows = array_filter($rows, fn($r) => $r['status'] === 'Requires Attention');
if ($filter === 'monitor')  $rows = array_filter($rows, fn($r) => $r['status'] === 'Monitor');

$perfThr = (float)setting('performance_threshold', 50);
$attThr  = (float)setting('attendance_threshold', 75);
$failThr = (int)setting('failed_subjects_threshold', 2);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Attention Alerts</h1>
      <p class="muted mb-0">Students whose performance or attendance may require attention.</p></div>
    <div class="d-flex gap-2 flex-wrap">
      <form method="get" class="d-flex gap-2">
        <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <?= selectOptions($semesters, 'id', 'label', $semId) ?>
        </select>
        <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="all"      <?= $filter==='all'     ?'selected':'' ?>>All flags</option>
          <option value="requires" <?= $filter==='requires'?'selected':'' ?>>Requires Attention</option>
          <option value="monitor"  <?= $filter==='monitor' ?'selected':'' ?>>Monitor</option>
        </select>
      </form>
      <?php if ($rows): ?>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="notify_all">
          <button class="btn btn-sm btn-warning" onclick="return confirm('Send a warning notification to all <?= count($rows) ?> flagged students?');">
            <i class="bi bi-bell"></i> Notify All
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="alert alert-warning py-2 small">
    <strong>Active thresholds:</strong>
    Average &lt; <strong><?= $perfThr ?>%</strong> &middot;
    Attendance &lt; <strong><?= $attThr ?>%</strong> &middot;
    Failed subjects ≥ <strong><?= $failThr ?></strong>.
    Adjust in <a href="<?= e(baseUrl('admin/settings/index.php')) ?>">Settings</a>.
  </div>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($rows): ?>
      <table class="table mb-0 align-middle">
        <thead><tr><th>Reg. No.</th><th>Student</th><th>Average</th><th>Attendance</th><th>Failed</th><th>Status</th><th>Reason</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['student']['registration_number']) ?></strong></td>
            <td><?= e($r['student']['first_name'].' '.$r['student']['last_name']) ?></td>
            <td><?= pct($r['avg']) ?></td>
            <td><?= pct($r['attendance']) ?></td>
            <td><?= (int)$r['failed'] ?></td>
            <td><?= attentionBadge($r['status']) ?></td>
            <td><small class="muted"><?= e(implode(', ', $r['reason'])) ?></small></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= e(baseUrl('reports/student_report.php?student_id='.(int)$r['student']['id'].'&semester_id='.$semId)) ?>">
                <i class="bi bi-file-text"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty p-4 mb-0">No students currently flagged for attention in this semester.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>