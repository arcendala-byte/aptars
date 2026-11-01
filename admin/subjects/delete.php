<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/subjects/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid subject.'); redirect(baseUrl('admin/subjects/index.php')); }
db()->prepare("UPDATE subjects SET status='inactive' WHERE id=?")->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'subject.archive', "Archived subject #$id");
setFlash('success', 'Subject archived.');
redirect(baseUrl('admin/subjects/index.php'));