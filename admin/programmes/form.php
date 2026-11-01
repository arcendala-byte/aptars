<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Programme' : 'New Programme';
$activeNav = 'programmes';

$errors = [];
$data = ['department_id'=>'', 'name'=>'', 'code'=>'', 'level'=>'Degree', 'duration'=>4, 'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM programmes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Programme not found.'); redirect(baseUrl('admin/programmes/index.php')); }
    $data = $row;
}

$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['department_id'] = (int)post('department_id', 0);
    $data['name']          = post('name', '');
    $data['code']          = strtoupper(post('code', ''));
    $data['level']         = post('level', 'Degree');
    $data['duration']      = (int)post('duration', 4);
    $data['status']        = post('status', 'active');

    if ($data['department_id'] <= 0) $errors[] = 'Department is required.';
    if ($data['name'] === '')        $errors[] = 'Name is required.';
    if ($data['code'] === '')        $errors[] = 'Code is required.';
    if (!in_array($data['level'], ['Certificate','Diploma','Degree','Masters','PhD'], true)) $data['level'] = 'Degree';
    if ($data['duration'] < 1 || $data['duration'] > 10) $errors[] = 'Duration must be 1–10 years.';

    if (!$errors) {
        $sql = $isEdit
            ? 'SELECT id FROM programmes WHERE code = ? AND id <> ?'
            : 'SELECT id FROM programmes WHERE code = ?';
        $params = $isEdit ? [$data['code'], $id] : [$data['code']];
        $stmt = db()->prepare($sql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Code already exists.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE programmes SET department_id=?, name=?, code=?, level=?, duration=?, status=? WHERE id=?');
            $stmt->execute([$data['department_id'], $data['name'], $data['code'], $data['level'], $data['duration'], $data['status'], $id]);
            logActivity((int)$_SESSION['user_id'], 'programme.update', "Updated programme #$id");
            setFlash('success', 'Programme updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO programmes (department_id, name, code, level, duration, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$data['department_id'], $data['name'], $data['code'], $data['level'], $data['duration'], $data['status']]);
            logActivity((int)$_SESSION['user_id'], 'programme.create', 'Created ' . $data['code']);
            setFlash('success', 'Programme created.');
        }
        redirect(baseUrl('admin/programmes/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Programmes</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-6"><label class="form-label">Department <span class="text-danger">*</span></label>
        <select name="department_id" class="form-select" required>
          <?= selectOptions($departments, 'id', 'name', $data['department_id'], 'Select department') ?>
        </select></div>
      <div class="col-md-6"><label class="form-label">Level <span class="text-danger">*</span></label>
        <select name="level" class="form-select" required>
          <?php foreach (['Certificate','Diploma','Degree','Masters','PhD'] as $lv): ?>
            <option value="<?= $lv ?>" <?= $data['level']===$lv?'selected':'' ?>><?= $lv ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($data['name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control" required maxlength="20" value="<?= e($data['code']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Duration (years)</label>
        <input type="number" name="duration" class="form-control" min="1" max="10" value="<?= (int)$data['duration'] ?>"></div>
      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Programme' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>