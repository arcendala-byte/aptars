<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/programmes/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid programme.'); redirect(baseUrl('admin/programmes/index.php')); }
db()->prepare("UPDATE programmes SET status='inactive' WHERE id=?")->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'programme.archive', "Archived programme #$id");
setFlash('success', 'Programme archived.');
redirect(baseUrl('admin/programmes/index.php'));