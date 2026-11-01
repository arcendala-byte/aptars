<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/crud.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$registrationOpen = setting('registration_open', '1') === '1';

$programmes = db()->query("
    SELECT p.id, p.code, p.name, p.department_id, d.code AS dept_code
    FROM programmes p JOIN departments d ON d.id = p.department_id
    WHERE p.status='active' ORDER BY p.code
")->fetchAll();

$errors = [];
$data = [
    'first_name'=>'', 'middle_name'=>'', 'last_name'=>'', 'gender'=>'Male',
    'date_of_birth'=>'', 'phone'=>'', 'email'=>'',
    'programme_id'=>'', 'admission_year'=>(int)date('Y'),
    'registration_number'=>'', 'username'=>'', 'user_email'=>'',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registrationOpen) {
    csrfVerify();

    $data['first_name']          = post('first_name', '');
    $data['middle_name']         = post('middle_name', '');
    $data['last_name']           = post('last_name', '');
    $data['gender']              = post('gender', 'Male');
    $data['date_of_birth']       = post('date_of_birth', '') ?: null;
    $data['phone']               = post('phone', '');
    $data['email']               = post('email', '');
    $data['programme_id']        = (int)post('programme_id', 0);
    $data['admission_year']      = (int)post('admission_year', (int)date('Y'));
    $data['registration_number'] = strtoupper(post('registration_number', ''));
    $data['username']            = post('username', '');
    $data['user_email']          = post('user_email', '');
    $password                    = (string)($_POST['password'] ?? '');
    $passwordConfirm             = (string)($_POST['password_confirm'] ?? '');

    // --- validation ---
    if ($data['first_name'] === '')          $errors[] = 'First name is required.';
    if ($data['last_name'] === '')           $errors[] = 'Last name is required.';
    if ($data['registration_number'] === '') $errors[] = 'Registration number is required.';
    if (!in_array($data['gender'], ['Male','Female','Other'], true)) $data['gender'] = 'Other';
    if ($data['admission_year'] < 2000 || $data['admission_year'] > (int)date('Y') + 1) {
        $errors[] = 'Admission year looks invalid.';
    }
    if ($data['programme_id'] <= 0) $errors[] = 'Please choose a programme.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Personal email is not valid.';
    }
    if ($data['username'] === '') $errors[] = 'Username is required.';
    if ($data['user_email'] === '' || !filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid login email is required.';
    }
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $passwordConfirm) $errors[] = 'Passwords do not match.';

    // derive department from programme
    $data['department_id'] = 0;
    foreach ($programmes as $p) {
        if ((int)$p['id'] === $data['programme_id']) { $data['department_id'] = (int)$p['department_id']; break; }
    }
    if ($data['department_id'] === 0 && !$errors) $errors[] = 'Selected programme is not available.';

    // uniqueness
    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM students WHERE registration_number = ?');
        $stmt->execute([$data['registration_number']]);
        if ($stmt->fetch()) $errors[] = 'That registration number is already in use.';

        $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) $errors[] = 'That username is already taken.';

        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['user_email']]);
        if ($stmt->fetch()) $errors[] = 'That login email is already registered.';
    }

    // --- save ---
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?,?,?,'student','pending')")
                ->execute([$data['username'], $data['user_email'], $hash]);
            $userId = (int)$pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO students
                  (user_id, registration_number, first_name, middle_name, last_name, gender,
                   date_of_birth, phone, email, programme_id, department_id, admission_year, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pending')
            ")->execute([
                $userId, $data['registration_number'], $data['first_name'], $data['middle_name'],
                $data['last_name'], $data['gender'], $data['date_of_birth'], $data['phone'],
                $data['email'], $data['programme_id'], $data['department_id'], $data['admission_year'],
            ]);

            $pdo->commit();

            logActivity($userId, 'student.self_register', "Self-registration: {$data['registration_number']}");

            // Notify all admins
            $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND status='active'")->fetchAll();
            $ids = array_map(fn($r) => (int)$r['id'], $admins);
            createNotificationBulk(
                $ids,
                'New student registration',
                fullName($data) . " ({$data['registration_number']}) has registered and is awaiting approval.",
                'info'
            );

            setFlash('success',
                'Registration submitted. Your account is pending administrator approval. '
                . 'You will be able to log in once an administrator activates your account.');
            redirect(baseUrl('login.php'));
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[APTARS self-register] ' . $t->getMessage());
            $errors[] = 'Could not complete registration. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register &middot; <?= e(setting('system_title', APP_NAME)) ?></title>
<link href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>

<div class="auth-wrap auth-wrap-wide">
  <div class="auth-card auth-card-wide">
    <div class="text-center mb-3">
      <h1><?= e(setting('system_title', 'APTARS')) ?></h1>
      <p class="sub">Student Self-Registration</p>
    </div>

    <?php if (!$registrationOpen): ?>
      <div class="alert alert-warning">
        <i class="bi bi-lock"></i> Registration is currently closed. Please contact the institution's administrator.
      </div>
      <a href="login.php" class="btn btn-outline-secondary w-100">Back to login</a>
    <?php else: ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0">
          <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul></div>
      <?php endif; ?>

      <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle"></i>
        After submitting, your account must be <strong>approved by an administrator</strong> before you can log in.
      </div>

      <form method="post" novalidate>
        <?= csrfField() ?>

        <h6 class="form-section">Personal details</h6>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">First name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control" required value="<?= e($data['first_name']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Middle name</label>
            <input type="text" name="middle_name" class="form-control" value="<?= e($data['middle_name']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Last name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" required value="<?= e($data['last_name']) ?>"></div>

          <div class="col-md-4"><label class="form-label">Gender</label>
            <select name="gender" class="form-select">
              <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= $data['gender']===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label">Date of birth</label>
            <input type="date" name="date_of_birth" class="form-control" value="<?= e($data['date_of_birth']) ?>"></div>
          <div class="col-md-4"><label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>"></div>

          <div class="col-12"><label class="form-label">Personal email</label>
            <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>"
                   placeholder="Optional — for correspondence"></div>
        </div>

        <h6 class="form-section mt-3">Academic details</h6>
        <div class="row g-3">
          <div class="col-md-8"><label class="form-label">Programme <span class="text-danger">*</span></label>
            <select name="programme_id" class="form-select" required>
              <?= selectOptions($programmes, 'id', 'code', $data['programme_id'], 'Select your programme') ?>
            </select></div>
          <div class="col-md-4"><label class="form-label">Admission year</label>
            <input type="number" name="admission_year" class="form-control"
                   min="2000" max="<?= date('Y')+1 ?>" value="<?= (int)$data['admission_year'] ?>"></div>

          <div class="col-12"><label class="form-label">Registration number <span class="text-danger">*</span></label>
            <input type="text" name="registration_number" class="form-control" required
                   value="<?= e($data['registration_number']) ?>"
                   placeholder="e.g. CS/2024/010">
            <small class="muted">Must be unique. If unsure, ask your department office.</small></div>
        </div>

        <h6 class="form-section mt-3">Login credentials</h6>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required value="<?= e($data['username']) ?>"></div>
          <div class="col-md-8"><label class="form-label">Login email <span class="text-danger">*</span></label>
            <input type="email" name="user_email" class="form-control" required value="<?= e($data['user_email']) ?>"></div>
          <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required minlength="6"></div>
          <div class="col-md-6"><label class="form-label">Confirm password <span class="text-danger">*</span></label>
            <input type="password" name="password_confirm" class="form-control" required minlength="6"></div>
        </div>

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary flex-fill">Create account</button>
          <a href="login.php" class="btn btn-link">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>