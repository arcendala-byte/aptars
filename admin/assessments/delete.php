<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/assessments/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger','Invalid assessment.'); redirect(baseUrl('admin/assessments/index.php')); }
db()->prepare("UPDATE assessments SET status='inactive' WHERE id=?")->execute([$id]);
logActivity((int)$_SESSION['user_id'], 'assessment.archive', "Archived assessment #$id");
setFlash('success','Assessment archived.');
redirect(baseUrl('admin/assessments/index.php'));