<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/departments/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid department.'); redirect(baseUrl('admin/departments/index.php')); }
db()->prepare("UPDATE departments SET status='inactive' WHERE id=?")->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'department.archive', "Archived department #$id");
setFlash('success', 'Department archived.');
redirect(baseUrl('admin/departments/index.php'));