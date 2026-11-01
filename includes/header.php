<?php
if (!headers_sent()) header('X-Frame-Options: SAMEORIGIN');
require_once __DIR__ . '/notifications.php';
$user = currentUser();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Dashboard') ?> &middot; <?= e(setting('system_title', APP_NAME)) ?></title>

<!-- Local vendor assets — no CDN dependency -->
<link href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">

<?php if ($user && $user['role'] === 'admin'): ?>
<form method="get" action="<?= e(baseUrl('admin/search.php')) ?>" class="global-search">
  <input type="text" name="q" placeholder="Search students, lecturers, subjects…" autocomplete="off">
  <button type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
</form>
<?php endif; ?>

<?php if ($user):
    $unreadNotif = unreadNotificationCount((int)$user['id']);
?>
  <a href="<?= e(baseUrl('notifications.php')) ?>" class="notif-bell" title="Notifications">
    <i class="bi bi-bell<?= $unreadNotif > 0 ? '-fill' : '' ?>"></i>
    <?php if ($unreadNotif > 0): ?>
      <span class="notif-badge"><?= $unreadNotif > 99 ? '99+' : $unreadNotif ?></span>
    <?php endif; ?>
  </a>
<?php endif; ?>