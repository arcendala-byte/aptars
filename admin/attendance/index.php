<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('admin');

$pageTitle = 'Attendance';
$activeNav = 'attendance';

$subjectId = (int)get('subject_id', 0);
$semId     = (int)get('semester_id', 0);

$subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

$where = []; $params = [];
if ($subjectId > 0) { $where[] = 'a.subject_id = ?';  $params[] = $subjectId; }
if ($semId > 0)     { $where[] = 'a.semester_id = ?'; $params[] = $semId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Group attendance by (subject_id, semester_id, date)
$countSql = "SELECT COUNT(*) FROM (
    SELECT 1 FROM attendance a $whereSql GROUP BY a.subject_id, a.semester_id, a.attendance_date
) x";
$dataSql  = "SELECT a.subject_id, a.semester_id, a.attendance_date,
                    sub.code AS subj_code, sub.name AS subj_name,
                    sem.semester_name, y.year_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN a.status IN ('Present','Late') THEN 1 ELSE 0 END) AS attended
             FROM attendance a
             JOIN subjects  sub ON sub.id = a.subject_id
             JOIN semesters sem ON sem.id = a.semester_id
             JOIN academic_years y ON y.id = sem.academic_year_id
             $whereSql
             GROUP BY a.subject_id, a.semester_id, a.attendance_date, sub.code, sub.name, sem.semester_name, y.year_name
             ORDER BY a.attendance_date DESC";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 20);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Attendance</h1><p class="muted mb-0">Record and review class attendance sessions.</p></div>
    <a href="record.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Record a Session</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-5"><label class="form-label small mb-1">Subject</label>
          <select name="subject_id" class="form-select form-select-sm"><?= selectOptions($subjects, 'id', 'code', $subjectId ?: null, 'All') ?></select></div>
        <div class="col-md-5"><label class="form-label small mb-1">Semester</label>
          <select name="semester_id" class="form-select form-select-sm"><?= selectOptions($semesters, 'id', 'label', $semId ?: null, 'All') ?></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Date</th><th>Subject</th><th>Semester</th><th>Attended / Total</th><th>Attendance %</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $r):
              $pct = $r['total'] > 0 ? ($r['attended']/$r['total']*100) : 0;
          ?>
            <tr>
              <td><strong><?= e($r['attendance_date']) ?></strong></td>
              <td><?= e($r['subj_code']) ?> — <?= e($r['subj_name']) ?></td>
              <td><?= e($r['year_name']) ?> / <?= e($r['semester_name']) ?></td>
              <td><?= (int)$r['attended'] ?> / <?= (int)$r['total'] ?></td>
              <td><?= pct($pct) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="record.php?subject_id=<?= (int)$r['subject_id'] ?>&semester_id=<?= (int)$r['semester_id'] ?>&date=<?= e($r['attendance_date']) ?>">
                  <i class="bi bi-pencil"></i> Open
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No attendance records yet. <a href="record.php">Record the first session</a>.</p>
      <?php endif; ?>
    </div>
    <?php if ($result['pages'] > 1): ?>
      <div class="panel-body border-top d-flex justify-content-between align-items-center">
        <small class="muted">Showing <?= count($result['rows']) ?> of <?= $result['total'] ?></small>
        <?= paginationLinks($result['page'], $result['pages'], array_filter(['subject_id'=>$subjectId ?: null,'semester_id'=>$semId ?: null])) ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>