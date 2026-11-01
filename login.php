<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(baseUrl('index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $username = post('username', '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid credentials.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Your account is still awaiting administrator approval.';
        } elseif ($user['status'] !== 'active') {
            $error = 'This account is not active. Contact the administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid credentials.';
        } else {
            $profileId = null; $fullName = $user['username'];
            if ($user['role'] === 'student') {
                $ps = db()->prepare('SELECT id, first_name, middle_name, last_name FROM students WHERE user_id=? LIMIT 1');
                $ps->execute([$user['id']]);
                if ($row = $ps->fetch()) {
                    $profileId = (int)$row['id'];
                    $fullName  = trim($row['first_name'].' '.($row['middle_name'] ?? '').' '.$row['last_name']);
                }
            } elseif ($user['role'] === 'lecturer') {
                $ps = db()->prepare('SELECT id, first_name, last_name FROM lecturers WHERE user_id=? LIMIT 1');
                $ps->execute([$user['id']]);
                if ($row = $ps->fetch()) {
                    $profileId = (int)$row['id'];
                    $fullName  = trim($row['first_name'].' '.$row['last_name']);
                }
            }

            loginUser($user, $profileId, $fullName);
            db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
            logActivity((int)$user['id'], 'login', 'User logged in');

            switch ($user['role']) {
                case 'admin':    redirect(baseUrl('admin/dashboard.php'));    break;
                case 'lecturer': redirect(baseUrl('lecturer/dashboard.php')); break;
                case 'student':  redirect(baseUrl('student/dashboard.php'));  break;
            }
        }
    }
}

$flash = getFlash();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login &middot; <?= e(setting('system_title', APP_NAME)) ?></title>
<link href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <h1><?= e(setting('system_title', 'APTARS')) ?></h1>
    <p class="sub">Academic Performance Tracking, Analysis &amp; Reporting System</p>

    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrfField() ?>
      <div class="mb-3">
        <label class="form-label">Username or Email</label>
        <input type="text" name="username" class="form-control" required autofocus
               value="<?= e(post('username', '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100">Sign in</button>
    </form>

    <div class="text-center mt-3">
      <a href="<?= e(baseUrl('index.php')) ?>" class="small text-decoration-none">&larr; Back to home</a>
    </div>

    <hr class="my-3">
    <p class="text-center small mb-0">
      New student? <a href="<?= e(baseUrl('register.php')) ?>">Create an account</a>
    </p>

    <p class="sub mt-3 mb-0 text-center" style="font-size:.78rem">
      Demo: <code>admin</code> / <code>lecturer1</code> / <code>student1</code> — password: <code>password</code>
    </p>
  </div>
</div>
</body>
</html>