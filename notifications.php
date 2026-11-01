<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crud.php';
require_once __DIR__ . '/includes/notifications.php';
requireLogin();

$pageTitle = 'Notifications';
$activeNav = 'notifications';
$userId = (int)$_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = post('action', '');
    if ($action === 'mark_all') {
        markAllNotificationsRead($userId);
        setFlash('success', 'All notifications marked as read.');
    } elseif ($action === 'mark_one') {
        markNotificationRead((int)post('id', 0), $userId);
    }
    redirect(baseUrl('notifications.php'));
}

$filter = get('filter', 'all');
$where = 'WHERE user_id = ?';
$params = [$userId];
if ($filter === 'unread') $where .= ' AND is_read = 0';

$stmt = db()->prepare("SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$notes = $stmt->fetchAll();
$unread = unreadNotificationCount($userId);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Notifications</h1>
      <p class="muted mb-0"><?= $unread ?> unread of <?= count($notes) ?> shown.</p></div>
    <div class="d-flex gap-2">
      <a href="?filter=all"    class="btn btn-sm <?= $filter==='all'    ? 'btn-primary':'btn-outline-secondary' ?>">All</a>
      <a href="?filter=unread" class="btn btn-sm <?= $filter==='unread' ? 'btn-primary':'btn-outline-secondary' ?>">Unread</a>
      <?php if ($unread > 0): ?>
        <form method="post" class="d-inline"><?= csrfField() ?>
          <input type="hidden" name="action" value="mark_all">
          <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-all"></i> Mark all read</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php displayFlash(); ?>

  <?php if ($notes): ?>
    <ul class="notif-list">
      <?php foreach ($notes as $n): ?>
        <li class="notif-item <?= $n['is_read'] ? '' : 'unread' ?> notif-type-<?= e($n['type']) ?>">
          <div class="notif-icon"><i class="bi <?= e(notificationIcon($n['type'])) ?>"></i></div>
          <div class="notif-body">
            <div class="notif-head">
              <strong><?= e($n['title']) ?></strong>
              <small class="muted"><?= e(timeAgo($n['created_at'])) ?></small>
            </div>
            <p><?= e($n['message']) ?></p>
            <?php if (!$n['is_read']): ?>
              <form method="post" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_one">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn btn-sm btn-link p-0">Mark as read</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="panel"><div class="panel-body">
      <p class="empty mb-0"><?= $filter==='unread' ? 'No unread notifications.' : 'No notifications yet.' ?></p>
    </div></div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>