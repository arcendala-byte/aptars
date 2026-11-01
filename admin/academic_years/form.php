<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Academic Year' : 'New Academic Year';
$activeNav = 'academic_years';

$errors = [];
$data = ['year_name'=>'', 'start_date'=>'', 'end_date'=>'', 'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM academic_years WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Academic year not found.'); redirect(baseUrl('admin/academic_years/index.php')); }
    $data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['year_name']  = post('year_name', '');
    $data['start_date'] = post('start_date', '');
    $data['end_date']   = post('end_date', '');
    $data['status']     = post('status', 'active');

    if ($data['year_name'] === '') $errors[] = 'Year name is required.';
    if (!$data['start_date'])      $errors[] = 'Start date is required.';
    if (!$data['end_date'])        $errors[] = 'End date is required.';
    if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) {
        $errors[] = 'End date must be after start date.';
    }
    if (!in_array($data['status'], ['active','inactive','closed'], true)) $data['status'] = 'active';

    if (!$errors) {
        $sql = $isEdit ? 'SELECT id FROM academic_years WHERE year_name = ? AND id <> ?' : 'SELECT id FROM academic_years WHERE year_name = ?';
        $params = $isEdit ? [$data['year_name'], $id] : [$data['year_name']];
        $stmt = db()->prepare($sql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Year name already exists.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE academic_years SET year_name=?, start_date=?, end_date=?, status=? WHERE id=?');
            $stmt->execute([$data['year_name'], $data['start_date'], $data['end_date'], $data['status'], $id]);
            logActivity((int)$_SESSION['user_id'], 'year.update', "Updated academic year #$id");
            setFlash('success', 'Academic year updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO academic_years (year_name, start_date, end_date, status) VALUES (?,?,?,?)');
            $stmt->execute([$data['year_name'], $data['start_date'], $data['end_date'], $data['status']]);
            logActivity((int)$_SESSION['user_id'], 'year.create', 'Created ' . $data['year_name']);
            setFlash('success', 'Academic year created.');
        }
        redirect(baseUrl('admin/academic_years/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Academic Years</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-6"><label class="form-label">Year Name <span class="text-danger">*</span></label>
        <input type="text" name="year_name" class="form-control" required placeholder="2024/2025" value="<?= e($data['year_name']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Start Date <span class="text-danger">*</span></label>
        <input type="date" name="start_date" class="form-control" required value="<?= e($data['start_date']) ?>"></div>
      <div class="col-md-3"><label class="form-label">End Date <span class="text-danger">*</span></label>
        <input type="date" name="end_date" class="form-control" required value="<?= e($data['end_date']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="closed"   <?= $data['status']==='closed'   ? 'selected' : '' ?>>Closed</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Academic Year' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>