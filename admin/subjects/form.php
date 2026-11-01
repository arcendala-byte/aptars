<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Subject' : 'New Subject';
$activeNav = 'subjects';

$errors = [];
$data = ['programme_id'=>'', 'department_id'=>'', 'code'=>'', 'name'=>'', 'credit_hours'=>3, 'semester'=>'1', 'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM subjects WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Subject not found.'); redirect(baseUrl('admin/subjects/index.php')); }
    $data = $row;
}

$programmes = db()->query("
    SELECT p.id, p.code, p.name, p.department_id, d.code AS dept_code
    FROM programmes p JOIN departments d ON d.id = p.department_id
    WHERE p.status='active' ORDER BY p.code
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['programme_id'] = (int)post('programme_id', 0);
    $data['code']         = strtoupper(post('code', ''));
    $data['name']         = post('name', '');
    $data['credit_hours'] = (int)post('credit_hours', 3);
    $data['semester']     = post('semester', '1');
    $data['status']       = post('status', 'active');

    if ($data['programme_id'] <= 0) $errors[] = 'Programme is required.';
    if ($data['code'] === '')       $errors[] = 'Code is required.';
    if ($data['name'] === '')       $errors[] = 'Name is required.';
    if ($data['credit_hours'] < 1 || $data['credit_hours'] > 10) $errors[] = 'Credits must be 1–10.';
    if (!in_array($data['semester'], ['1','2','3'], true)) $data['semester'] = '1';

    $data['department_id'] = 0;
    foreach ($programmes as $p) {
        if ((int)$p['id'] === $data['programme_id']) { $data['department_id'] = (int)$p['department_id']; break; }
    }
    if ($data['department_id'] === 0 && !$errors) $errors[] = 'Selected programme has no department.';

    if (!$errors) {
        $sql = $isEdit
            ? 'SELECT id FROM subjects WHERE code = ? AND id <> ?'
            : 'SELECT id FROM subjects WHERE code = ?';
        $params = $isEdit ? [$data['code'], $id] : [$data['code']];
        $stmt = db()->prepare($sql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Code already exists.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE subjects SET programme_id=?, department_id=?, code=?, name=?, credit_hours=?, semester=?, status=? WHERE id=?');
            $stmt->execute([$data['programme_id'], $data['department_id'], $data['code'], $data['name'], $data['credit_hours'], $data['semester'], $data['status'], $id]);
            logActivity((int)$_SESSION['user_id'], 'subject.update', "Updated subject #$id");
            setFlash('success', 'Subject updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO subjects (programme_id, department_id, code, name, credit_hours, semester, status) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$data['programme_id'], $data['department_id'], $data['code'], $data['name'], $data['credit_hours'], $data['semester'], $data['status']]);
            logActivity((int)$_SESSION['user_id'], 'subject.create', 'Created ' . $data['code']);
            setFlash('success', 'Subject created.');
        }
        redirect(baseUrl('admin/subjects/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Subjects</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-6"><label class="form-label">Programme <span class="text-danger">*</span></label>
        <select name="programme_id" class="form-select" required>
          <?= selectOptions($programmes, 'id', 'code', $data['programme_id'], 'Select programme') ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Semester</label>
        <select name="semester" class="form-select">
          <?php foreach (['1','2','3'] as $s): ?>
            <option value="<?= $s ?>" <?= $data['semester']===$s?'selected':'' ?>>Semester <?= $s ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Credit Hours</label>
        <input type="number" name="credit_hours" class="form-control" min="1" max="10" value="<?= (int)$data['credit_hours'] ?>"></div>
      <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($data['name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control" required maxlength="20" value="<?= e($data['code']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Subject' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>