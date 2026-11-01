<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'New Subject Allocation';
$activeNav = 'allocations';

$errors = [];
$data = ['lecturer_id'=>'', 'subject_id'=>'', 'semester_id'=>''];

$lecturers = db()->query("SELECT id, first_name, last_name, staff_number FROM lecturers WHERE status='active' ORDER BY last_name")->fetchAll();
foreach ($lecturers as &$l) { $l['label'] = $l['last_name'] . ', ' . $l['first_name'] . ' (' . $l['staff_number'] . ')'; }
unset($l);

$subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
foreach ($subjects as &$s) { $s['label'] = $s['code'] . ' — ' . $s['name']; }
unset($s);

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id WHERE s.status IN ('active','closed') ORDER BY y.start_date DESC, s.start_date")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['lecturer_id'] = (int)post('lecturer_id', 0);
    $data['subject_id']  = (int)post('subject_id', 0);
    $data['semester_id'] = (int)post('semester_id', 0);

    if ($data['lecturer_id'] <= 0) $errors[] = 'Lecturer is required.';
    if ($data['subject_id']  <= 0) $errors[] = 'Subject is required.';
    if ($data['semester_id'] <= 0) $errors[] = 'Semester is required.';

    $academicYearId = 0;
    if (!$errors) {
        $stmt = db()->prepare('SELECT academic_year_id FROM semesters WHERE id = ?');
        $stmt->execute([$data['semester_id']]);
        $academicYearId = (int)$stmt->fetchColumn();
        if ($academicYearId === 0) $errors[] = 'Selected semester not found.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM lecturer_subjects WHERE lecturer_id=? AND subject_id=? AND semester_id=?');
        $stmt->execute([$data['lecturer_id'], $data['subject_id'], $data['semester_id']]);
        if ($stmt->fetch()) $errors[] = 'This lecturer is already allocated to this subject for that semester.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO lecturer_subjects (lecturer_id, subject_id, semester_id, academic_year_id) VALUES (?,?,?,?)');
        $stmt->execute([$data['lecturer_id'], $data['subject_id'], $data['semester_id'], $academicYearId]);
        logActivity((int)$_SESSION['user_id'], 'allocation.create', "Allocated subject {$data['subject_id']} to lecturer {$data['lecturer_id']}");
        setFlash('success', 'Allocation created.');
        redirect(baseUrl('admin/allocations/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Allocations</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <div class="col-md-6"><label class="form-label">Lecturer <span class="text-danger">*</span></label>
        <select name="lecturer_id" class="form-select" required>
          <?= selectOptions($lecturers, 'id', 'label', $data['lecturer_id'], 'Select lecturer') ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">Subject <span class="text-danger">*</span></label>
        <select name="subject_id" class="form-select" required>
          <?= selectOptions($subjects, 'id', 'label', $data['subject_id'], 'Select subject') ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">Semester <span class="text-danger">*</span></label>
        <select name="semester_id" class="form-select" required>
          <?= selectOptions($semesters, 'id', 'label', $data['semester_id'], 'Select semester') ?>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary">Create Allocation</button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>