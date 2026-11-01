<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Subject Allocation';
$activeNav = 'allocations';

$lect = (int)get('lecturer_id', 0);
$sem  = (int)get('semester_id', 0);

$where = []; $params = [];
if ($lect > 0) { $where[] = 'ls.lecturer_id = ?'; $params[] = $lect; }
if ($sem  > 0) { $where[] = 'ls.semester_id = ?'; $params[] = $sem; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM lecturer_subjects ls $whereSql";
$dataSql  = "SELECT ls.id, l.first_name, l.last_name, l.staff_number,
                    sub.code AS subj_code, sub.name AS subj_name,
                    sem.semester_name, y.year_name
             FROM lecturer_subjects ls
             JOIN lecturers      l   ON l.id   = ls.lecturer_id
             JOIN subjects       sub ON sub.id = ls.subject_id
             JOIN semesters      sem ON sem.id = ls.semester_id
             JOIN academic_years y   ON y.id   = ls.academic_year_id
             $whereSql ORDER BY l.last_name, sem.semester_name, sub.code";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 20);

$lecturers = db()->query("SELECT id, first_name, last_name, staff_number FROM lecturers WHERE status='active' ORDER BY last_name")->fetchAll();
foreach ($lecturers as &$l) { $l['label'] = $l['last_name'] . ', ' . $l['first_name'] . ' (' . $l['staff_number'] . ')'; }
unset($l);

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Subject Allocation</h1><p class="muted mb-0">Assign subjects to lecturers per semester.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Allocation</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label small mb-1">Lecturer</label>
          <select name="lecturer_id" class="form-select form-select-sm">
            <?= selectOptions($lecturers, 'id', 'label', $lect ?: null, 'All') ?>
          </select></div>
        <div class="col-md-4"><label class="form-label small mb-1">Semester</label>
          <select name="semester_id" class="form-select form-select-sm">
            <?= selectOptions($semesters, 'id', 'label', $sem ?: null, 'All') ?>
          </select></div>
        <div class="col-md-4"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Lecturer</th><th>Subject</th><th>Semester</th><th>Academic Year</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $r): ?>
            <tr>
              <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?><br><small class="muted"><?= e($r['staff_number']) ?></small></td>
              <td><strong><?= e($r['subj_code']) ?></strong> — <?= e($r['subj_name']) ?></td>
              <td><?= e($r['semester_name']) ?></td>
              <td><?= e($r['year_name']) ?></td>
              <td class="text-end">
                <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this allocation?');">
                  <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No allocations found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>