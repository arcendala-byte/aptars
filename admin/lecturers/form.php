<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Lecturer' : 'New Lecturer';
$activeNav = 'lecturers';

$errors = [];
$data = [
    'first_name'=>'', 'last_name'=>'', 'staff_number'=>'', 'email'=>'', 'phone'=>'',
    'department_id'=>'', 'status'=>'active',
    'username'=>'', 'user_email'=>'', 'password'=>'',
    'user_id' => null,
];

if ($isEdit) {
    $stmt = db()->prepare("
        SELECT l.*, u.username, u.email AS user_email
        FROM lecturers l LEFT JOIN users u ON u.id = l.user_id
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Lecturer not found.'); redirect(baseUrl('admin/lecturers/index.php')); }
    $data = array_merge($data, $row);
    $data['user_email'] = $row['user_email'] ?: $row['email'];
}

$departments = db()->query("SELECT id, name, code FROM departments WHERE status='active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['first_name']    = post('first_name', '');
    $data['last_name']     = post('last_name', '');
    $data['staff_number']  = strtoupper(post('staff_number', ''));
    $data['email']         = post('email', '');
    $data['phone']         = post('phone', '');
    $data['department_id'] = (int)post('department_id', 0);
    $data['status']        = post('status', 'active');
    $data['username']      = post('username', '');
    $data['user_email']    = post('user_email', '');
    $data['password']      = (string)($_POST['password'] ?? '');

    if ($data['first_name'] === '')    $errors[] = 'First name is required.';
    if ($data['last_name'] === '')     $errors[] = 'Last name is required.';
    if ($data['staff_number'] === '')  $errors[] = 'Staff number is required.';
    if ($data['department_id'] <= 0)   $errors[] = 'Department is required.';
    if ($data['username'] === '')      $errors[] = 'Username is required.';
    if ($data['user_email'] === '' || !filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid login email is required.';
    if (!$isEdit && $data['password'] === '') $errors[] = 'Password is required for new lecturers.';
    if ($isEdit && $data['password'] !== '' && strlen($data['password']) < 4) $errors[] = 'Password must be at least 4 characters.';

    if (!$errors) {
        $checkSql = 'SELECT id FROM lecturers WHERE staff_number = ?' . ($isEdit ? ' AND id <> ?' : '');
        $checkParams = $isEdit ? [$data['staff_number'], $id] : [$data['staff_number']];
        $stmt = db()->prepare($checkSql); $stmt->execute($checkParams);
        if ($stmt->fetch()) $errors[] = 'Staff number already exists.';

        $checkSql = 'SELECT id FROM users WHERE username = ?' . ($isEdit && $data['user_id'] ? ' AND id <> ?' : '');
        $checkParams = $isEdit && $data['user_id'] ? [$data['username'], $data['user_id']] : [$data['username']];
        $stmt = db()->prepare($checkSql); $stmt->execute($checkParams);
        if ($stmt->fetch()) $errors[] = 'Username already taken.';

        $checkSql = 'SELECT id FROM users WHERE email = ?' . ($isEdit && $data['user_id'] ? ' AND id <> ?' : '');
        $checkParams = $isEdit && $data['user_id'] ? [$data['user_email'], $data['user_id']] : [$data['user_email']];
        $stmt = db()->prepare($checkSql); $stmt->execute($checkParams);
        if ($stmt->fetch()) $errors[] = 'Login email already taken.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($isEdit && $data['user_id']) {
                if ($data['password'] !== '') {
                    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE users SET username=?, email=?, password=?, status=? WHERE id=?')
                        ->execute([$data['username'], $data['user_email'], $hash, $data['status'] === 'active' ? 'active' : 'inactive', $data['user_id']]);
                } else {
                    $pdo->prepare('UPDATE users SET username=?, email=?, status=? WHERE id=?')
                        ->execute([$data['username'], $data['user_email'], $data['status'] === 'active' ? 'active' : 'inactive', $data['user_id']]);
                }
                $userId = (int)$data['user_id'];
                $pdo->prepare('UPDATE lecturers SET first_name=?, last_name=?, staff_number=?, email=?, phone=?, department_id=?, status=? WHERE id=?')
                    ->execute([$data['first_name'], $data['last_name'], $data['staff_number'], $data['email'], $data['phone'], $data['department_id'], $data['status'], $id]);
            } else {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (username, email, password, role, status) VALUES (?,?,?,?,?)')
                    ->execute([$data['username'], $data['user_email'], $hash, 'lecturer', 'active']);
                $userId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO lecturers (user_id, staff_number, first_name, last_name, email, phone, department_id, status) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$userId, $data['staff_number'], $data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['department_id'], $data['status']]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
            logActivity((int)$_SESSION['user_id'], $isEdit ? 'lecturer.update' : 'lecturer.create', "Lecturer #$id");
            setFlash('success', $isEdit ? 'Lecturer updated.' : 'Lecturer created.');
            redirect(baseUrl('admin/lecturers/index.php'));
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[APTARS lecturer save] ' . $t->getMessage());
            $errors[] = 'Could not save lecturer. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Lecturers</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <h6 class="text-muted mt-1">Personal Details</h6>
      <div class="col-md-4"><label class="form-label">First Name <span class="text-danger">*</span></label>
        <input type="text" name="first_name" class="form-control" required value="<?= e($data['first_name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Last Name <span class="text-danger">*</span></label>
        <input type="text" name="last_name" class="form-control" required value="<?= e($data['last_name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Staff Number <span class="text-danger">*</span></label>
        <input type="text" name="staff_number" class="form-control" required value="<?= e($data['staff_number']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Department <span class="text-danger">*</span></label>
        <select name="department_id" class="form-select" required>
          <?= selectOptions($departments, 'id', 'name', $data['department_id'], 'Select') ?>
        </select></div>

      <h6 class="text-muted mt-3">Login Account</h6>
      <div class="col-md-4"><label class="form-label">Username <span class="text-danger">*</span></label>
        <input type="text" name="username" class="form-control" required value="<?= e($data['username']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Login Email <span class="text-danger">*</span></label>
        <input type="email" name="user_email" class="form-control" required value="<?= e($data['user_email']) ?>"></div>
      <div class="col-md-4"><label class="form-label"><?= $isEdit ? 'New Password (leave blank to keep)' : 'Password ' ?><?= !$isEdit ? '<span class="text-danger">*</span>' : '' ?></label>
        <input type="password" name="password" class="form-control" <?= !$isEdit ? 'required' : '' ?>></div>

      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= $data['status']==='active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $data['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
        </select></div>

      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Lecturer' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>