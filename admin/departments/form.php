<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Department' : 'New Department';
$activeNav = 'departments';

$errors = [];
$data = ['name'=>'', 'code'=>'', 'description'=>'', 'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM departments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Department not found.'); redirect(baseUrl('admin/departments/index.php')); }
    $data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['name']        = post('name', '');
    $data['code']        = strtoupper(post('code', ''));
    $data['description'] = post('description', '');
    $data['status']      = post('status', 'active');

    if ($data['name'] === '') $errors[] = 'Name is required.';
    if ($data['code'] === '') $errors[] = 'Code is required.';
    if (!in_array($data['status'], ['active','inactive'], true)) $data['status'] = 'active';

    if (!$errors) {
        $sql = $isEdit
            ? 'SELECT id FROM departments WHERE code = ? AND id <> ?'
            : 'SELECT id FROM departments WHERE code = ?';
        $params = $isEdit ? [$data['code'], $id] : [$data['code']];
        $stmt = db()->prepare($sql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Code already exists.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE departments SET name=?, code=?, description=?, status=? WHERE id=?');
            $stmt->execute([$data['name'], $data['code'], $data['description'], $data['status'], $id]);
            logActivity((int)$_SESSION['user_id'], 'department.update', "Updated department #$id");
            setFlash('success', 'Department updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO departments (name, code, description, status) VALUES (?,?,?,?)');
            $stmt->execute([$data['name'], $data['code'], $data['description'], $data['status']]);
            logActivity((int)$_SESSION['user_id'], 'department.create', 'Created ' . $data['code']);
            setFlash('success', 'Department created.');
        }
        redirect(baseUrl('admin/departments/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Departments</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($data['name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control" required maxlength="20" value="<?= e($data['code']) ?>"></div>
      <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($data['description']) ?></textarea></div>
      <div class="col-md-4"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Department' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>