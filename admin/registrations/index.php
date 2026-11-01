<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Student Registration';
$activeNav = 'registrations';

$stud = (int)get('student_id', 0);
$sem  = (int)get('semester_id', 0);

$where = []; $params = [];
if ($stud > 0) { $where[] = 'ss.student_id = ?'; $params[] = $stud; }
if ($sem  > 0) { $where[] = 'ss.semester_id = ?'; $params[] = $sem; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM student_subjects ss $whereSql";
$dataSql  = "SELECT ss.id, ss.registration_date, ss.status,
                    s.registration_number, s.first_name, s.last_name,
                    sub.code AS subj_code, sub.name AS subj_name,
                    sem.semester_name, y.year_name
             FROM student_subjects ss
             JOIN students       s   ON s.id   = ss.student_id
             JOIN subjects       sub ON sub.id = ss.subject_id
             JOIN semesters      sem ON sem.id = ss.semester_id
             JOIN academic_years y   ON y.id   = sem.academic_year_id
             $whereSql ORDER BY s.registration_number, sem.semester_name, sub.code";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 25);

$students = db()->query("SELECT id, registration_number, first_name, last_name FROM students WHERE status='active' ORDER BY registration_number")->fetchAll();
foreach ($students as &$s) { $s['label'] = $s['registration_number'] . ' — ' . $s['first_name'] . ' ' . $s['last_name']; }
unset($s);

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Student Registration</h1><p class="muted mb-0">Register students for subjects per semester.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Register Student</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-5"><label class="form-label small mb-1">Student</label>
          <select name="student_id" class="form-select form-select-sm">
            <?= selectOptions($students, 'id', 'label', $stud ?: null, 'All') ?>
          </select></div>
        <div class="col-md-4"><label class="form-label small mb-1">Semester</label>
          <select name="semester_id" class="form-select form-select-sm">
            <?= selectOptions($semesters, 'id', 'label', $sem ?: null, 'All') ?>
          </select></div>
        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Student</th><th>Subject</th><th>Semester</th><th>Reg. Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $r): ?>
            <tr>
              <td><strong><?= e($r['registration_number']) ?></strong><br><small class="muted"><?= e($r['first_name'].' '.$r['last_name']) ?></small></td>
              <td><strong><?= e($r['subj_code']) ?></strong> — <?= e($r['subj_name']) ?></td>
              <td><?= e($r['year_name']) ?> / <?= e($r['semester_name']) ?></td>
              <td><?= e($r['registration_date']) ?></td>
              <td><?= statusBadge($r['status']) ?></td>
              <td class="text-end">
                <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this registration?');">
                  <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No registrations found. <a href="form.php">Register a student</a>.</p>
      <?php endif; ?>
    </div>
    <?php if ($result['pages'] > 1): ?>
      <div class="panel-body border-top d-flex justify-content-between align-items-center">
        <small class="muted">Showing <?= count($result['rows']) ?> of <?= $result['total'] ?></small>
        <?= paginationLinks($result['page'], $result['pages'], array_filter(['student_id'=>$stud ?: null,'semester_id'=>$sem ?: null])) ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>