<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Factor' : 'New Influencing Factor';
$activeNav = 'factors';

$errors = [];
$data = ['name'=>'','code'=>'','description'=>'','scale_min'=>1,'scale_max'=>5,'status'=>'active'];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM influencing_factors WHERE id=?');
    $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) { setFlash('danger','Factor not found.'); redirect(baseUrl('admin/factors/index.php')); }
    $data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['name']        = post('name', '');
    $data['code']        = strtoupper(post('code', ''));
    $data['description'] = post('description', '');
    $data['scale_min']   = (int)post('scale_min', 1);
    $data['scale_max']   = (int)post('scale_max', 5);
    $data['status']      = post('status', 'active');

    if ($data['name'] === '') $errors[] = 'Name is required.';
    if ($data['code'] === '') $errors[] = 'Code is required.';
    if ($data['scale_min'] < 0 || $data['scale_max'] > 100 || $data['scale_min'] >= $data['scale_max']) {
        $errors[] = 'Scale range is invalid.';
    }

    if (!$errors) {
        $sql = $isEdit ? 'SELECT id FROM influencing_factors WHERE code=? AND id<>?' : 'SELECT id FROM influencing_factors WHERE code=?';
        $params = $isEdit ? [$data['code'], $id] : [$data['code']];
        $stmt = db()->prepare($sql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Code already exists.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE influencing_factors SET name=?, code=?, description=?, scale_min=?, scale_max=?, status=? WHERE id=?');
            $stmt->execute([$data['name'], $data['code'], $data['description'], $data['scale_min'], $data['scale_max'], $data['status'], $id]);
            setFlash('success', 'Factor updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO influencing_factors (name, code, description, scale_min, scale_max, status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$data['name'], $data['code'], $data['description'], $data['scale_min'], $data['scale_max'], $data['status']]);
            setFlash('success', 'Factor created.');
        }
        logActivity((int)$_SESSION['user_id'], 'factor.save', 'Factor ' . $data['code']);
        redirect(baseUrl('admin/factors/index.php'));
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Factors</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($data['name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control" required value="<?= e($data['code']) ?>"></div>
      <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= e($data['description']) ?></textarea></div>
      <div class="col-md-3"><label class="form-label">Scale Min</label>
        <input type="number" name="scale_min" class="form-control" min="0" max="100" value="<?= (int)$data['scale_min'] ?>"></div>
      <div class="col-md-3"><label class="form-label">Scale Max</label>
        <input type="number" name="scale_max" class="form-control" min="1" max="100" value="<?= (int)$data['scale_max'] ?>"></div>
      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected':'' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected':'' ?>>Inactive</option>
        </select></div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Factor' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>