<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Semester' : 'New Semester';
$activeNav = 'semesters';

$errors = [];
$data = ['academic_year_id'=>'', 'semester_name'=>'', 'start_date'=>'', 'end_date'=>'', 'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM semesters WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Semester not found.'); redirect(baseUrl('admin/semesters/index.php')); }
    $data = $row;
}

$years = db()->query("SELECT id, year_name FROM academic_years ORDER BY start_date DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['academic_year_id'] = (int)post('academic_year_id', 0);
    $data['semester_name']    = post('semester_name', '');
    $data['start_date']       = post('start_date', '');
    $data['end_date']         = post('end_date', '');
    $data['status']           = post('status', 'active');

    if ($data['academic_year_id'] <= 0) $errors[] = 'Academic year is required.';
    if ($data['semester_name'] === '')  $errors[] = 'Semester name is required.';
    if (!$data['start_date'])           $errors[] = 'Start date is required.';
    if (!$data['end_date'])             $errors[] = 'End date is required.';
    if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) {
        $errors[] = 'End date must be after start date.';
    }
    if (!in_array($data['status'], ['active','inactive','closed'], true)) $data['status'] = 'active';

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE semesters SET academic_year_id=?, semester_name=?, start_date=?, end_date=?, status=? WHERE id=?');
            $stmt->execute([$data['academic_year_id'], $data['semester_name'], $data['start_date'], $data['end_date'], $data['status'], $id]);
            logActivity((int)$_SESSION['user_id'], 'semester.update', "Updated semester #$id");
            setFlash('success', 'Semester updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO semesters (academic_year_id, semester_name, start_date, end_date, status) VALUES (?,?,?,?,?)');
            $stmt->execute([$data['academic_year_id'], $data['semester_name'], $data['start_date'], $data['end_date'], $data['status']]);
            logActivity((int)$_SESSION['user_id'], 'semester.create', 'Created ' . $data['semester_name']);
            setFlash('success', 'Semester created.');
        }
        redirect(baseUrl('admin/semesters/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Semesters</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-6"><label class="form-label">Academic Year <span class="text-danger">*</span></label>
        <select name="academic_year_id" class="form-select" required>
          <?= selectOptions($years, 'id', 'year_name', $data['academic_year_id'], 'Select academic year') ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">Semester Name <span class="text-danger">*</span></label>
        <input type="text" name="semester_name" class="form-control" required placeholder="e.g. Semester 1" value="<?= e($data['semester_name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Start Date <span class="text-danger">*</span></label>
        <input type="date" name="start_date" class="form-control" required value="<?= e($data['start_date']) ?>"></div>
      <div class="col-md-4"><label class="form-label">End Date <span class="text-danger">*</span></label>
        <input type="date" name="end_date" class="form-control" required value="<?= e($data['end_date']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="closed"   <?= $data['status']==='closed'   ? 'selected' : '' ?>>Closed</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Semester' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>