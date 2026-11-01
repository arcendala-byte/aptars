<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/notifications.php';
requireRole('admin');

$id = (int)(post('id') ?? get('id', 0));
if ($id <= 0) { setFlash('danger','Invalid user.'); redirect(baseUrl('admin/users/index.php')); }

$stmt = db()->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { setFlash('danger','User not found.'); redirect(baseUrl('admin/users/index.php')); }

$isSelf = ((int)$user['id'] === (int)$_SESSION['user_id']);

$errors = [];
$data = ['username'=>$user['username'], 'email'=>$user['email'], 'status'=>$user['status']];
$newPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['username'] = post('username', '');
    $data['email']    = post('email', '');
    $data['status']   = post('status', $user['status']);
    $newPassword      = (string)($_POST['password'] ?? '');

    if ($data['username'] === '') $errors[] = 'Username is required.';
    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!in_array($data['status'], ['pending','active','inactive','suspended'], true)) $data['status'] = $user['status'];
    if ($isSelf && $data['status'] !== 'active') $errors[] = 'You cannot deactivate your own account.';
    if ($newPassword !== '' && strlen($newPassword) < 4) $errors[] = 'New password must be at least 4 characters.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE username=? AND id<>?');
        $stmt->execute([$data['username'], $id]);
        if ($stmt->fetch()) $errors[] = 'Username already taken.';

        $stmt = db()->prepare('SELECT id FROM users WHERE email=? AND id<>?');
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) $errors[] = 'Email already taken.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET username=?, email=?, status=?, password=? WHERE id=?')
                    ->execute([$data['username'], $data['email'], $data['status'], $hash, $id]);
                logActivity((int)$_SESSION['user_id'], 'user.password_reset', "Reset password for user #$id");
            } else {
                $pdo->prepare('UPDATE users SET username=?, email=?, status=? WHERE id=?')
                    ->execute([$data['username'], $data['email'], $data['status'], $id]);
            }

            if ($user['role'] === 'student') {
                $profileStatus = match ($data['status']) {
                    'active'   => 'active',
                    'pending'  => 'pending',
                    default    => 'inactive',
                };
                $pdo->prepare('UPDATE students SET status=? WHERE user_id=?')
                    ->execute([$profileStatus, $id]);
            } elseif ($user['role'] === 'lecturer') {
                $profileStatus = match ($data['status']) {
                    'active'   => 'active',
                    'pending'  => 'inactive',
                    default    => 'inactive',
                };
                $pdo->prepare('UPDATE lecturers SET status=? WHERE user_id=?')
                    ->execute([$profileStatus, $id]);
            }

            $pdo->commit();

            if ($user['status'] === 'pending' && $data['status'] === 'active') {
                $name = '';
                if ($user['role'] === 'student') {
                    $s = db()->prepare('SELECT first_name FROM students WHERE user_id=? LIMIT 1');
                    $s->execute([$id]);
                    $name = (string)$s->fetchColumn();
                }
                $msg = 'Your account has been approved. You can now log in with your credentials.'
                     . ($name !== '' ? " Welcome, {$name}." : '');
                createNotification((int)$id, 'Account approved', $msg, 'success');
                logActivity((int)$_SESSION['user_id'], 'user.approve', "Approved user #$id");
            }

            setFlash('success', 'User updated.');
            redirect(baseUrl('admin/users/index.php'));
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[APTARS user edit] ' . $t->getMessage());
            $errors[] = 'Could not save user. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1>Edit User Account</h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Users</a></p></div>
  <?= errorList($errors) ?>

  <div class="panel"><div class="panel-body">
    <?php if ($user['status'] === 'pending'): ?>
      <div class="alert alert-warning py-2 small">
        <i class="bi bi-hourglass-split"></i>
        <strong>This account is pending approval.</strong>
        Set status to <strong>Active</strong> to grant access.
      </div>
    <?php endif; ?>

    <div class="alert alert-info py-2 small">
      <strong>Role:</strong> <?= e($user['role']) ?>
      <?php if ($isSelf): ?> &middot; <em>This is your own account.</em><?php endif; ?>
    </div>

    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <input type="hidden" name="id" value="<?= $id ?>">

      <div class="col-md-6"><label class="form-label">Username <span class="text-danger">*</span></label>
        <input type="text" name="username" class="form-control" required value="<?= e($data['username']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" required value="<?= e($data['email']) ?>"></div>

      <div class="col-md-6"><label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password"></div>

      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select" <?= $isSelf ? 'disabled' : '' ?>>
          <option value="pending"   <?= $data['status']==='pending'  ?'selected':'' ?>>Pending</option>
          <option value="active"    <?= $data['status']==='active'   ?'selected':'' ?>>Active</option>
          <option value="inactive"  <?= $data['status']==='inactive' ?'selected':'' ?>>Inactive</option>
          <option value="suspended" <?= $data['status']==='suspended'?'selected':'' ?>>Suspended</option>
        </select>
        <?php if ($isSelf): ?><input type="hidden" name="status" value="active"><?php endif; ?>
      </div>

      <div class="col-12">
        <button class="btn btn-primary">Save Changes</button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>