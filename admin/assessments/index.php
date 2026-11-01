<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('admin');

$pageTitle = 'Assessments';
$activeNav = 'assessments';

$subjectId = (int)get('subject_id', 0);
$semId     = (int)get('semester_id', 0);
$type      = get('type', '');

$where = []; $params = [];
if ($subjectId > 0) { $where[] = 'a.subject_id = ?';  $params[] = $subjectId; }
if ($semId > 0)     { $where[] = 'a.semester_id = ?'; $params[] = $semId; }
if ($type !== '')   { $where[] = 'a.type = ?';        $params[] = $type; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM assessments a $whereSql";
$dataSql  = "SELECT a.*, sub.code AS subj_code, sub.name AS subj_name,
                    sem.semester_name, y.year_name,
                    (SELECT COUNT(*) FROM results r WHERE r.assessment_id = a.id) AS graded
             FROM assessments a
             JOIN subjects  sub ON sub.id = a.subject_id
             JOIN semesters sem ON sem.id = a.semester_id
             JOIN academic_years y ON y.id = sem.academic_year_id
             $whereSql ORDER BY y.start_date DESC, sub.code, a.id";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 20);

$subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Assessments</h1><p class="muted mb-0">CATs, assignments, practicals, and exams per subject/semester.</p></div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Assessment</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel">
    <div class="panel-head">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-md-4"><label class="form-label small mb-1">Subject</label>
          <select name="subject_id" class="form-select form-select-sm"><?= selectOptions($subjects, 'id', 'code', $subjectId ?: null, 'All') ?></select></div>
        <div class="col-md-4"><label class="form-label small mb-1">Semester</label>
          <select name="semester_id" class="form-select form-select-sm"><?= selectOptions($semesters, 'id', 'label', $semId ?: null, 'All') ?></select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Type</label>
          <select name="type" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach (['CAT','Assignment','Practical','Exam','Other'] as $t): ?>
              <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php" class="btn btn-sm btn-link">Reset</a></div>
      </form>
    </div>
    <div class="panel-body p-0">
      <?php if ($result['rows']): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Subject</th><th>Semester</th><th>Name</th><th>Type</th><th>Max</th><th>Graded</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $a): ?>
            <tr>
              <td><strong><?= e($a['subj_code']) ?></strong> — <?= e($a['subj_name']) ?></td>
              <td><?= e($a['year_name']) ?> / <?= e($a['semester_name']) ?></td>
              <td><?= e($a['name']) ?></td>
              <td><span class="badge bg-secondary"><?= e($a['type']) ?></span></td>
              <td><?= (float)$a['max_marks'] ?></td>
              <td><?= (int)$a['graded'] ?></td>
              <td>
                <a class="btn btn-sm btn-outline-primary" href="form.php?id=<?= (int)$a['id'] ?>"><i class="bi bi-pencil"></i></a>
                <a class="btn btn-sm btn-outline-success" href="<?= e(baseUrl('admin/results/enter.php?id='.(int)$a['id'])) ?>"><i class="bi bi-pencil-square"></i> Enter Marks</a>
                <?php if ($a['status']==='active'): ?>
                  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Archive this assessment?');">
                    <?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No assessments found. <a href="form.php">Create the first one</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>