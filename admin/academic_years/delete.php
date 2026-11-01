<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/academic_years/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid academic year.'); redirect(baseUrl('admin/academic_years/index.php')); }
db()->prepare("UPDATE academic_years SET status='inactive' WHERE id=?")->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'year.archive', "Archived academic year #$id");
setFlash('success', 'Academic year archived.');
redirect(baseUrl('admin/academic_years/index.php'));