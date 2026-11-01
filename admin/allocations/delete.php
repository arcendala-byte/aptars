<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/allocations/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger', 'Invalid allocation.'); redirect(baseUrl('admin/allocations/index.php')); }
db()->prepare('DELETE FROM lecturer_subjects WHERE id = ?')->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'allocation.delete', "Deleted allocation #$id");
setFlash('success', 'Allocation removed.');
redirect(baseUrl('admin/allocations/index.php'));