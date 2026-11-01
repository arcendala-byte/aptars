<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$id     = (int)(post('id') ?? get('id', 0));
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Student' : 'New Student';
$activeNav = 'students';

$errors = [];
$data = [
    'first_name'=>'', 'middle_name'=>'', 'last_name'=>'',
    'registration_number'=>'', 'gender'=>'Male', 'date_of_birth'=>'',
    'phone'=>'', 'email'=>'', 'programme_id'=>'', 'department_id'=>'',
    'admission_year'=>date('Y'), 'status'=>'active',
    'username'=>'', 'user_email'=>'', 'password'=>'',
    'user_id'=>null,
];

if ($isEdit) {
    $stmt = db()->prepare("
        SELECT s.*, u.username, u.email AS user_email
        FROM students s LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { setFlash('danger', 'Student not found.'); redirect(baseUrl('admin/students/index.php')); }
    $data = array_merge($data, $row);
    $data['user_email'] = $row['user_email'] ?: $row['email'];
}

$programmes = db()->query("
    SELECT p.id, p.code, p.name, p.department_id, d.code AS dept_code
    FROM programmes p JOIN departments d ON d.id = p.department_id
    WHERE p.status='active' ORDER BY p.code
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['first_name']          = post('first_name', '');
    $data['middle_name']         = post('middle_name', '');
    $data['last_name']           = post('last_name', '');
    $data['registration_number'] = strtoupper(post('registration_number', ''));
    $data['gender']              = post('gender', 'Male');
    $data['date_of_birth']       = post('date_of_birth', '') ?: null;
    $data['phone']               = post('phone', '');
    $data['email']               = post('email', '');
    $data['programme_id']        = (int)post('programme_id', 0);
    $data['admission_year']      = (int)post('admission_year', (int)date('Y'));
    $data['status']              = post('status', 'active');
    $data['username']            = post('username', '');
    $data['user_email']          = post('user_email', '');
    $data['password']            = (string)($_POST['password'] ?? '');

    if ($data['first_name'] === '')          $errors[] = 'First name is required.';
    if ($data['last_name'] === '')           $errors[] = 'Last name is required.';
    if ($data['registration_number'] === '') $errors[] = 'Registration number is required.';
    if ($data['programme_id'] <= 0)          $errors[] = 'Programme is required.';
    if (!in_array($data['gender'], ['Male','Female','Other'], true)) $data['gender'] = 'Other';
    if ($data['admission_year'] < 2000 || $data['admission_year'] > (int)date('Y') + 1) $errors[] = 'Admission year looks invalid.';
    if ($data['username'] === '')            $errors[] = 'Username is required.';
    if ($data['user_email'] === '' || !filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid login email is required.';
    if (!$isEdit && $data['password'] === '') $errors[] = 'Password is required for new students.';

    $data['department_id'] = 0;
    foreach ($programmes as $p) {
        if ((int)$p['id'] === $data['programme_id']) { $data['department_id'] = (int)$p['department_id']; break; }
    }
    if ($data['department_id'] === 0 && !$errors) $errors[] = 'Selected programme has no department.';

    if (!$errors) {
        $checkSql = 'SELECT id FROM students WHERE registration_number = ?' . ($isEdit ? ' AND id <> ?' : '');
        $params = $isEdit ? [$data['registration_number'], $id] : [$data['registration_number']];
        $stmt = db()->prepare($checkSql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Registration number already exists.';

        $checkSql = 'SELECT id FROM users WHERE username = ?' . ($isEdit && $data['user_id'] ? ' AND id <> ?' : '');
        $params = $isEdit && $data['user_id'] ? [$data['username'], $data['user_id']] : [$data['username']];
        $stmt = db()->prepare($checkSql); $stmt->execute($params);
        if ($stmt->fetch()) $errors[] = 'Username already taken.';

        $checkSql = 'SELECT id FROM users WHERE email = ?' . ($isEdit && $data['user_id'] ? ' AND id <> ?' : '');
        $params = $isEdit && $data['user_id'] ? [$data['user_email'], $data['user_id']] : [$data['user_email']];
        $stmt = db()->prepare($checkSql); $stmt->execute($params);
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
                $pdo->prepare('UPDATE students SET first_name=?, middle_name=?, last_name=?, registration_number=?, gender=?, date_of_birth=?, phone=?, email=?, programme_id=?, department_id=?, admission_year=?, status=? WHERE id=?')
                    ->execute([$data['first_name'], $data['middle_name'], $data['last_name'], $data['registration_number'], $data['gender'], $data['date_of_birth'], $data['phone'], $data['email'], $data['programme_id'], $data['department_id'], $data['admission_year'], $data['status'], $id]);
            } else {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (username, email, password, role, status) VALUES (?,?,?,?,?)')
                    ->execute([$data['username'], $data['user_email'], $hash, 'student', 'active']);
                $userId = (int)$pdo->lastInsertId();

                $pdo->prepare('INSERT INTO students (user_id, registration_number, first_name, middle_name, last_name, gender, date_of_birth, phone, email, programme_id, department_id, admission_year, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$userId, $data['registration_number'], $data['first_name'], $data['middle_name'], $data['last_name'], $data['gender'], $data['date_of_birth'], $data['phone'], $data['email'], $data['programme_id'], $data['department_id'], $data['admission_year'], $data['status']]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
            logActivity((int)$_SESSION['user_id'], $isEdit ? 'student.update' : 'student.create', "Student #$id");
            setFlash('success', $isEdit ? 'Student updated.' : 'Student created.');
            redirect(baseUrl('admin/students/index.php'));
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[APTARS student save] ' . $t->getMessage());
            $errors[] = 'Could not save student. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Students</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <h6 class="text-muted mt-1">Personal Details</h6>
      <div class="col-md-4"><label class="form-label">First Name <span class="text-danger">*</span></label>
        <input type="text" name="first_name" class="form-control" required value="<?= e($data['first_name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Middle Name</label>
        <input type="text" name="middle_name" class="form-control" value="<?= e($data['middle_name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Last Name <span class="text-danger">*</span></label>
        <input type="text" name="last_name" class="form-control" required value="<?= e($data['last_name']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Gender</label>
        <select name="gender" class="form-select">
          <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?= $g ?>" <?= $data['gender']===$g?'selected':'' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Date of Birth</label>
        <input type="date" name="date_of_birth" class="form-control" value="<?= e($data['date_of_birth']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>"></div>

      <h6 class="text-muted mt-3">Academic Details</h6>
      <div class="col-md-4"><label class="form-label">Registration Number <span class="text-danger">*</span></label>
        <input type="text" name="registration_number" class="form-control" required value="<?= e($data['registration_number']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Programme <span class="text-danger">*</span></label>
        <select name="programme_id" class="form-select" required>
          <?= selectOptions($programmes, 'id', 'code', $data['programme_id'], 'Select programme') ?>
        </select></div>
      <div class="col-md-2"><label class="form-label">Adm. Year</label>
        <input type="number" name="admission_year" class="form-control" min="2000" max="<?= date('Y')+1 ?>" value="<?= (int)$data['admission_year'] ?>"></div>
      <div class="col-md-2"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active"    <?= $data['status']==='active'    ? 'selected' : '' ?>>Active</option>
          <option value="inactive"  <?= $data['status']==='inactive'  ? 'selected' : '' ?>>Inactive</option>
          <option value="graduated" <?= $data['status']==='graduated' ? 'selected' : '' ?>>Graduated</option>
        </select></div>

      <h6 class="text-muted mt-3">Login Account</h6>
      <div class="col-md-4"><label class="form-label">Username <span class="text-danger">*</span></label>
        <input type="text" name="username" class="form-control" required value="<?= e($data['username']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Login Email <span class="text-danger">*</span></label>
        <input type="email" name="user_email" class="form-control" required value="<?= e($data['user_email']) ?>"></div>
      <div class="col-md-4"><label class="form-label"><?= $isEdit ? 'New Password (leave blank to keep)' : 'Password ' ?><?= !$isEdit ? '<span class="text-danger">*</span>' : '' ?></label>
        <input type="password" name="password" class="form-control" <?= !$isEdit ? 'required' : '' ?>></div>

      <div class="col-12">
        <button class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Student' ?></button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>