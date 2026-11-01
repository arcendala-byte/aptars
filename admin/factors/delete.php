<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(baseUrl('admin/factors/index.php'));
csrfVerify();
$id = (int)post('id', 0);
if ($id <= 0) { setFlash('danger','Invalid factor.'); redirect(baseUrl('admin/factors/index.php')); }
db()->prepare("UPDATE influencing_factors SET status='inactive' WHERE id=?")->execute([$id]);
setFlash('success','Factor archived.');
redirect(baseUrl('admin/factors/index.php'));