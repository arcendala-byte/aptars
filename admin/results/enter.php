<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('admin');

$id = (int)(post('assessment_id') ?? get('id', 0));
if ($id <= 0) { setFlash('danger','Invalid assessment.'); redirect(baseUrl('admin/results/index.php')); }

$stmt = db()->prepare("
    SELECT a.*, sub.code AS subj_code, sub.name AS subj_name, sem.semester_name, y.year_name
    FROM assessments a
    JOIN subjects  sub ON sub.id = a.subject_id
    JOIN semesters sem ON sem.id = a.semester_id
    JOIN academic_years y ON y.id = sem.academic_year_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) { setFlash('danger','Assessment not found.'); redirect(baseUrl('admin/results/index.php')); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $result = saveResultMarks($id, (array)($_POST['marks'] ?? []), (int)$_SESSION['user_id']);
    if ($result['errors']) {
        $errors = $result['errors'];
    } else {
        setFlash('success', $result['saved'] . ' mark(s) saved.');
        redirect(baseUrl('admin/results/enter.php?id=' . $id));
    }
}

// Load students + existing marks (repopulate with posted values on error)
$stmt = db()->prepare("
    SELECT s.id, s.registration_number, s.first_name, s.last_name,
           r.marks AS existing_marks, r.grade, r.percentage
    FROM student_subjects ss
    JOIN students s ON s.id = ss.student_id
    LEFT JOIN results r ON r.student_id = s.id AND r.assessment_id = ? AND r.semester_id = ?
    WHERE ss.subject_id = ? AND ss.semester_id = ? AND ss.status='registered'
    ORDER BY s.registration_number
");
$stmt->execute([$id, $a['semester_id'], $a['subject_id'], $a['semester_id']]);
$students = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedMarks = (array)($_POST['marks'] ?? []);
    foreach ($students as &$s) {
        if (array_key_exists((int)$s['id'], $postedMarks)) $s['existing_marks'] = $postedMarks[(int)$s['id']];
    }
    unset($s);
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Enter Marks — <?= e($a['name']) ?></h1>
    <p class="muted mb-0">
      <?= e($a['subj_code']) ?> — <?= e($a['subj_name']) ?>
      &middot; <?= e($a['year_name']) ?> / <?= e($a['semester_name']) ?>
      &middot; Max <?= (float)$a['max_marks'] ?> marks
      &middot; <a href="index.php">&larr; back to results</a>
    </p>
  </div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body p-0">
    <?php if (!$students): ?>
      <p class="empty p-4 mb-0">No students are registered for this subject/semester.</p>
    <?php else: ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="assessment_id" value="<?= (int)$a['id'] ?>">
        <table class="table mb-0 align-middle">
          <thead><tr><th>Reg. No.</th><th>Name</th><th style="width:180px">Marks (max <?= (float)$a['max_marks'] ?>)</th><th>Current Grade</th></tr></thead>
          <tbody>
          <?php foreach ($students as $s): ?>
            <tr>
              <td><strong><?= e($s['registration_number']) ?></strong></td>
              <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
              <td>
                <input type="number" step="0.01" min="0" max="<?= (float)$a['max_marks'] ?>"
                       name="marks[<?= (int)$s['id'] ?>]"
                       class="form-control form-control-sm"
                       value="<?= $s['existing_marks'] !== null ? e((string)$s['existing_marks']) : '' ?>"
                       placeholder="leave blank to skip">
              </td>
              <td>
                <?php if ($s['grade']): ?>
                  <span class="badge bg-primary"><?= e($s['grade']) ?></span>
                  <small class="muted ms-1"><?= pct((float)$s['percentage']) ?></small>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="p-3 border-top">
          <button class="btn btn-primary"><i class="bi bi-save"></i> Save Marks</button>
          <a href="index.php" class="btn btn-link">Cancel</a>
          <small class="muted ms-3">Blank fields are skipped (no result saved for that student).</small>
        </div>
      </form>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>