<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Assessment' : 'New Assessment';
$activeNav = 'assessments';

$errors = [];
$data = ['subject_id'=>'','semester_id'=>'','name'=>'','type'=>'CAT','max_marks'=>100,'weight'=>0,'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM assessments WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Assessment not found.'); redirect(baseUrl('admin/assessments/index.php')); }
    $data = $row;
}

$subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id WHERE s.status IN ('active','closed') ORDER BY y.start_date DESC, s.start_date")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['subject_id']  = (int)post('subject_id', 0);
    $data['semester_id'] = (int)post('semester_id', 0);
    $data['name']        = post('name', '');
    $data['type']        = post('type', 'CAT');
    $data['max_marks']   = (float)post('max_marks', 100);
    $data['weight']      = (float)post('weight', 0);
    $data['status']      = post('status', 'active');

    if ($data['subject_id'] <= 0)  $errors[] = 'Subject is required.';
    if ($data['semester_id'] <= 0) $errors[] = 'Semester is required.';
    if ($data['name'] === '')      $errors[] = 'Name is required.';
    if (!in_array($data['type'], ['CAT','Assignment','Practical','Exam','Other'], true)) $data['type'] = 'Other';
    if ($data['max_marks'] <= 0 || $data['max_marks'] > 1000) $errors[] = 'Max marks must be between 1 and 1000.';
    if ($data['weight'] < 0 || $data['weight'] > 100) $errors[] = 'Weight must be between 0 and 100.';
    if (!in_array($data['status'], ['active','inactive'], true)) $data['status'] = 'active';

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE assessments SET subject_id=?, semester_id=?, name=?, type=?, max_marks=?, weight=?, status=? WHERE id=?');
            $stmt->execute([$data['subject_id'], $data['semester_id'], $data['name'], $data['type'], $data['max_marks'], $data['weight'], $data['status'], $id]);
            logActivity((int)$_SESSION['user_id'], 'assessment.update', "Updated assessment #$id");
            setFlash('success', 'Assessment updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO assessments (subject_id, semester_id, name, type, max_marks, weight, status) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$data['subject_id'], $data['semester_id'], $data['name'], $data['type'], $data['max_marks'], $data['weight'], $data['status']]);
            logActivity((int)$_SESSION['user_id'], 'assessment.create', 'Created ' . $data['name']);
            setFlash('success', 'Assessment created.');
        }
        redirect(baseUrl('admin/assessments/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Assessments</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-6"><label class="form-label">Subject <span class="text-danger">*</span></label>
        <select name="subject_id" class="form-select" required><?= selectOptions($subjects, 'id', 'code', $data['subject_id'], 'Select subject') ?></select></div>
      <div class="col-md-6"><label class="form-label">Semester <span class="text-danger">*</span></label>
        <select name="semester_id" class="form-select" required><?= selectOptions($semesters, 'id', 'label', $data['semester_id'], 'Select semester') ?></select></div>
      <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($data['name']) ?>" placeholder="e.g. CAT 1"></div>
      <div class="col-md-4"><label class="form-label">Type</label>
        <select name="type" class="form-select">
          <?php foreach (['CAT','Assignment','Practical','Exam','Other'] as $t): ?>
            <option value="<?= $t ?>" <?= $data['type']===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Max Marks</label>
        <input type="number" step="0.01" name="max_marks" class="form-control" value="<?= e((string)$data['max_marks']) ?>" min="1" max="1000"></div>
      <div class="col-md-3"><label class="form-label">Weight (%)</label>
        <input type="number" step="0.01" name="weight" class="form-control" value="<?= e((string)$data['weight']) ?>" min="0" max="100"></div>
      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected':'' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected':'' ?>>Inactive</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Assessment' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>