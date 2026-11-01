<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('admin');

$pageTitle = 'Results';
$activeNav = 'results';

$subjectId = (int)get('subject_id', 0);
$semId     = (int)get('semester_id', 0);

$where = []; $params = [];
if ($subjectId > 0) { $where[] = 'a.subject_id = ?';  $params[] = $subjectId; }
if ($semId > 0)     { $where[] = 'a.semester_id = ?'; $params[] = $semId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM assessments a $whereSql";
$dataSql  = "SELECT a.*, sub.code AS subj_code, sub.name AS subj_name,
                    sem.semester_name, y.year_name
             FROM assessments a
             JOIN subjects  sub ON sub.id = a.subject_id
             JOIN semesters sem ON sem.id = a.semester_id
             JOIN academic_years y ON y.id = sem.academic_year_id
             $whereSql ORDER BY y.start_date DESC, sub.code, a.id";
$result = paginate($countSql, $dataSql, $params, (int)get('page', 1), 20);

// Progress for each row
$assessments = $result['rows'];
$progress = [];
foreach ($assessments as $a) $progress[$a['id']] = assessmentProgress((int)$a['id']);

// Recently entered results
$recent = db()->query("
    SELECT r.id, r.marks, r.max_marks, r.percentage, r.grade, r.created_at,
           s.registration_number, s.first_name, s.last_name, sub.code AS subj_code, a.name AS ass_name
    FROM results r
    JOIN students  s   ON s.id = r.student_id
    JOIN subjects  sub ON sub.id = r.subject_id
    JOIN assessments a ON a.id = r.assessment_id
    ORDER BY r.created_at DESC LIMIT 10
")->fetchAll();

$subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id ORDER BY y.start_date DESC, s.start_date")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Results</h1><p class="muted mb-0">Enter and review academic marks.</p></div>
    <a href="<?= e(baseUrl('admin/assessments/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-ui-checks"></i> Manage Assessments</a>
  </div>

  <?php displayFlash(); ?>

  <div class="panel mb-4">
    <div class="panel-head"><h5>Select an Assessment to Enter Marks</h5></div>
    <div class="panel-body p-0">
      <form method="get" class="p-3 border-bottom row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label small mb-1">Subject</label>
          <select name="subject_id" class="form-select form-select-sm"><?= selectOptions($subjects, 'id', 'code', $subjectId ?: null, 'All subjects') ?></select></div>
        <div class="col-md-5"><label class="form-label small mb-1">Semester</label>
          <select name="semester_id" class="form-select form-select-sm"><?= selectOptions($semesters, 'id', 'label', $semId ?: null, 'All semesters') ?></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Filter</button></div>
      </form>
      <?php if ($assessments): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Subject</th><th>Assessment</th><th>Semester</th><th>Graded / Enrolled</th><th>Average</th><th>Pass Rate</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($assessments as $a):
              $p = $progress[$a['id']];
          ?>
            <tr>
              <td><strong><?= e($a['subj_code']) ?></strong> — <?= e($a['subj_name']) ?></td>
              <td><?= e($a['name']) ?> <span class="badge bg-secondary"><?= e($a['type']) ?></span></td>
              <td><?= e($a['year_name']) ?> / <?= e($a['semester_name']) ?></td>
              <td><?= $p['graded'] ?> / <?= $p['students'] ?></td>
              <td><?= pct($p['avg']) ?></td>
              <td><?= pct($p['pass_rate']) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-primary" href="enter.php?id=<?= (int)$a['id'] ?>"><i class="bi bi-pencil-square"></i> Enter Marks</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No assessments match the filters.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h5>Recently Entered Results</h5></div>
    <div class="panel-body p-0">
      <?php if ($recent): ?>
        <table class="table mb-0">
          <thead><tr><th>Student</th><th>Subject</th><th>Assessment</th><th>Marks</th><th>%</th><th>Grade</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><?= e($r['registration_number']) ?><br><small class="muted"><?= e($r['first_name'].' '.$r['last_name']) ?></small></td>
              <td><?= e($r['subj_code']) ?></td>
              <td><?= e($r['ass_name']) ?></td>
              <td><?= (float)$r['marks'] ?> / <?= (float)$r['max_marks'] ?></td>
              <td><?= pct((float)$r['percentage']) ?></td>
              <td><span class="badge bg-primary"><?= e($r['grade']) ?></span></td>
              <td><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No results entered yet.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>