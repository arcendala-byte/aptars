<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/semesters/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid semester.'); redirect(baseUrl('admin/semesters/index.php')); }
db()->prepare("UPDATE semesters SET status='inactive' WHERE id=?")->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'semester.archive', "Archived semester #$id");
setFlash('success', 'Semester archived.');
redirect(baseUrl('admin/semesters/index.php'));