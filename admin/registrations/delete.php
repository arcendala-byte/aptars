<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/registrations/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid registration.'); redirect(baseUrl('admin/registrations/index.php')); }
db()->prepare('DELETE FROM student_subjects WHERE id = ?')->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'registration.delete', "Deleted registration #$id");
setFlash('success', 'Registration removed.');
redirect(baseUrl('admin/registrations/index.php'));